<?php

namespace App\Controller;

use App\Domain\FormsGroups\ParticipantDirectoryDecorator;
use App\Enum\ParticipantDirectoryContext;
use App\Enum\ParticipantType;
use App\Exception\ExceptionMessage;
use App\Service\Participants\IndividualsDirectoryService;
use App\Service\Participants\MembersDirectoryService;
use Doctrine\Common\Annotations\Annotation\IgnoreAnnotation;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Class SearchController
 *
 * @IgnoreAnnotation("api")
 * @IgnoreAnnotation("apiGroup")
 * @IgnoreAnnotation("apiHeader")
 * @IgnoreAnnotation("apiParam")
 * @IgnoreAnnotation("apiSuccess")
 * @IgnoreAnnotation("apiError")
 *
 * @package App\Controller
 */
class ParticipantsDirectoryController extends Controller
{
    /**
     * @return mixed
     * @api {post} /search Search Participants
     * @apiGroup Searchable
     *
     * @apiHeader {String} token Authorization Token
     * @apiParam {String} [keyword] Keyword
     * @apiParam {String} [filters] Filters
     * @apiParam {Boolean} [notdismissed] Not Dismissed Filter
     *
     * @apiSuccess {Integer} results_num Number of results
     * @apiSuccess {Array} data Results
     *
     */
    public function searchAction(
        ManagerRegistry $doctrine,
        IndividualsDirectoryService $individualsDirectoryService,
        MembersDirectoryService $membersDirectoryService
    )
    {
        if ($this->checkToken() === false) {
            return $this->getResponse()->error(ExceptionMessage::INVALID_TOKEN, 401);
        }

        if ($this->account()->getParticipantType() === ParticipantType::MEMBER) {
            $directory = $membersDirectoryService;
        }

        if ($this->account()->getParticipantType() === ParticipantType::INDIVIDUAL) {
            $directory = $individualsDirectoryService;
        }

        $directory->setDateFormat($this->phpDateFormat());

        $keyword = $this->getRequest()->param('keyword');
        $filters = $this->getRequest()->param('filters');

        if ($this->getRequest()->param('in_linked_accounts', false)) {
            $account = $this->account();
            $accounts = [];

            if ($account->getParentAccount()) {
                $accounts[] = $account->getParentAccount()->getId();
                foreach ($account->getParentAccount()->getChildrenAccounts() as $childrenAccount) {
                    $accounts[] = $childrenAccount->getId();
                }
            }

            if (!$account->getParentAccount() && $account->getChildrenAccounts()) {
                $accounts[] = $account->getId();
                foreach ($account->getChildrenAccounts() as $childrenAccount) {
                    $accounts[] = $childrenAccount->getId();
                }
            }

            $directory->setAccounts($accounts);
        }

        if ($this->getRequest()->param('in_related_accounts', false)) {
            $accounts = json_decode($this->account()->getSearchInOrganizations(), true);
            $organizationsIds = array_column($accounts, 'id');
            $organizationsIds[] = $this->account()->getId();
            $directory->setAccounts($organizationsIds);
        }

        if (is_array($filters)) {
            foreach (array_filter($filters) as $array) {
                $directory->set(key($array), $array[key($array)]);
            }
        }

        if ($keyword) {
            $keyword = trim($keyword);

            if (stripos($keyword, 'Case:') === 0) {
                $directory->set('case', substr($keyword, 5));
                if (($statusPos = stripos($keyword, 'Status:')) !== false) {
                    $status = substr($keyword, $statusPos + 7);
                    $case = substr($keyword, 5, strlen($keyword) - strlen($status) - 5 - 7);

                    $directory->set('case', trim($case));
                    $directory->set('status', trim($status));
                }
            } else {
                $directory->set('keyword', $keyword);
            }
        }

        if ($this->getRequest()->param('for_messaging')) {
            $directory->set('for_messaging', true);
        }

        if ($this->getRequest()->param('notdismissed')) {
            $directory->set('notdismissed', true);
        }

        if ($this->getRequest()->param('ignorewithemptystatus')) {
            $directory->set('ignorewithemptystatus', true);
        }

        if ($this->getRequest()->param('sort_by')) {
            $directory->setOrderBy($this->getRequest()->param('sort_by'));
        }

        if ($this->getRequest()->param('sort_order')) {
            $directory->setOrderDir($this->getRequest()->param('sort_order'));
        }

        if ($this->getRequest()->param('columns_filter')) {
            $directory->setColumnFilter($this->getRequest()->param('columns_filter'));
        }

        $context = $this->getRequest()->param('context');

        if (!ParticipantDirectoryContext::isValidValue($context)) {
            $context = ParticipantDirectoryContext::PARTICIPANT_DIRECTORY;
        }

        // show only participants assigned to current account
        $directory->set('account', $this->account()->getId());
        $directory->set('current_page', (int)$this->getRequest()->param('current_page', 1));
        $directory->set('limit', (int)$this->getRequest()->param('limit', 20));
        $directory->setContext($context);

        if ($context === ParticipantDirectoryContext::GROUPS_FORMS) {
            $directory = new ParticipantDirectoryDecorator($directory, $doctrine);
            $directory->setFormId($this->getRequest()->param('form_id'));
        }

        return $this->getResponse()->success([
            'results_num' => $directory->resultsNum(),
            'rows' => $directory->search(),
            'columns' => $directory->getColumns($this->account(), true)
        ]);
    }

}
