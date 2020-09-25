<?php

namespace App\Controller;

use App\Entity\HttpCode;
use App\Entity\SerializeGroups;
use App\Entity\User;
use App\Repository\UserRepository;
use DateInterval;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Routing\Annotation\Route;
use Nelmio\ApiDocBundle\Annotation\Security;

use Swagger\Annotations as SWG;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * @Route("/user")
 */
class UsersController extends ApiController {

    /**
     * Get information of an user
     *
     * @Route("/profile/{id}", name="user_info", methods={"GET"})
     *
     * @SWG\Response(response="200", description="User returned")
     * @SWG\Response(response="404", description="User not found")
     *
     * @SWG\Tag(name="Users")
     * @Security(name="Bearer")
     *
     * @param $id
     * @param UserRepository $userRepository
     * @param SerializerInterface $serializer
     *
     * @return Response
     */
    public function profile(
        $id,
        UserRepository $userRepository,
        SerializerInterface $serializer
    ) {
        $groups = [SerializeGroups::USER_INFO];
        $user = $userRepository->find($id);

        if ($user->getId() === $this->getUser()->getId() || in_array('ROLE_ADMIN', $this->getUser()->getRoles())) {
            array_push($groups, SerializeGroups::USER_PERSONAL);
        }

        return $this->__serializer($user, $groups, $serializer);
    }

    /**
     * Activate an user with the token given.
     *
     * @Route("/activate/{token}", name="user_activate", methods={"GET"})
     *
     * @SWG\Response(response="200", description="User activated, username returned.")
     * @SWG\Response(response="404", description="User not found")
     *
     * @SWG\Tag(name="Users")
     *
     * @param $token
     * @param UserRepository $userRepository
     * @param EntityManagerInterface $entityManager
     *
     * @return Response
     *
     * @throws NonUniqueResultException
     */
    public function activate(
        $token,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager
    ) {
        $userToActivate = $userRepository->findByToken($token);

        if ($userToActivate === null) {
            return $this->__serializeError('No user found with this data.', HttpCode::NOT_FOUND);
        }

        if ($userToActivate->getIsActive()) {
            return $this->__serializeError('This user is already active.', HttpCode::CONFLICT);
        }

        if ($this->checkIfTokenIsExpired($userToActivate->getTokenCreatedAt())) {
            return $this->__serializeError("Your token is expired.", HttpCode::FORBIDDEN);
        }

        $userToActivate->setToken(null);
        $userToActivate->setTokenCreatedAt(null);
        $userToActivate->setIsActive(true);

        $entityManager->persist($userToActivate);
        $entityManager->flush();

        return $this->__serializeSuccess(['message' => "User's account activated.", 'username' => $userToActivate->getUsername()]);
    }

    /**
     * Add an user, and send a generated password to the user
     *
     * @Route("/create", name="user_create", methods={"POST"})
     *
     * @SWG\Parameter(
     *     name="user",
     *     in="body",
     *     type="string",
     *     description="The account to create",
     *     required=true,
     *     @SWG\Schema(
     *         @SWG\Property(property="username", type="string", example="UniqueUsername"),
     *         @SWG\Property(property="email", type="string", example="valid@email.fr"),
     *         @SWG\Property(property="role", type="string", example="ROLE_USER,ROLE_ADMIN")
     *     )
     * )
     *
     * @SWG\Response(response="200", description="User created with username returned")
     * @SWG\Response(response="422", description="Error which can be corrected by user.")
     * @SWG\Response(response="500", description="Return if an error is arrived")
     *
     * @SWG\Tag(name="Users")
     * @Security(name="Bearer")
     *
     * @param Request $request
     * @param UserPasswordEncoderInterface $passwordEncoder
     * @param EntityManagerInterface $entityManager
     * @param ValidatorInterface $validator
     *
     * @return Response
     *
     * @throws Exception
     */
    public function create(
        Request $request,
        UserPasswordEncoderInterface $passwordEncoder,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator
    ) {
        $this->denyAccessUnlessGranted('add', $this->getUser());

        $userData = json_decode($request->getContent());

        if ($error = $this->__checkProperty($userData, ['username', 'email'])) {
            return $error;
        }

        if (property_exists($userData, 'roles')) {
            $roles = explode(',', $userData->roles);

            foreach ($roles as $role) {
                if (!in_array($role, $this->getParameter('app.api.available_role'))) {
                    return $this->__serializeError("Role '$role' is not a valid role.");
                }
            }
        }

        $user = new User();

        $user->setEmail($userData->email);
        $user->setUsername($userData->username);
        $user->setRoles(isset($roles) ? $roles : $user->getRoles());
        $plainPassword = $this->generateSecurePassword();

        $user->setPassword($passwordEncoder->encodePassword($user, $plainPassword));

        $errors = $validator->validate($user);

        if (count($errors)) {
            return $this->__serializeError($errors->get(0)->getMessage());
        }

        $entityManager->persist($user);
        $entityManager->flush();

        $mailerResult = $this->forward('App\Controller\MailerController::sendMail', [
            'from' => $this->getParameter('app.api.default_mail_from'),
            'to' => $user->getEmail(),
            'subject' => 'Your account have been created by and administrator.',
            'htmlTemplate' => 'emails/default/user/create.html.twig',
            'context' => [
                'username' => $user->getUsername(),
                'token' => $user->getToken(),
                'plainPassword' => $plainPassword
            ]
        ]);

        if ($mailerResult) {
            $data = [
                'status' => HttpCode::CREATED,
                'message' => "User created, and informed.",
                'token' => $user->getToken()
            ];
        } else {
            $entityManager->remove($user);
            $entityManager->flush();

            return $this->__serializeError("An error is come to us, please contact developer", HttpCode::INTERNAL_SERVER_ERROR);
        }

        return $this->__serializeSuccess($data, $data['status']);
    }

