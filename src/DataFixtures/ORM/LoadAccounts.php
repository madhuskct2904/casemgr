<?php

namespace App\DataFixtures\ORM;

use App\Entity\Accounts;
use App\Entity\AccountsData;
use App\Enum\AccountType;
use Doctrine\Common\DataFixtures\AbstractFixture;
use Doctrine\Common\DataFixtures\FixtureInterface;
use Doctrine\Persistence\ObjectManager;

class LoadAccounts extends AbstractFixture implements FixtureInterface
{
    public function load(ObjectManager $manager)
    {
        $connection = $manager->getConnection();
        $connection->exec("ALTER TABLE accounts AUTO_INCREMENT = 22;");
        $connection->exec("ALTER TABLE accounts_data AUTO_INCREMENT = 22;");

        $accountData = new AccountsData();

        $accountData->setContactName('CaseMGR');
        $accountData->setEmailAddress('case@casemgr.org');
        $accountData->setAccountUrl('casemgr.casemgr.io');
        $accountData->setBillingContactName('CaseMGR');
        $accountData->setBillingEmailAddress('case@casemgr.org');
        $accountData->setServiceCategory('Other');

        $manager->persist($accountData);
        $manager->flush();

        $account = new Accounts();

        $account->setOrganizationName('CaseMGR');
        $account->setSystemId('JMPTBC');
        $account->setAccountType(AccountType::DEFAULT);
        $account->setActivationDate(new \DateTime());
        $account->setStatus('active');
        $account->setData($accountData);

        $manager->persist($account);
        $manager->flush();
    }
}
