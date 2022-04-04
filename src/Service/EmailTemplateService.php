<?php

namespace App\Service;

use App\Entity\EmailTemplate;
use App\Entity\Users;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Exception;

class EmailTemplateService
{
    protected $em;
    protected $template;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function prepareIndex(array $templates)
    {
        $templatesIndex = [];

        foreach ($templates as $template) {
            $templatesIndex[] = [
                'id'          => $template->getId(),
                'name'        => $template->getName(),
                'subject'     => $template->getSubject(),
                'header'      => $template->getHeader(),
                'sender'      => $template->getSender(),
                'body'        => $template->getBody(),
                'created_by'  => $template->getCreator() ? $template->getCreator()->getData()->getFullName() : '',
                'created_at'  => $template->getCreatedAt(),
                'modified_by' => $template->getModifiedBy() ? $template->getModifiedBy()->getData()->getFullName() : '',
                'modified_at' => $template->getModifiedAt()
            ];
        }

        return $templatesIndex;
    }

    public function create(array $templateData, Users $user)
    {
        $template = new EmailTemplate();
        $this->setTemplateData($templateData, $user, $template);

        $this->em->persist($template);
        $this->em->flush();

        $this->template = $template;
    }

    public function update(array $templateData, Users $user)
    {
        $template = $this->em->getRepository('App:EmailTemplate')->find($templateData['id']);

        $this->setTemplateData($templateData, $user, $template);

        $template->setModifiedAt(new \DateTime());
        $template->setModifiedBy($user);

        $this->em->flush();
        $this->em->refresh($template);

        $this->template = $template;
    }

    public function getTemplate()
    {
        return $this->template;
    }

    public function duplicateTemplate(int $templateId, string $name, Users $user)
    {
        $template = $this->em->getRepository('App:EmailTemplate')->find($templateId);

        if (!$template) {
            throw new Exception('Wrong template!');
        }

        $newTemplate = clone $template;
        $newTemplate->setName($name);
        $newTemplate->setCreator($user);
        $newTemplate->setCreatedAt(new \DateTime());
        $newTemplate->setModifiedAt(null);
        $newTemplate->setModifiedBy(null);

        $this->em->persist($newTemplate);
        $this->em->flush();

        $this->template = $newTemplate;
    }

    public function delete(int $templateId)
    {
        $template  = $this->em->getRepository('App:EmailTemplate')->find($templateId);

        if (!$template) {
            throw new Exception('Wrong template!');
        }

        $this->em->remove($template);
        $this->em->flush();
    }

    protected function setTemplateData(array $templateData, Users $user, EmailTemplate &$template): void
    {
        $template->setName($templateData['name']);
        $template->setSubject($templateData['subject']);
        $template->setHeader($templateData['header']);
        $template->setSender($templateData['sender']);
        $template->setBody($templateData['body']);
        $template->setCreator($user);
        $template->setCreatedAt(new \DateTime());
    }
}
