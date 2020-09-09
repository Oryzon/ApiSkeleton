<?php

namespace App\Controller;

use App\Entity\HttpCode;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\IsGranted;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Nelmio\ApiDocBundle\Annotation\Security;
use Swagger\Annotations as SWG;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Annotation\Route;

class MailerController extends ApiController {

    /**
     * @var MailerInterface
     */
    private $mailer;

    /**
     * MailerController constructor.
     *
     * @param MailerInterface $mailer
     */
    public function __construct(MailerInterface $mailer) {
        $this->mailer = $mailer;
    }

    /**
     * Send an email
     *
     * @Route("/send-email/{from}/{to}", name="send_email", methods={"POST"})
     *
     * @SWG\Response(response="200", description="Mail have been send.")
     *
     * @SWG\Parameter(
     *     name="htmlTemplate",
     *     in="body",
     *     description="HTML template used for the e-mail",
     *     required=true,
     *     @SWG\Schema(
     *         @SWG\Property(property="project", type="string"),
     *         @SWG\Property(property="group", type="string"),
     *         @SWG\Property(property="template", type="string")
     *     )
     * )
     *
     * @SWG\Parameter(
     *     name="email",
     *     in="body",
     *     description="The content of the email",
     *     required=true,
     *     @SWG\Schema(
     *         @SWG\Property(property="subject", type="string"),
     *         @SWG\Property(property="context", type="object")
     *     )
     * )
     *
     * @SWG\Tag(name="General")
     * @Security(name="Bearer")
     * @IsGranted("ROLE_ADMIN", message="Access Denied : You have not enough power for this.")
     *
     * @param $from
     * @param $to
     * @param Request $request
     * @param Filesystem $filesystem
     *
     * @return Response
     */
    public function apiSendMail($from, $to, Request $request, Filesystem $filesystem) {
        $json = json_decode($request->getContent());

        if (!property_exists($json, 'htmlTemplate')) {
            return $this->__serializeError("HTML Template is missing.", HttpCode::UNPROCESSABLE_ENTITY);
        }

        $htmlTemplate = $json->htmlTemplate;

        if (!property_exists($htmlTemplate, 'project')
            || !property_exists($htmlTemplate, 'group')
            || !property_exists($htmlTemplate, 'template')) {
            return $this->__serializeError("HTML Template isn't complete, please check.", HttpCode::UNPROCESSABLE_ENTITY);
        }

        if (!property_exists($json, 'email')) {
            return $this->__serializeError("Email is missing", HttpCode::UNPROCESSABLE_ENTITY);
        }

        $emailContent = $json->email;

        if (!property_exists($emailContent, "subject")) {
            return $this->__serializeError("Email subject is missing", HttpCode::UNPROCESSABLE_ENTITY);
        }

        $base = rtrim($_SERVER['DOCUMENT_ROOT'], 'public');
        $templateEmails = $base . 'templates' . DIRECTORY_SEPARATOR . 'emails' . DIRECTORY_SEPARATOR;

        // We check is the asked template exist.
        if (!$filesystem->exists($templateEmails . $htmlTemplate->project)) {
            return $this->__serializeError("HTML Template Project doesn't exist, please verify.", HttpCode::UNPROCESSABLE_ENTITY);
        }

        if (!$filesystem->exists($templateEmails . $htmlTemplate->project . DIRECTORY_SEPARATOR . $htmlTemplate->group)) {
            return $this->__serializeError("HTML Template Group doesn't exist, please verify.", HttpCode::UNPROCESSABLE_ENTITY);
        }

        if (!$filesystem->exists($templateEmails . $htmlTemplate->project . DIRECTORY_SEPARATOR . $htmlTemplate->group . DIRECTORY_SEPARATOR . $htmlTemplate->template)) {
            return $this->__serializeError("HTML Template Template doesn't exist, please verify", HttpCode::UNPROCESSABLE_ENTITY);
        }

        $htmlTemplateString =   'emails' .
                                DIRECTORY_SEPARATOR .
                                $htmlTemplate->project .
                                DIRECTORY_SEPARATOR .
                                $htmlTemplate->group .
                                DIRECTORY_SEPARATOR .
                                $htmlTemplate->template;

        if (property_exists($emailContent, 'context')) {
            $context = json_decode(json_encode($emailContent->context), true);
        } else {
            $context = [];
        }

        if ($this->sendMail($from, $to, $emailContent->subject, $htmlTemplateString, $context)) {
            return $this->__serializeSuccess([
                'status' => HttpCode::CREATED,
                'message' => 'Mail have been send.'
            ]);
        } else {
            return $this->__serializeError('An error is arrived, please contact developer.', HttpCode::INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @param string $from
     * @param string $to
     * @param string $subject
     * @param string $htmlTemplate
     * @param array $context
     *
     * @return bool
     */
    public function sendMail(
        string $from,
        string $to,
        string $subject,
        string $htmlTemplate,
        array $context
    ) {
        $email = (new TemplatedEmail())
            ->from($from)
            ->to(new Address($to))
            ->subject($subject)
            ->htmlTemplate($htmlTemplate);

        if (count($context)) {
            $email->context($context);
        }

        try {
            $this->mailer->send($email);
            return true;
        } catch (TransportExceptionInterface $e) {
            return false;
        }
    }
}
