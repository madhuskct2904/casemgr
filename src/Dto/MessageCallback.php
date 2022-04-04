<?php


namespace App\Dto;

/**
 * Class MessageCallback
 * @package App\Dto
 */
class MessageCallback
{
    /**
     * @var string|null
     */
    private $errorCode;
    /**
     * @var string|null
     */
    private $smsSid;
    /**
     * @var string|null
     */
    private $smsStatus;
    /**
     * @var string|null
     */
    private $messageStatus;
    /**
     * @var string|null
     */
    private $to;
    /**
     * @var string|null
     */
    private $messageSid;
    /**
     * @var string|null
     */
    private $accountSid;
    /**
     * @var string|null
     */
    private $from;
    /**
     * @var string|null
     */
    private $apiVersion;

    /**
     * MessageCallback constructor.
     *
     * @param string $errorCode
     * @param string $smsSid
     * @param string $smsStatus
     * @param string $messageStatus
     * @param string $to
     * @param string $messageSid
     * @param string $accountSid
     * @param string $from
     * @param string $apiVersion
     */
    public function __construct(
        ?string $errorCode,
        ?string $smsSid,
        ?string $smsStatus,
        ?string $messageStatus,
        ?string $to,
        ?string $messageSid,
        ?string $accountSid,
        ?string $from,
        ?string $apiVersion
    ) {
        $this->errorCode     = $errorCode;
        $this->smsSid        = $smsSid;
        $this->smsStatus     = $smsStatus;
        $this->messageStatus = $messageStatus;
        $this->to            = $to;
        $this->messageSid    = $messageSid;
        $this->accountSid    = $accountSid;
        $this->from          = $from;
        $this->apiVersion    = $apiVersion;
    }

    /**
     * @return string|null
     */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /**
     * @return string|null
     */
    public function getSmsSid(): ?string
    {
        return $this->smsSid;
    }

    /**
     * @return string|null
     */
    public function getSmsStatus(): ?string
    {
        return $this->smsStatus;
    }

    /**
     * @return string|null
     */
    public function getMessageStatus(): ?string
    {
        return $this->messageStatus;
    }

    /**
     * @return string|null
     */
    public function getTo(): ?string
    {
        return $this->to;
    }

    /**
     * @return string|null
     */
    public function getMessageSid(): ?string
    {
        return $this->messageSid;
    }

    /**
     * @return string|null
     */
    public function getAccountSid(): ?string
    {
        return $this->accountSid;
    }

    /**
     * @return string|null
     */
    public function getFrom(): ?string
    {
        return $this->from;
    }

    /**
     * @return string|null
     */
    public function getApiVersion(): ?string
    {
        return $this->apiVersion;
    }

    /**
     * @return array
     */
    public function toArray(): array
    {
        return [
            'errorCode'     => $this->getErrorCode(),
            'smsSid'        => $this->getSmsSid(),
            'smsStatus'     => $this->getSmsStatus(),
            'messageStatus' => $this->getMessageStatus(),
            'to'            => $this->getTo(),
            'messageSid'    => $this->getMessageSid(),
            'accountSid'    => $this->getAccountSid(),
            'from'          => $this->getFrom(),
            'apiVersion'    => $this->getApiVersion()
        ];
    }
}
