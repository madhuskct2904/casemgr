<?php

namespace App\Controller;

use App\Entity\EmailTemplate;
use App\Entity\Users;
use App\Exception\ExceptionMessage;
use App\Service\EmailTemplateService;
use Exception;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use function Sentry\captureException;

class EmailsTemplatesController extends Controller
{
    public function indexAction(EmailTemplateService $emailTemplateService)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if (!$this->can(Users::ACCESS_LEVELS['SYSTEM_ADMINISTRATOR'])) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        $templates = $this->getDoctrine()->getRepository('App:EmailTemplate')->findAll();
        $templatesIndex = $emailTemplateService->prepareIndex($templates);

        return $this->getResponse()->success(['templates' => $templatesIndex]);
    }

    public function createAction(
        EmailTemplateService $templateService,
        ValidatorInterface $validator
    )
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if (!$this->can(Users::ACCESS_LEVELS['SYSTEM_ADMINISTRATOR'])) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        $templateData = $this->getRequest()->param('template');

        $emailTemplateDataConstraints = new Assert\Collection([
            'name'    => new Assert\NotBlank(),
            'subject' => new Assert\NotBlank(),
            'header'  => new Assert\NotBlank(),
            'sender'  => new Assert\Choice(['choices' => array_keys($this->getParameter('email_senders'))]),
            'body'    => new Assert\NotBlank(),
            'id'      => new Assert\IsNull()
        ]);

        $errors = $validator->validate($templateData, $emailTemplateDataConstraints);

        if (count($errors)) {
            $messages = [];

            foreach ($errors as $error) {
                $messages[$error->getPropertyPath()][] = $error->getMessage();
            }

            return $this->getResponse()->error(json_encode($messages));
        }

        try {
            $templateService->create($templateData, $this->user());
            $template = $templateService->getTemplate();
        } catch (Exception $e) {
            captureException($e); // capture exception by Sentry

            return $this->getResponse()->error(ExceptionMessage::DEFAULT);
        }

        $templateArr = $this->getDoctrine()->getManager()->getRepository('App:EmailTemplate')->findOneAsArray($template->getId());

        return $this->getResponse()->success(['template' => $templateArr]);
    }

    public function getAction($templateId)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if (!$this->can(Users::ACCESS_LEVELS['SYSTEM_ADMINISTRATOR'])) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        $template = $this->getDoctrine()->getManager()->getRepository('App:EmailTemplate')->findOneAsArray($templateId);

        return $this->getResponse()->success(['template' => $template]);
    }

    public function updateAction(
        EmailTemplateService $templateService,
        ValidatorInterface $validator
    )
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if (!$this->can(Users::ACCESS_LEVELS['SYSTEM_ADMINISTRATOR'])) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        $templateData = $this->getRequest()->param('template');

        $emailTemplateDataConstraints = new Assert\Collection([
            'name'    => new Assert\NotBlank(),
            'subject' => new Assert\NotBlank(),
            'header'  => new Assert\NotBlank(),
            'sender'  => new Assert\Choice(['choices' => array_keys($this->getParameter('email_senders'))]),
            'body'    => new Assert\NotBlank(),
            'id'      => new Assert\Type('integer')
        ]);

        $errors = $validator->validate($templateData, $emailTemplateDataConstraints);

        if (count($errors)) {
            $messages = [];

            foreach ($errors as $error) {
                $messages[$error->getPropertyPath()][] = $error->getMessage();
            }

            return $this->getResponse()->error(json_encode($messages));
        }

        try {
            $templateService->update($templateData, $this->user());
            $template = $templateService->getTemplate();
        } catch (Exception $e) {
            captureException($e); // capture exception by Sentry

            return $this->getResponse()->error(ExceptionMessage::DEFAULT);
        }

        $templateArr = $this->getDoctrine()->getManager()->getRepository('App:EmailTemplate')->findOneAsArray($template->getId());

        return $this->getResponse()->success(['template' => $templateArr]);
    }

    public function duplicateAction(EmailTemplateService $templateService)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if (!$this->can(Users::ACCESS_LEVELS['SYSTEM_ADMINISTRATOR'])) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        $templateId = $this->getRequest()->param('template_id');
        $name = $this->getRequest()->param('name');

        try {
            $templatesService->duplicateTemplate($templateId, $name, $this->user());
            $template = $templatesService->getTemplate();
        } catch (Exception $e) {
            captureException($e); // capture exception by Sentry

            return $this->getResponse()->error(ExceptionMessage::DEFAULT);
        }

        $templateArr = $this->getDoctrine()->getManager()->getRepository('App:EmailTemplate')->findOneAsArray($template->getId());

        return $this->getResponse()->success(['template'=>$templateArr]);
    }

    public function deleteAction(EmailTemplateService $templateService)
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if (!$this->can(Users::ACCESS_LEVELS['SYSTEM_ADMINISTRATOR'])) {
            return $this->getResponse()->error(ExceptionMessage::NO_ACCESS, 401);
        }

        $templateId = $this->getRequest()->param('template_id');

        try {
            $templateService->delete($templateId);
        } catch (Exception $e) {
            captureException($e); // capture exception by Sentry

            return $this->getResponse()->error(ExceptionMessage::DEFAULT);
        }

        return $this->getResponse()->success();
    }
}
