<?php

namespace App\Handler\Modules;

use App\Entity\Accounts;
use App\Entity\MemberData;
use App\Entity\Users;
use App\Entity\UsersSettings;
use App\Enum\AccountType;
use App\Enum\ParticipantStatus;
use App\Enum\ParticipantType;
use App\Handler\Modules\Handler\ModuleHandler;
use App\Service\Participants\MembersDirectoryService;
use App\Utils\Helper;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Class ParticipantsProfileHandler
 *
 * @package App\Handler\Modules
 */
class MembersProfileHandler extends ModuleHandler
{
    private $systemId;
    private MembersDirectoryService $membersDirectoryService;

    public function __construct(
        MembersDirectoryService $membersDirectoryService,
        EntityManagerInterface $doctrine
    ) {
        $this->membersDirectoryService = $membersDirectoryService;
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
            $participantUser->setUserDataType(ParticipantType::MEMBER);

            if ($creatorAccount) {
                $participantUser->addAccount($creatorAccount);
            }

            $em->persist($participantUser);
            $em->flush();

            $memberData = new MemberData();
            $memberData->setUser($participantUser);
            $memberData->setSystemId(strtolower(Helper::generateCode(9))); // Generate system ID

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

            $memberData = $participantUser->getMemberData();
        }

        $history = false;
        if ($this->getDataId() !== null) {
            $formData = $this->getDoctrine()->getRepository('App:FormsData')->findOneBy(['id' => $this->getDataId()]);
            $history = $formData->getAssignment() ? true : false;
        }

        if (!$history) {
            // Users data
            $this->updateMembersData($memberData, $data);
            $em->refresh($participantUser);
        }

        // Return user ID
        $this->response('id', $participantUser->getId());

        // System ID
        $this->systemId = $memberData->getSystemId();
        return null;
    }

    public function systemValues(): array
    {
        $map = $this->map();

        if (isset($map['system_id']) and $this->systemId !== null) {
            return [$map['system_id'] => $this->systemId];
        }

        return [];
    }

    public function validate(): ?array
    {
        $id = $this->getElementId();
        $phone = $this->params()->get('phone_number');

        if (
            $phone &&
            !$this->membersDirectoryService->isUniqueOrNullMemberPhone($phone, $id, $this->getAccount())
        ) {
            $map = $this->map();
            if (isset($map['phone_number'])) {
                return [$map['phone_number'] => 'Phone number is already in use.'];
            }
        }

        if ($id !== null || $this->force()) {
            return null;
        }

        // New user
        // Check duplicate if force is false
        // Get ID this user

        $account = $this->getAccount();

        if ($account->getAccountType() === AccountType::CHILD) {

            $accounts[] = $this->getAccount()->getParentAccount()->getId();
            $siblings = $this->getAccount()->getParentAccount()->getChildrenAccounts();

            foreach ($siblings as $sibling) {
                $accounts[] = $sibling->getId();
            }

            $this->membersDirectoryService->setAccounts($accounts);
        }

        if ($account->getAccountType() === AccountType::PARENT) {
            $accounts[] = $this->getAccount()->getId();
            $siblings = $this->getAccount()->getChildrenAccounts();

            foreach ($siblings as $sibling) {
                $accounts[] = $sibling->getId();
            }

            $this->membersDirectoryService->setAccounts($accounts);
        }

        $result = $this->membersDirectoryService->findUniqueMembers(
            $this->params()->get('name'),
            $this->params()->get('organization_id'),
            $this->getAccount()
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

        $module = $em->getRepository('App:Modules')->findOneBy(['key' => 'members_profile']);

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
            $this->updateMembersData($participantUser->getData(), $this->params());
        }
    }


    /**
     * @param MemberData|null $memberData
     * @param Handler\Params $data
     * @param \Doctrine\ORM\EntityManagerInterface $em
     * @throws \Exception
     */
    private function updateMembersData(?MemberData $memberData, Handler\Params $data): void
    {
        $memberData->setName($data->get('name', ''));
        $memberData->setPhoneNumber(Helper::convertPhone($data->get('phone_number', '')));
        $memberData->setJobTitle($data->get('job_title', ''));
        $memberData->setOrganizationId($data->get('organization_id', ''));

        // Generate system ID
        if ($memberData->getSystemId() === null or empty($memberData->getSystemId())) {
            $memberData->setSystemId(strtolower(Helper::generateCode(9))); // Generate system ID
        }

        if (!empty($data->get('date_completed', ''))) {
            $memberData->setDateCompleted(
                new \DateTime(
                    $data->get('date_completed', '')
                )
            );
        }

        $em = $this->getDoctrine();

        $em->persist($memberData);
        $em->flush();
    }
}