    /**
     * Ask a password update in case of lost the password. (Will send an email with a token)
     *
     * @Route("/forget/{email}", name="user_forget", methods={"PATCH"})
     *
     * @SWG\Response(response="200", description="Forget password's mail send to user")
     * @SWG\Response(response="422", description="Error which can be corrected by user.")
     * @SWG\Response(response="500",description="Internal error")
     *
     * @SWG\Tag(name="Users")
     *
     * @param $email
     * @param ValidatorInterface $validator
     * @param UserRepository $userRepository
     * @param EntityManagerInterface $entityManager
     *
     * @return Response
     * @throws Exception
     */
    public function forget(
        $email,
        ValidatorInterface $validator,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager
    ) {
        $emailConstraint = new Assert\Email();
        $emailConstraint->message = "Invalid email address";

        $errors = $validator->validate($email, $emailConstraint);

        if (count($errors)) {
            return $this->__serializeError($errors->get(0)->getMessage());
        }

        $userToResetPassword = $userRepository->findByEmail($email);

        if ($userToResetPassword === null) {
            return $this->__serializeError("We haven't found this user.", HttpCode::NOT_FOUND);
        }

        if (!$userToResetPassword->getIsActive()) {
            return $this->__serializeError("This user isn't active.");
        }

        if (!$this->checkIfTokenIsAskable($userToResetPassword->getTokenCreatedAt())) {
            return $this->__serializeError("You have asked a token there is less than " . $this->getParameter('app.api.token_askable_every') . " seconds.", HttpCode::FORBIDDEN);
        }

        $userToResetPassword->createToken(20);

        $entityManager->persist($userToResetPassword);
        $entityManager->flush();

        $mailerResult = $this->forward('App\Controller\MailerController::sendMail', [
            'from' => $this->getParameter('app.api.default_mail_from'),
            'to' => $userToResetPassword->getEmail(),
            'subject' => "You have lost your password, here is to change it.",
            'htmlTemplate' => 'emails/default/user/forget.html.twig',
            'context' => [
                'username' => $userToResetPassword->getUsername(),
                'token' => $userToResetPassword->getToken()
            ]
        ]);

        return $mailerResult
            ? $this->__serializeSuccess("User have receive and email for changing password", HttpCode::OK)
            : $this->__serializeError("Internal error. (Mail)", HttpCode::INTERNAL_SERVER_ERROR);
    }

    /**
     * Make a password update in case of losing the password.
     *
     * @Route("/forget/{token}", name="user_forget_change", methods={"PUT"})
     *
     * @SWG\Response(response="200", description="User's password successfully changed.")
     * @SWG\Response(response="500",description="Internal error")
     *
     * @SWG\Tag(name="Users")
     *
     * @param $token
     * @param Request $request
     * @param UserRepository $userRepository
     * @param EntityManagerInterface $entityManager
     * @param UserPasswordEncoderInterface $passwordEncoder
     *
     * @return Response
     *
     * @throws NonUniqueResultException
     */
    public function forgetChange(
        $token,
        Request $request,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        UserPasswordEncoderInterface $passwordEncoder
    ) {
        $userToChangePassword = $userRepository->findByToken($token);
        $userData = json_decode($request->getContent());

        if ($userToChangePassword === null) {
            return $this->__serializeError("We haven't found the user.", HttpCode::NOT_FOUND);
        }

        if ($this->checkIfTokenIsExpired($userToChangePassword->getTokenCreatedAt())) {
            return $this->__serializeError("Your token is expired.", HttpCode::FORBIDDEN);
        }

        if ($error = $this->__checkProperty($userData, 'password')) {
            return $error;
        }

        $userToChangePassword->setPassword($passwordEncoder->encodePassword($userToChangePassword, $userData->password));

        $userToChangePassword->setToken(null);
        $userToChangePassword->setTokenCreatedAt(null);

        $entityManager->persist($userToChangePassword);
        $entityManager->flush();

        $mailerResult = $this->forward('App\Controller\MailerController::sendMail', [
            'from' => $this->getParameter('app.api.default_mail_from'),
            'to' => $userToChangePassword->getEmail(),
            'subject' => "You have change your password",
            'htmlTemplate' => 'emails/default/user/changed.html.twig',
            'context' => [
                'username' => $userToChangePassword->getUsername(),
                'updated' => $userToChangePassword->getUpdated()->format('Y-m-d H:i:s')
            ]
        ]);

        return $mailerResult
            ? $this->__serializeSuccess("Password updated for " . $userToChangePassword->getUsername(), HttpCode::OK)
            : $this->__serializeError("Internal error. (Mail)", HttpCode::INTERNAL_SERVER_ERROR);
    }

