<?php

namespace App\Handler\Modules\Handler;

use App\Entity\Accounts;
use App\Entity\Forms;
use App\Entity\Users;
use App\Entity\UsersData;
use Doctrine\ORM\EntityManager;
use LogicException;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class ModuleHandler
 *
 * @package App\Handler\Modules\Handler
 */
abstract class ModuleHandler
{
    private $elementId;
    private $doctrine;
    private $form;
    private $params = [];
    private $output = [];
    private $force = false;
    private $container;
    private $dataId;
    private $account;

    /**
     * @param ContainerInterface $container
     */
    public function setContainer(ContainerInterface $container): void
    {
        $this->container = $container;
    }

    /**
     * @return ContainerInterface
     */
    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    /**
     * @param EntityManager $doctrine
     */
    public function setDoctrine(EntityManager $doctrine): void
    {
        $this->doctrine = $doctrine;
    }

    /**
     * @return EntityManager
     */
    public function getDoctrine(): EntityManager
    {
        return $this->doctrine;
    }

    /**
     * @param $id
     */
    public function setElementId($id): void
    {
        $this->elementId = $id;
    }

    /**
     * @return mixed
     */
    public function getElementId()
    {
        return $this->elementId;
    }

    /**
     * @param Forms $form
     */
    public function setForm(Forms $form): void
    {
        $this->form = $form;
    }

    /**
     * @return Forms
     */
    public function getForm(): Forms
    {
        return $this->form;
    }

    /**
     * @return array
     */
    public function output(): array
    {
        return $this->output;
    }

    /**
     * @param array $params
     */
    public function set(array $params): void
    {
        $this->params = $params;
    }

    public function getParams()
    {
        return $this->params;
    }

    /**
     * @return mixed
     */
    public function getDataId()
    {
        return $this->dataId;
    }

    /**
     * @param $dataId
     */
    public function setDataId($dataId)
    {
        $this->dataId = $dataId;
    }

    public function getAccount(): ?Accounts
    {
        return $this->account;
    }

    /**
     * @param $account
     */
    public function setAccount(Accounts $account = null)
    {
        $this->account = $account;
    }

    /**
     * @return Params
     */
    public function params(): Params
    {
        $params = $this->params;
        $map = json_decode($this->getForm()->getColumnsMap(), true);
        $flatMap = [];
        $data = [];

        if (is_array($map)) {
            foreach ($map as $row) {
                $flatMap[$row['name']] = $row['value'];
            }
        }

        foreach ($flatMap as $name => $value) {
            $key = array_search($value, array_column($params, 'name'));
            if ($key !== false) {
                $data[$name] = $params[$key]['value'];
            }
        }

        return new Params($data);
    }

    /**
     * @return array
     */
    public function map(): array
    {
        $map = json_decode($this->getForm()->getColumnsMap(), true);
        $flatMap = [];

        if (is_array($map)) {
            foreach ($map as $row) {
                $flatMap[$row['name']] = $row['value'];
            }
        }

        return $flatMap;
    }

    /**
     * @param string $name
     * @param mixed $value
     */
    public function response(string $name, $value): void
    {
        $this->output[$name] = $value;
    }


    /**
     * @param bool $force
     */
    public function setForce(bool $force): void
    {
        $this->force = $force;
    }

    /**
     * @return bool
     */
    public function force(): bool
    {
        return $this->force;
    }

    public function before()
    {
    }

    public function after()
    {
    }

    public function systemValues(): ?array
    {
        return null;
    }

    public function validate(): ?array
    {
        return null;
    }

    public function getDateFormat(): string
    {
        $user = $this->getLoggedUser();

        if (null === $user) {
            return 'm/d/Y';
        }

        $data     = $user->getData();
        $timezone = $data->getTimeZone();
        $config   = $this->getContainer()
            ->getParameter('timezones')
        ;

        return $timezone ? $config[$timezone]['phpDateFormat'] : 'm/d/Y';
    }

    private function getLoggedUser(): ?Users
    {
        # This should use DI instead of pulling from the service container directly, but
        # I can't figure out how to make it work.  There are classes that implement this one
        # with their own implementations of the constructor.
        $userService = $this->container->get('App\Service\UserService');
        return $userService->getLoggedUser();
    }
}
