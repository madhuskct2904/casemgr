<?php


namespace App\Service;

use App\Entity\MassMessages;
use App\Entity\Messages;
use App\Entity\Users;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Class MassMessageHistoryService
 * @package App\Service
 */
class MassMessageHistoryService
{
    /**
     * @var ContainerInterface
     */
    private $container;
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * MassMessageHistoryService constructor.
     *
     * @param ContainerInterface $container
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        ContainerInterface $container,
        EntityManagerInterface $entityManager
    ) {
        $this->container     = $container;
        $this->entityManager = $entityManager;
    }

    /**
     * @param array $params
     *
     * @param Users $user
     *
     * @return array
     */
    public function search(array $params, Users $user): array
    {
        $repository = $this->entityManager->getRepository('App:MassMessages');

        return $this->doSearch($repository, $params, $user, false);
    }

    /**
     * @param int $massMessageId
     * @param array $params
     *
     * @param Users $user
     *
     * @return array
     */
    public function searchMessages(int $massMessageId, array $params, Users $user): array
    {
        $repositoryMessages     = $this->entityManager->getRepository('App:Messages');
        $repositoryMassMessages = $this->entityManager->getRepository('App:MassMessages');
        $messages               = $this->doSearch($repositoryMessages, $params, $user, $massMessageId);

        $massMessage = $repositoryMassMessages->find($massMessageId);
        $all         = $massMessage->getMessages()->filter(function (Messages $message) {
            return ($message->getStatus() != 'system_administrator');
        })->count();
        $sent        = $massMessage->getMessages()->filter(function (Messages $message) {
            return ($message->getStatus() != 'error' && $message->getStatus() != 'system_administrator' && $message->getStatus() != null);
        })->count();

        return [
            'records' => $messages,
            'stats'   => [
                'all'  => $all,
                'sent' => $sent
            ]
        ];
    }

    /**
     * @param MassMessages $massMessage
     *
     * @return mixed
     * @throws \Exception
     */
    public function getCsv(MassMessages $massMessage)
    {
        $date   = new \DateTime();
        $data[] = [
            'Date of Export ' . $date->format('m/d/Y h:i A'),
            'Message History'
        ];

        $data[] = [];

        $data[] = [
            'Participant name',
            'System ID',
            'Organization ID',
            'Status'
        ];

        $criteria = new Criteria();
        $criteria->where(Criteria::expr()->neq('status', 'system_administrator'));
        $criteria->andWhere(Criteria::expr()->eq('massMessage', $massMessage));

        $messages = $this->entityManager->getRepository('App:Messages')->matching($criteria);

        foreach ($messages as $message) {
            $data[] = [
                $message->getParticipant()->getData()->getFullName(false),
                $message->getParticipant()->getData()->getSystemId(),
                $message->getParticipant()->getData()->getOrganizationId(),
                $message->getStatusTransformed()
            ];
        }


        return $data;
    }

    /**
     * @param $repository
     * @param array $params
     * @param bool $massMessageId
     *
     * @param Users $user
     *
     * @return array
     */
    private function doSearch($repository, array $params, Users $user, $massMessageId = false): array
    {
        $filters     = (isset($params['filters'])) ? $params['filters'] : null;
        $currentPage = (isset($params['current_page']) ? $params['current_page'] : 1);
        $limit       = (isset($params['limit']) ? $params['limit'] : 1);

        if (is_array($filters)) {
            foreach ($filters as $array) {
                $repository->set(key($array), $array[key($array)]);
            }
        }

        if (isset($params['sort_by'])) {
            $repository->setOrderBy($params['sort_by']);
        }

        if (isset($params['sort_order'])) {
            $repository->setOrderDir($params['sort_order']);
        }

        if (isset($params['columns_filter'])) {
            $repository->setColumnFilter($params['columns_filter']);
        }

        if ($massMessageId) {
            $repository->set('mass_message', $massMessageId);
        }

        $repository->set('current_page', (int)$currentPage);
        $repository->set('limit', (int)$limit);

        $account = $this->entityManager->getRepository('App:AccountsData')->findOneBy([
            'accountUrl' => $user->getDefaultAccount()
        ]);

        $repository->set('account_id', $account->getId());

        $timezone = new \DateTimeZone($user->getData()->getTimeZone() ?? 'UTC');
        $repository->setTimezone($timezone);

        return [
            'results_num' => $repository->resultsNum(),
            'data'        => $repository->search(),
        ];
    }
}
