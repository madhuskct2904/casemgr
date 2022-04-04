<?php

namespace App\Repository;

use App\Enum\EmailRecipientStatus;
use Doctrine\ORM\AbstractQuery;

class EmailMessageRepository extends \Doctrine\ORM\EntityRepository
{
    public function findWithRecipientsAsArray($id)
    {
        $emailData = $this->createQueryBuilder('e')
            ->select('e.id, e.body, e.subject, e.header, e.recipientsGroup as recipients_group, e.recipientsOption as recipients_option, e.status, e.sender, IDENTITY(e.template) as template_id')
            ->where('e.id =:id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getSingleResult(\Doctrine\ORM\Query::HYDRATE_ARRAY);

        $recipients =
            $this->getEntityManager()
                ->createQueryBuilder()
                ->select('r.email, r.status')
                ->from('App:EmailRecipient', 'r')
                ->where('r.emailMessage = :emailMessage')
                ->setParameter('emailMessage', $emailData['id'])
                ->getQuery()
                ->getResult(AbstractQuery::HYDRATE_ARRAY);

        $emailData['recipients'] = array_column($recipients, 'email');

        $failedRecipients = array_map(function ($item) {
            return $item['status'] == EmailRecipientStatus::ERROR;
        }, $recipients);

        $emailData['failedRecipients'] = array_column($failedRecipients, 'email');

        if ($emailData['recipients_group'] === 'users_by_account') {
            $emailData['recipients_option'] = empty($emailData['recipients_option']) ? [] : json_decode($emailData['recipients_option'], true);
        }

        return $emailData;
    }
}
