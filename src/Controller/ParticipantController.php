<?php


namespace App\Controller;


use App\Entity\Users;
use App\Enum\ParticipantType;
use App\Enum\SystemMessageStatus;
use App\Exception\ExceptionMessage;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class ParticipantController extends Controller
{
    public function dashboardAction(Request $request): JsonResponse
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        $id = $this->getRequest()->param('user_id');
        $user = $this->getDoctrine()->getRepository('App:Users')->findOneBy(['id' => $id]);

        if (!$user) {
            return $this->getResponse()->error(ExceptionMessage::NOT_FOUND_USER, 404);
        }

        $account = $user->getAccounts()->first();

        if (!$account || $account->getId() !== $this->account()->getId()) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_ACCOUNT, 403);
        }

        // User history assignments
        $assignments = $this->getDoctrine()->getRepository('App:Assignments')->getHistoryAssignments($user);

        // User current assignment
        $assignment = $this->getDoctrine()->getRepository('App:Assignments')->getCurrentAssignment($user);

        // Activities services
        $activitiesServices = $this->getDoctrine()->getRepository('App:FormsData')->getByModuleAndElementId(
            'activities_services',
            $user,
            $this->account()
        );

        // Assessments outcomes
        $assessmentsOutcomes = $this->getDoctrine()->getRepository('App:FormsData')->getByModuleAndElementId(
            'assessment_outcomes',
            $user,
            $this->account()
        );

        $userDataType = $user->getUserDataType();

        if ($userDataType == ParticipantType::INDIVIDUAL) {
            $profileModuleKey = 'participants_profile';
        }

        if ($userDataType == ParticipantType::MEMBER) {
            $profileModuleKey = 'members_profile';
        }

        // Case Notes
        $caseNotes = $this->access() > Users::ACCESS_LEVELS['VOLUNTEER']
            ? $this->getDoctrine()->getRepository('App:CaseNotes')->getWidget($user)
            : [];

        $profile = $this->getDoctrine()->getRepository('App:Users')->getProfileData($user, ['email', 'programs'], $profileModuleKey);

        $data = [
            'email'                  => $user->getEmail(),
            'system_id'              => $user->getData()->getSystemId(),
            'case_manager'           => $user->getData()->getCaseManager(),
            'secondary_case_manager' => $user->getData()->getCaseManagerSecondary(),
            'phone_number'           => $user->getData()->getPhoneNumber(),
            'avatar'                 => $user->getData()->getAvatar(),
            'job_title'              => $user->getData()->getJobTitle(),
            'date_format'            => $this->dateFormat($user),
            'assignments'            => $assignments,
            'assignment'             => $assignment,
            'activities_services'    => $activitiesServices,
            'assessments_outcomes'   => $assessmentsOutcomes,
            'case_notes'             => $caseNotes,
            'profile'                => $profile,
            'organization_id'        => $user->getData()->getOrganizationId(),
            'status'                 => $user->getData()->getStatus(),
            'status_label'           => $user->getData()->getStatusLabel(),
            'id'                     => $user->getId()
        ];

        if ($userDataType == ParticipantType::INDIVIDUAL) {
            $data += [
                'first_name' => $user->getData()->getFirstName(),
                'last_name'  => $user->getData()->getLastName(),
                'date_birth' => $user->getData()->getDateBirth() ? $user->getData()->getDateBirth()->format('d.m.Y') : '',
                'gender'     => $user->getData()->getGender(),
            ];
        }

        if ($userDataType == ParticipantType::MEMBER) {
            $data += ['name' => $user->getMemberData()->getName()];
        }

        $referralForm = $this->getDoctrine()->getRepository('App:Referral')->findOneBy(['enrolledParticipant' => $user]);

        if ($referralForm) {
            $data['referral_form_data_id'] = $referralForm->getFormData()->getId();
        }

        $this->getDoctrine()->getRepository('App:SystemMessage')->setStatusBy([
            'relatedTo'   => 'participant',
            'relatedToId' => $user->getId(),
            'user'        => $this->user(),
            'account'     => $this->account()
        ], SystemMessageStatus::READ);

        return $this->getResponse()->success($data);
    }

}
