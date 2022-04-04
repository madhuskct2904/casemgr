<?php

namespace App\Handler\Modules;

use App\Entity\FormsData;
use App\Entity\Users;
use App\Handler\Modules\Handler\Params;
use App\Service\UserService;
use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class FormValuesHandler
{
    protected $form;
    protected FormsData $formData;
    protected EntityManagerInterface $em;
    protected ContainerInterface $container;
    protected UserService $userService;

    public function __construct(
        EntityManagerInterface $em,
        ContainerInterface $container,
        UserService $userService
    ) {
        $this->em        = $em;
        $this->container = $container;
        $this->userService = $userService;
    }

    public function setFormData(FormsData $formsData)
    {
        $this->formData = $formsData;
    }

    public function getFormData()
    {
        return $this->formData;
    }

    public function columnsMapValues(array $values): Params
    {
        $form = $this->formData->getForm();
        $map = json_decode($form->getColumnsMap(), true);
        $flatMap = [];
        $data = [];

        if (is_array($map)) {
            foreach ($map as $row) {
                $flatMap[$row['name']] = $row['value'];
            }
        }

        foreach ($flatMap as $name => $value) {
            $key = array_search($value, array_column($values, 'name'));
            if ($key !== false) {
                $data[$name] = $values[$key]['value'];
            }
        }

        return new Params($data);
    }

    public function getDateFormat(): string
    {
        $user = $this->getLoggedUser();

        if (null === $user) {
            return 'm/d/Y';
        }

        $data     = $user->getData();
        $timezone = $data->getTimeZone();
        $config   = $this->container->getParameter('timezones');

        return $timezone ? $config[$timezone]['phpDateFormat'] : 'm/d/Y';
    }

    private function getLoggedUser(): ?Users
    {
        return $this->userService->getLoggedUser();
    }
}
