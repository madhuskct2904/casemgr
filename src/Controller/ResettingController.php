<?php

namespace App\Controller;

use App\Exception\ExceptionMessage;
use App\Utils\Helper;
use DateTime;
use Doctrine\Common\Annotations\Annotation\IgnoreAnnotation;
use Exception;
use Nucleos\UserBundle\Util\TokenGenerator;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Security\Core\Encoder\EncoderFactoryInterface;
use function Sentry\captureException;

/**
 * Class ResettingController
 *
 * @IgnoreAnnotation("api")
 * @IgnoreAnnotation("apiGroup")
 * @IgnoreAnnotation("apiHeader")
 * @IgnoreAnnotation("apiParam")
 * @IgnoreAnnotation("apiSuccess")
 * @IgnoreAnnotation("apiError")
 *
 * @package App\Controller
 */
class ResettingController extends Controller
{

    private MailerInterface $mailer;

    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer;
    }

    /**
     * @param Request $request
     *
     * @return JsonResponse
     *
     * @api {post} /resetting/email Reset Password Email
     * @apiGroup Users
     *
     * @apiParam {String} email User Email
     *
     * @apiSuccess {String} message Success Message
     *
     * @apiError message Error Message
     *
     */
    public function emailAction(Request $request)
    {
        if ($request->isMethod('POST')) {
            $email = $this->getRequest()->param('email');

            $user = $this->getDoctrine()->getRepository('App:Users')->findOneBy([
                'email' => $email,
                'type'  => 'user'
            ]);

            if ($user === null) {
                // message to return success rather than error to mask that the user doesnt exist
                return $this->getResponse()->success([
                    'message' => 'Password reset email sent.'
                ]);
            }

            $em = $this->getDoctrine()->getManager();
            $tokenGenerator = new TokenGenerator();

            $user->setConfirmationToken($tokenGenerator->generateToken());
            $user->setPasswordRequestedAt(new DateTime());

            $em->flush();

            try {
                $topic = 'Password Reset | CaseMGR';
                $message = (new TemplatedEmail())
                    ->subject($topic)
                    ->from($this->getParameter('mailer_from'))
                    ->to($user->getEmail())
                    ->htmlTemplate('Emails/resetting.html.twig')
                    ->textTemplate('Emails/resetting.txt.twig')
                    ->context([
                        'title'   => $topic,
                        'user'    => $user,
                        'mainUrl' => $this->getParameter('frontend_domain')
                    ]);

                $this->mailer->send($message);

                return $this->getResponse()->success([
                    'message' => 'Password reset email sent.'
                ]);
            } catch (Exception $e) {
                captureException($e); // capture exception by Sentry

                return $this->getResponse()->error(ExceptionMessage::DEFAULT);
            }
        }

        return $this->getResponse()->error(ExceptionMessage::NOT_ALLOWED_METHOD);
    }

    /**
     * @param Request $request
     * @return JsonResponse
     * @api {post} /resetting/change Reset Password
     * @apiGroup Users
     *
     * @apiParam {String} token Confirmation Token
     * @apiParam {String} password User new password
     *
     * @apiSuccess {String} message Success Message
     *
     * @apiError message Error Message
     *
     */
    public function changeAction(Request $request, EncoderFactoryInterface $encoderFactory)
    {
        if ($request->isMethod('POST')) {
            $token = $this->getRequest()->param('token');
            $password = $this->getRequest()->param('password');

            if (strlen($token) < 40) {
                return $this->getResponse()->error(ExceptionMessage::INVALID_CONFIRMATION_TOKEN);
            }

            $user = $this->getDoctrine()->getRepository('App:Users')->findOneBy([
                'confirmationToken' => $token,
                'type'              => 'user'
            ]);

            if (!$user || $user->getPasswordRequestedAt() === null) {
                return $this->getResponse()->error(ExceptionMessage::INVALID_CONFIRMATION_TOKEN);
            }

            $encoder = $encoderFactory->getEncoder($user);
            if ($oldPass = $this->getRequest()->param('oldPass')) {
                if (!$encoder->isPasswordValid($user->getPassword(), $oldPass, $user->getSalt())) {
                    return $this->getResponse()->error(ExceptionMessage::INVALID_OLD_PASSWORD);
                }
            }

            $old_password = $user->getPassword();

            if ($error = Helper::validatePassword($password, $user->getData()->getFirstName(), $user->getData()->getLastName(), $old_password, $encoder)) {
                return $this->getResponse()->error($error);
            }

            $now = new DateTime();
            $requested = clone $user->getPasswordRequestedAt();

            if ($requested->modify('+2 hours') < $now) {
                return $this->getResponse()->error(ExceptionMessage::EXPIRED_CONFIRMATION_TOKEN);
            }

            $em = $this->getDoctrine()->getManager();

            $user->setConfirmationToken(null);
            $user->setPasswordRequestedAt(null);
            $user->setPlainPassword($password);
            $user->setPasswordSetAt(new DateTime());

            $em->flush();

            return $this->getResponse()->success([
                'message' => 'Password changed.'
            ]);
        }

        return $this->getResponse()->error(ExceptionMessage::NOT_ALLOWED_METHOD);
    }
}
