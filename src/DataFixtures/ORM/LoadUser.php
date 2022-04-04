<?php

namespace App\DataFixtures\ORM;

use App\Entity\Accounts;
use App\Entity\Credentials;
use App\Entity\Users;
use App\Entity\UsersData;
use App\Enum\ParticipantStatus;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Persistence\ObjectManager;


class LoadUser extends AbstractFixture implements FixtureInterface
{
    public function load(ObjectManager $manager)
    {
        $account = $manager->getRepository(Accounts::class)->find(22);

        $userAdmin = new Users();
        $userAdmin->setUsername('administrator');
        $userAdmin->setPlainPassword('secret');
        $userAdmin->setEmail('admin@example.com');
        $userAdmin->setEnabled(true);
        $userAdmin->setRoles(['a:1:{i:0;s:28:"A:1:{I:0;S:10:"ROLE_ADMIN";}";}']);
        $userAdmin->setTypeAsUser();
        $userAdmin->setEnabled(true);
        $userAdmin->setDefaultAccount('casemgr.casemgr.io');
        $userAdmin->addAccount($account);

        $manager->persist($userAdmin);
        $manager->flush();

        $userData = new UsersData();
        $userData->setUser($userAdmin);
        $userData->setGender('male');
        $userData->setSystemId(1);
        $userData->setCaseManager('Example Manager');
        $userData->setStatusLabel('pending');
        $userData->setStatus(ParticipantStatus::ACTIVE);
        $userData->setPhoneNumber(123456);
        $userData->setDateBirth(new \DateTime());
        $userData->setFirstName('System');
        $userData->setLastName('administrator');

        $manager->persist($userData);
        $manager->flush();

        $credential = new Credentials();

        $credential
            ->setAccount($account)
            ->setUser($userAdmin)
            ->setEnabled(true)
            ->setAccess(6);

        $manager->persist($credential);
        $manager->flush();
    }
}
