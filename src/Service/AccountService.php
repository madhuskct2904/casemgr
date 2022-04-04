<?php

namespace App\Service;

use App\Entity\Accounts;
use App\Entity\LinkedAccountHistory;
use App\Entity\Users;
use App\Enum\AccountType;
use App\Domain\Account\AccountServiceException;
use Doctrine\ORM\EntityManagerInterface;

class AccountService
{
    private $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function unlink(Accounts $accounts, Users $user)
    {
        if (!$accounts->getParentAccount()) {
            throw new AccountServiceException('Not a child account!');
        }

        $this->removeAccountFromReports($accounts);
        $this->replicateForms($accounts);
        $this->createHistoryEntry($accounts, $user);

        $accounts->setParentAccount(null);
        $accounts->setAccountType(AccountType::DEFAULT);
        $this->em->flush();
    }

    private function createHistoryEntry(Accounts $accounts, Users $user): void
    {
        $accountData = [
            'id'               => $accounts->getId(),
            'organizationName' => $accounts->getOrganizationName(),
            'systemId'         => $accounts->getSystemId(),
            'accountType'      => $accounts->getAccountType(),
            'activationDate'   => $accounts->getActivationDate(),
            'status'           => $accounts->getStatus(),
            'twilioPhone'      => $accounts->getTwilioPhone(),
            'twilioStatus'     => $accounts->isTwilioStatus(),
            'accountOwner'     => $accounts->getData()->getAccountOwner(),
            'main'             => $accounts->isMain(),
            'participantType'  => $accounts->getParticipantType(),
            'data'             => [
                'address1'            => $accounts->getData()->getAddress1(),
                'address2'            => $accounts->getData()->getAddress2(),
                'city'                => $accounts->getData()->getCity(),
                'state'               => $accounts->getData()->getState(),
                'country'             => $accounts->getData()->getCountry(),
                'zipCode'             => $accounts->getData()->getZipCode(),
                'contactName'         => $accounts->getData()->getContactName(),
                'emailAddress'        => $accounts->getData()->getEmailAddress(),
                'phoneNumber'         => $accounts->getData()->getPhoneNumber(),
                'accountUrl'          => $accounts->getData()->getAccountUrl(),
                'billingContactName'  => $accounts->getData()->getBillingContactName(),
                'billingEmailAddress' => $accounts->getData()->getBillingEmailAddress(),
                'billingPrimaryPhone' => $accounts->getData()->getBillingPrimaryPhone(),
                'serviceCategory'     => $accounts->getData()->getServiceCategory(),
                'accountOwner'        => $accounts->getData()->getAccountOwner(),
                'projectContact'      => $accounts->getData()->getProjectContact()
            ]
        ];

        $userData = [
            'id'    => $user->getId(),
            'email' => $user->getEmail(),
            'name'  => $user->getData()->getFullName()
        ];

        $historicalEntry = new LinkedAccountHistory();
        $historicalEntry->setAccount($accounts->getParentAccount());
        $historicalEntry->setData(json_encode($accountData));
        $historicalEntry->setUserData(json_encode($userData));
        $historicalEntry->setCreatedDate(new \DateTime());

        $this->em->persist($historicalEntry);
        $this->em->flush();
    }

    private function removeAccountFromReports(Accounts $accounts): void
    {
        $reports = $this->em->getRepository('App:Reports')->findByAccount($accounts->getParentAccount());

        if (!$reports) {
            return;
        }

        foreach ($reports as $report) {
            $reportAccounts = $report->getAccounts();

            if (empty($reportAccounts) || $reportAccounts == '[]') {
                continue;
            }

            $reportAccountsArr = json_decode($reportAccounts);

            if (json_last_error() !== JSON_ERROR_NONE) {
                continue;
            }

            foreach ($reportAccountsArr as $accountIdx => $accountData) {
                if ($accountData == $accounts->getId()) {
                    unset($reportAccountsArr[$accountIdx]);
                }
            }

            $report->setAccounts(json_encode($reportAccountsArr));
            $report->setModifiedDate(new \DateTime);
        }

        $this->em->flush();
    }

    private function replicateForms(Accounts $accounts): void
    {
        $forms = $accounts->getForms();

        foreach ($forms as $form) {
            $newForm = clone($form);

            $newForm->clearAccounts();
            $newForm->addAccount($accounts);

            $this->em->persist($newForm);
            $this->em->flush();

            $formsDataEntries = $this->em->getRepository('App:FormsData')->findByFormAndAccount($form, $accounts);

            foreach ($formsDataEntries as $formData) {
                $formData->setForm($newForm);
            }

            $this->em->flush();
            $this->em->getRepository('App:ReportsForms')->invalidateForm($form);
        }
    }
}
