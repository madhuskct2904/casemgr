<?php

namespace App\Controller;

use App\Entity\Users;
use App\Enum\EmailMessageStatus;
use App\Exception\ExceptionMessage;
use App\Service\EmailHelperService;
use App\Service\EmailHistoryService;
use Exception;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use function Sentry\captureException;

class EmailsController extends Controller
{
    protected $emailHelperService;

    public function newEmailOptionsAction(EmailHelperService $emailHelperService)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if (!$this->can(Users::ACCESS_LEVELS['SYSTEM_ADMINISTRATOR'])) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        $options = $emailHelperService->getNewEmailOptions();

        return $this->getResponse()->success($options);
    }

    public function createAction(
        EmailHistoryService $emailHistoryService,
        ValidatorInterface $validator
    )
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if (!$this->can(Users::ACCESS_LEVELS['SYSTEM_ADMINISTRATOR'])) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        $emailData = $this->getRequest()->param('email');
        $sendNow = $this->getRequest()->param('send', false);

        $emailDataConstraints = new Assert\Collection([
            'template_id'       => new Assert\Optional(),
            'subject'           => new Assert\NotBlank(),
            'header'            => new Assert\NotBlank(),
            'body'              => new Assert\NotBlank(),
            'sender'            => new Assert\Choice(['choices' => array_keys($this->getParameter('email_senders'))]),
            'recipients_group'  => new Assert\NotBlank(),
            'recipients_option' => new Assert\Optional(),
            'recipients'        => new Assert\All([new Assert\Email()]),
            'status'            => new Assert\EqualTo(EmailMessageStatus::DRAFTING),
            'id'                => new Assert\IsNull()
        ]);

        $errors = $validator->validate($emailData, $emailDataConstraints);

        if (count($errors)) {
            $messages = [];

            foreach ($errors as $error) {
                $messages[$error->getPropertyPath()][] = $error->getMessage();
            }

            return $this->getResponse()->error(json_encode($messages));
        }

        try {
            $emailHistoryService->createEntry($emailData, $this->user());
        } catch (Exception $e) {
            captureException($e); // capture exception by Sentry

            return $this->getResponse()->error(ExceptionMessage::DEFAULT);
        }

        if ($sendNow) {
            $emailHistoryService->send();
        }

        $emailMessageId = $emailHistoryService->getEmailMessage()->getId();

        $emailData = $this->getDoctrine()->getRepository('App:EmailMessage')->findWithRecipientsAsArray($emailMessageId);

        return $this->getResponse()->success(['email_message' => $emailData]);
    }

    public function getAction($emailMessageId)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if (!$this->can(Users::ACCESS_LEVELS['SYSTEM_ADMINISTRATOR'])) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        $emailData = $this->getDoctrine()->getRepository('App:EmailMessage')->findWithRecipientsAsArray($emailMessageId);

        return $this->getResponse()->success(['email_message' => $emailData]);
    }

    public function updateAction(
        EmailHistoryService $emailHistoryService,
        ValidatorInterface $validator
    )
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if (!$this->can(Users::ACCESS_LEVELS['SYSTEM_ADMINISTRATOR'])) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        $emailData = $this->getRequest()->param('email');
        $sendNow = $this->getRequest()->param('send', false);

        $emailDataConstraints = new Assert\Collection([
            'template_id'       => new Assert\Optional(),
            'subject'           => new Assert\NotBlank(),
            'header'            => new Assert\NotBlank(),
            'body'              => new Assert\NotBlank(),
            'sender'            => new Assert\Choice(['choices' => array_keys($this->getParameter('email_senders'))]),
            'recipients_group'  => new Assert\NotBlank(),
            'recipients_option' => new Assert\Optional(),
            'recipients'        => new Assert\All([new Assert\Email()]),
            'status'            => new Assert\EqualTo(EmailMessageStatus::DRAFTING),
            'id'                => new Assert\Type('integer'),
            'failedRecipients'  => new Assert\Optional()
        ]);

        $errors = $validator->validate($emailData, $emailDataConstraints);

        if (count($errors)) {
            $messages = [];

            foreach ($errors as $error) {
                $messages[$error->getPropertyPath()][] = $error->getMessage();
            }

            return $this->getResponse()->error(json_encode($messages));
        }

        try {
            $emailHistoryService->updateEntry($emailData, $this->user());
        } catch (Exception $e) {
            captureException($e); // capture exception by Sentry

            return $this->getResponse()->error(ExceptionMessage::DEFAULT);
        }

        if ($sendNow) {
            $emailHistoryService->send();
        }

        $emailMessageId = $emailHistoryService->getEmailMessage()->getId();

        $emailData = $this->getDoctrine()->getRepository('App:EmailMessage')->findWithRecipientsAsArray($emailMessageId);

        return $this->getResponse()->success(['email_message' => $emailData]);
    }
}
