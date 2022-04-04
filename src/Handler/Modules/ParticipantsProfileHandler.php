<?php

namespace App\Handler\Modules;

use App\Entity\Accounts;
use App\Entity\Users;
use App\Entity\UsersData;
use App\Entity\UsersSettings;
use App\Enum\AccountType;
use App\Enum\ParticipantStatus;
use App\Enum\ParticipantType;
use App\Handler\Modules\Handler\ModuleHandler;
use App\Utils\Helper;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Class ParticipantsProfileHandler
 *
 * @package App\Handler\Modules
 */
class ParticipantsProfileHandler extends ModuleHandler
{
    private $system_id;

    public function __construct(EntityManagerInterface $doctrine) {
        $this->setDoctrine($doctrine);
    }

    public function before($access = 0, Accounts $creatorAccount = null)
    {
        $em = $this->getDoctrine();
        $data = $this->params();

        if ($this->getElementId() === null) {
            if ($access < Users::ACCESS_LEVELS['CASE_MANAGER']) {
                throw new \Exception('No access.');
            }

            // Create new user account
            $participantUser = new Users();

            $username = strtolower(Helper::generateCode(4)) . uniqid();
            $email = sprintf('%s@casemgr.org', $username);

            $participantUser->setTypeAsParticipant();
            $participantUser->setUsername($username);
            $participantUser->setUsernameCanonical($username);
            $participantUser->setSalt(null);
            $participantUser->setEmail($email);
            $participantUser->setEmailCanonical($email);
            $participantUser->setEnabled('0');
            $participantUser->setPlainPassword(Helper::generateCode(8, 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz123456789'));
            $participantUser->setUserDataType(ParticipantType::INDIVIDUAL);

            if ($creatorAccount) {
                $participantUser->addAccount($creatorAccount);
            }

            $em->persist($participantUser);
            $em->flush();

            $participantUserData = new UsersData();
            $participantUserData->setUser($participantUser);
            $participantUserData->setSystemId(strtolower(Helper::generateCode(9))); // Generate system ID

            // Users settings
            $setting = new UsersSettings();

            $setting->setUser($participantUser);
            $setting->setName('widgets');
            $setting->setValue('{"widgetsFirst":[{"id":"1","name":"ProfileParticipant"},{"id":"4","name":"ActivitiesAndServices"}],"widgetsSecond":[{"id":"3","name":"Assingment"},{"id":"2","name":"AssessmentAndOutcomes"}],"widgetsThird":[{"id":"5","name":"CaseNotes"}],"widgetsStash":[{"id":"6","name":"ProfileParticipant"}]}');

            $em->persist($setting);
            $em->flush();
        } else {
            // User exists
            $participantUser = $this->getDoctrine()->getRepository('App:Users')->find($this->getElementId());

            if ($participantUser === null) {
                throw new \Exception('Invalid user ID');
            }

            $participantUserData = $participantUser->getData();
        }

        $history = false;

        if ($this->getDataId() !== null) {
            $formData = $this->getDoctrine()->getRepository('App:FormsData')->findOneBy(['id' => $this->getDataId()]);
            $history = $formData->getAssignment() ? true : false;
        }

        if (!$history || !$participantUserData->getId()) {
            $this->updateUsersData($participantUserData, $data);
            $em->refresh($participantUser);
        }

        // Return user ID
        $this->response('id', $participantUser->getId());

        // System ID
        $this->system_id = $participantUserData->getSystemId();

        return null;
    }

    public function systemValues(): array
    {
        $map = $this->map();

        if (isset($map['system_id']) and $this->system_id !== null) {
            return [$map['system_id'] => $this->system_id];
        }

        return [];
    }

    public function validate(): ?array
    {
        $id = $this->getElementId();
        $phone = $this->params()->get('phone_number');

        if ($phone && !$this->getContainer()->get('App\Service\Participants\IndividualsDirectoryService')
                ->isUniqueOrNullParticipantPhone($phone, $id, $this->getAccount())) {
            $map = $this->map();
            if (isset($map['phone_number'])) {
                return [$map['phone_number'] => 'Phone number is already in use.'];
            }
        }

        // don't check if dealing with exising user or force flag is in use

        if ($id !== null || $this->force()) {
            return null;
        }

        $participantsDirectoryService = $this->getContainer()->get('App\Service\Participants\IndividualsDirectoryService');

        $account = $this->getAccount();

        if ($account->getAccountType() === AccountType::CHILD) {

            $accounts[] = $this->getAccount()->getParentAccount()->getId();
            $siblings = $this->getAccount()->getParentAccount()->getChildrenAccounts();

            foreach ($siblings as $sibling) {
                $accounts[] = $sibling->getId();
            }

            $participantsDirectoryService->setAccounts($accounts);
        }

        if ($account->getAccountType() === AccountType::PARENT) {
            $accounts[] = $this->getAccount()->getId();
            $siblings = $this->getAccount()->getChildrenAccounts();

            foreach ($siblings as $sibling) {
                $accounts[] = $sibling->getId();
            }

            $participantsDirectoryService->setAccounts($accounts);
        }

        $birthDateString = $this->params()->get('date_birth');
        $dateFormat      = $this->getDateFormat();

        $birthDate       = DateTime::createFromFormat($dateFormat, $birthDateString);
        $result          = $participantsDirectoryService->findUniqueParticipants(
            $this->params()->get('first_name'),
            $this->params()->get('last_name'),
            $this->params()->get('organization_id'),
            $account,
            false === $birthDate ? null : $birthDate
        );

        if (count($result['users'])) {
            return $result;
        }

        return null;
    }

    public function after()
    {
        $participantUser = $this->getDoctrine()->getRepository('App:Users')->find($this->getElementId());

        if ($participantUser->getData()->getStatus() == ParticipantStatus::ACTIVE) {
            return;
        }

        $formData = $this->getDoctrine()->getRepository('App:FormsData')->find($this->getDataId());

        if (!$formData->getAssignment()) {
            return;
        }

        $assignmentId = $formData->getAssignment()->getId();
        $latestAssignment = $this->getDoctrine()->getRepository('App:Assignments')->findLatestAssignmentForParticipant($this->getElementId());

        if (!$latestAssignment || ($latestAssignment->getId() !== $assignmentId)) {
            return;
        }

        $em = $this->getDoctrine();

        $module = $em->getRepository('App:Modules')->findOneBy(['key' => 'participants_profile']);

        $currentProfileFormData = $em->getRepository('App:FormsData')->findOneBy([
            'module'     => $module,
            'element_id' => $this->getElementId(),
            'assignment' => null
        ]);

        if (!$currentProfileFormData) {
            return;
        }

        $em->remove($currentProfileFormData);
        $em->flush();

        $newFormData = clone($formData);
        $newFormDataValues = clone($formData->getValues());
        $em->persist($newFormData);
        $em->flush();

        foreach ($newFormDataValues as $value) {

            $newFormDataValue = clone($value);
            $newFormDataValue->setData($newFormData);
            $em->persist($newFormDataValue);
            $newFormData->addValue($newFormDataValue);
        }

        $newFormData->setAssignment(null);
        $em->flush();


        if ($participantUser->getData()) {
            $this->updateUsersData($participantUser->getData(), $this->params());
        }
    }

    private function updateUsersData(UsersData $participantUserData, Handler\Params $data): void
    {
        // Users data
        $participantUserData->setGender($data->get('gender', ''));
        $participantUserData->setPhoneNumber(Helper::convertPhone($data->get('phone_number', '')));
        $participantUserData->setFirstName($data->get('first_name', ''));
        $participantUserData->setLastName($data->get('last_name', ''));
        $participantUserData->setJobTitle($data->get('job_title', ''));
        $participantUserData->setOrganizationId($data->get('organization_id', ''));

        // Generate system ID
        if ($participantUserData->getSystemId() === null or empty($participantUserData->getSystemId())) {
            $participantUserData->setSystemId(strtolower(Helper::generateCode(9))); // Generate system ID
        }

        $birthDate  = $data->get('date_birth');
        $dateFormat = $this->getDateFormat();

        if (false === empty($birthDate)) {
            $participantUserData->setDateBirth(
                DateTime::createFromFormat($dateFormat, $birthDate)
            );
        }

        $completedDate = $data->get('date_completed');

        if (false === empty($completedDate)) {
            $participantUserData->setDateCompleted(
                DateTime::createFromFormat($dateFormat, $completedDate)
            );
        }

        $em = $this->getDoctrine();

        $em->persist($participantUserData);
        $em->flush();
    }

}
