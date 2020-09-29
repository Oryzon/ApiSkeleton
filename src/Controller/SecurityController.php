<?php

namespace App\Controller;

use App\Entity\HttpCode;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Swagger\Annotations as SWG;

/**
 * @Route("/auth")
 */
class SecurityController extends ApiController {
    /**
     * Create a new user
     *
     * @Route("/register", name="register", methods={"POST"})
     *
     * @SWG\Parameter(
     *     name="user",
     *     in="body",
     *     type="string",
     *     description="The account to register",
     *     required=true,
     *     @SWG\Schema(
     *         @SWG\Property(property="username", type="string", example="UniqueUsername"),
     *         @SWG\Property(property="email", type="string", example="valid@email.fr"),
     *         @SWG\Property(property="password", type="string", example="s3cUr3P4$$w0rd")
     *     )
     * )
     *
     * @SWG\Response(response="201", description="Return of the state of the registration.")
     * @SWG\Response(response="422", description="Error which can be corrected by user.")
     * @SWG\Response(response="500", description="Internal error")
     *
     * @SWG\Tag(name="Users")
     *
     * @param Request $request
     * @param UserPasswordEncoderInterface $passwordEncoder
     * @param EntityManagerInterface $entityManager
     * @param ValidatorInterface $validator
     *
     * @return Response
     */
    public function register(
        Request $request,
        UserPasswordEncoderInterface $passwordEncoder,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator
    ) {
        $values = json_decode($request->getContent());

        if (!$error = $this->__checkProperty($values, ['username', 'email', 'password'])) {
            return $error;
        }

        $user = new User();

        $user->setEmail($values->email);
        $user->setUsername($values->username);
        $user->setPassword($passwordEncoder->encodePassword($user, $values->password));
        $user->setRoles($user->getRoles());

        if ($this->getParameter('app.api.account_activation_needed')) {
            $user->setIsActive(false);
            $user->createToken();
        }

        $errors = $validator->validate($user);

        if (count($errors)) {
            return $this->__serializeError($errors->get(0)->getMessage());
        }

        $entityManager->persist($user);
        $entityManager->flush();

        if ($this->getParameter('app.api.account_activation_needed')) {
            $mailerResult = $this->forward('App\Controller\MailerController::sendMail', [
                'from' => $this->getParameter('app.api.default_mail_from'),
                'to' => $user->getEmail(),
                'subject' => 'Your account have been created',
                'htmlTemplate' => 'emails/default/user/activate.html.twig',
                'context' => [
                    'username' => $user->getUsername(),
                    'token' => $user->getToken()
                ]
            ]);

            if ($mailerResult) {
                $data = [
                    'status' => HttpCode::CREATED,
                    'message' => "User created, need to activate account. (Check your email)",
                    'token' => $user->getToken()
                ];
            } else {
                $entityManager->remove($user);
                $entityManager->flush();

                return $this->__serializeError("An error is come to us, please contact developer", HttpCode::INTERNAL_SERVER_ERROR);
            }
        } else {
            $data = [
                'status' => HttpCode::CREATED,
                'message' => "User created."
            ];
        }

        return $this->__serializeSuccess($data, $data['status']);
    }

    /**
     * Login an user
     *
     * @Route("/login_check", name="login", methods={"POST"})
     *
     * @SWG\Parameter(
     *     name="user",
     *     in="body",
     *     type="string",
     *     description="The account to login",
     *     required=true,
     *     @SWG\Schema(
     *         @SWG\Property(property="username", type="string", example="UniqueUsername"),
     *         @SWG\Property(property="password", type="string", example="s3cUr3P4$$w0rd")
     *     )
     * )
     *
     * @SWG\Response(response="200", description="User successfully login, token returned.")
     * @SWG\Response(response="500", description="Internal error")
     *
     * @SWG\Tag(name="Users")
     *
     * @return void
     */
    public function login() {
        /**
         * We use JWT Authentification, this function is only for having documentation.
         * If you want add some user data to payload see @App\EventListener\JWTCreatedListener
         */
        return;
    }

    /**
     * Refresh a token
     *
     * @Route("/token/refresh", name="refresh", methods={"POST"})
     *
     * @SWG\Response(response="200", description="Token refreshed")
     * @SWG\Response(response="500", description="Internal error")
     *
     * @SWG\Tag(name="Users")
     *
     * @return void
     */
    public function refresh() {
        /**
         * We use JWT Refresh Token Bundle, this function is only for having documentation.
         */
        return;
    }
}
