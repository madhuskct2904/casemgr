<?php

namespace App\Service;

use App\Entity\MassMessages;
use App\Entity\Messages;
use App\Entity\Users;
use App\Enum\MessageStrings;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Class MessageService
 * @package App\Service
 */
class MessageService
{
    private const SEND_ENDPOINT = 'message';

    private EntityManagerInterface $entityManager;
    private string $messagingApiToken;
    private string $messagingApiUrl;

    /**
     * @param EntityManagerInterface $entityManager
     * @param string                 $messagingApiToken
     * @param string                 $messagingApiUrl
     */
    public function __construct(EntityManagerInterface $entityManager, string $messagingApiToken, string $messagingApiUrl)
    {
        $this->entityManager     = $entityManager;
        $this->messagingApiToken = $messagingApiToken;
        $this->messagingApiUrl   = $messagingApiUrl;
    }

    /**
     * @param string      $body
     * @param string      $to
     * @param string|null $from
     *
     * @return array
     *
     * @throws GuzzleException
     */
    public function sendMessage(string $body, string $to, ?string $from = null): array
    {
        $client   = new Client();
        $action   = sprintf('%s%s/', $this->messagingApiUrl, self::SEND_ENDPOINT);
        $response = $client->post(
            $action,
            [
                'json' => [
                    'sender'    => $from,
                    'recipient' => $to,
                    'body'      => $body,
                ],
                'headers' => [
                    'X-Access-Token' => $this->messagingApiToken,
                ]
            ]
        );

        $responseBody        = $response->getBody();
        $encodedResponseBody = json_decode(
            $responseBody->getContents(),
            1
        );

        if (true === array_key_exists('data', $encodedResponseBody)) {
            return $encodedResponseBody['data'];
        }

        return [
            'sid'    => null,
            'status' => 'error',
        ];
    }

    /**
     * @param array             $data
     * @param Users             $user
     * @param MassMessages|null $massMessage
     *
     * @return Messages
     */
    public function storeMessage(array $data, Users $user, ?MassMessages $massMessage = null): Messages
    {
        $message = new Messages();

        $status = !empty($data['response']) ? $data['response']['status'] : 'error';
        $sid    = !empty($data['response']) ? $data['response']['sid'] : null;

        $message
            ->setBody($data['message']['body'])
            ->setUser($user)
            ->setFromPhone($data['message']['fromPhone'])
            ->setParticipant($data['message']['participant'])
            ->setToPhone($data['message']['toPhone'])
            ->setStatus($status)
            ->setType(MessageStrings::OUTBOUND)
            ->setCreatedAt(new DateTime())
            ->setSid($sid)
            ->setMassMessage($massMessage)
            ->setError($data['error'])
        ;

        $this->entityManager->persist($message);
        $this->entityManager->flush();

        if ($data['error']) {
            $this->addErrorMessage($user, $data, $massMessage);
        }

        return $message;
    }

    /**
     * @param Users             $user
     * @param array             $data
     * @param MassMessages|null $massMessage
     */
    private function addErrorMessage(Users $user, array $data, ?MassMessages $massMessage): void
    {
        $errorMessage = new Messages();

        $status = MessageStrings::ERROR_RESPONSE_MESSAGE_STATUS;
        $sid    = null;
        $body   = MessageStrings::ERROR_MESSAGE;

        $errorMessage
            ->setBody($body)
            ->setUser($user)
            ->setFromPhone($data['message']['fromPhone'])
            ->setParticipant($data['message']['participant'])
            ->setToPhone($data['message']['toPhone'])
            ->setStatus($status)
            ->setType(MessageStrings::INBOUND)
            ->setCreatedAt(new DateTime())
            ->setSid($sid)
            ->setMassMessage($massMessage)
            ->setError($data['error'])
        ;

        $this->entityManager->persist($errorMessage);
        $this->entityManager->flush();
    }
}