    /**
     * Edit an user
     *
     * @Route("/edit/{id}", name="user_edit", methods={"PUT"})
     *
     * @SWG\Response(response="200", description="User edited successfully")
     * @SWG\Response(response="500",description="Internal error")
     *
     * @SWG\Tag(name="Users")
     * @Security(name="Bearer")
     *
     * @param $id
     * @param User $user
     * @param UserRepository $userRepository
     * @param Request $request
     * @param ValidatorInterface $validator
     * @param EntityManagerInterface $entityManager
     * @param UserPasswordEncoderInterface $passwordEncoder
     *
     * @return Response
     */
    public function edit(
        $id,
        User $user,
        UserRepository $userRepository,
        Request $request,
        ValidatorInterface $validator,
        EntityManagerInterface $entityManager,
        UserPasswordEncoderInterface $passwordEncoder
    ) {
        $userToEdit = $userRepository->find($id);
        $this->denyAccessUnlessGranted('edit', $userToEdit);

        $userData = json_decode($request->getContent());

        if (property_exists($userData, 'roles')) {
            $roles = explode(',', $userData->roles);

            foreach ($roles as $role) {
                if (!in_array($role, $this->getParameter('app.api.available_role'))) {
                    return $this->__serializeError("Role '$role' is not a valid role.");
                }
            }

            $userToEdit->setRoles($roles);
        }

        if (property_exists($userData, 'username')) {
            $userToEdit->setUsername($userData->username);
        }

        if (property_exists($userData, 'email')) {
            $userToEdit->setEmail($userData->email);
        }

        if (property_exists($userData, 'password')) {
            $userToEdit->setPassword($passwordEncoder->encodePassword($userToEdit, $userData->password));
        }

        $errors = $validator->validate($user);

        if (count($errors)) {
            return $this->__serializeError($errors->get(0)->getMessage());
        }

        $entityManager->persist($user);
        $entityManager->flush();

        $data = [
            'status' => HttpCode::OK,
            'message' => "User have been updated."
        ];

        return $this->__serializeSuccess($data, $data['status']);
    }

    /**
     * Remove an user
     *
     * @Route("/remove/{id}", name="user_remove", methods={"DELETE"})
     *
     * @SWG\Response(response="200", description="User removed successfully")
     * @SWG\Response(response="500", description="Return if an error is arrived")
     *
     * @SWG\Tag(name="Users")
     * @Security(name="Bearer")
     *
     *
     * @param $id
     * @param UserRepository $userRepository
     * @param EntityManagerInterface $entityManager
     *
     * @return Response
     */
    public function remove(
        $id,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager
    ) {
        $userToRemove = $userRepository->find($id);
        $this->denyAccessUnlessGranted('edit', $userToRemove);

        if ($userToRemove === null) {
            return $this->__serializeError('No user found with this data.', HttpCode::NOT_FOUND);
        }

        if (!$userToRemove->getIsActive() && $userToRemove->getDeleted() !== null) {
            return $this->__serializeError('This user is already deleted.', HttpCode::CONFLICT);
        }

        $userToRemove->setDeleted(new DateTime('now'));
        $userToRemove->setIsActive(false);

        $entityManager->flush();

        return $this->__serializeSuccess(['message' => "User's account deleted.", 'username' => $userToRemove->getUsername()]);
    }

    /* General function for UsersController */
    /**
     * @param int $length
     * @return string
     *
     * @throws Exception
     */
    private function generateSecurePassword($length = null) {

        $length =
            $length !== null
                ? $length
                : $this->getParameter('app.api.default_password_length');

        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789`-=~!@#$%^&*()_+,./<>?;:[]{}\|';

        $str = '';
        $max = strlen($chars) -1;

        for($i = 0; $i < $length; $i++) {
            $str .= $chars[random_int(0, $max)];
        }

        return $str;
    }

    /**
     * @param DateTime $tokenCreatedAt
     *
     * @return bool
     */
    private function checkIfTokenIsExpired(DateTime $tokenCreatedAt) {
        $secondInterval = $this->dateIntervalToSecond($tokenCreatedAt->diff(new DateTime('now')));

        return $secondInterval > $this->getParameter('app.api.token_expiration_moment');
    }

    /**
     * @param DateTime $tokenCreatedAt
     *
     * @return bool
     */
    private function checkIfTokenIsAskable(DateTime $tokenCreatedAt) {
        $secondInterval = $this->dateIntervalToSecond($tokenCreatedAt->diff(new DateTime('now')));

        return $secondInterval > $this->getParameter('app.api.token_askable_every');
    }

    /**
     * @param DateInterval $dateInterval
     *
     * @return float|int
     */
    private function dateIntervalToSecond(DateInterval $dateInterval) {
        return $dateInterval->days * 86400
            + $dateInterval->h * 3600
            + $dateInterval->i * 60
            + $dateInterval->s;
    }
}