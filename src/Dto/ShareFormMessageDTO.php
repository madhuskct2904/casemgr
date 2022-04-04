<?php

namespace App\Dto;

class ShareFormMessageDTO {

    private $recipientName = '';
    private $accountName = '';
    private $formUrl = '';
    private $formName = '';
    private $internalUrl = '';
    private $userName = '';

    /**
     * @return string
     */
    public function getRecipientName(): string
    {
        return $this->recipientName;
    }

    /**
     * @param string $recipientName
     */
    public function setRecipientName(string $recipientName): void
    {
        $this->recipientName = $recipientName;
    }

    /**
     * @return string
     */
    public function getAccountName(): string
    {
        return $this->accountName;
    }

    /**
     * @param string $accountName
     */
    public function setAccountName(string $accountName): void
    {
        $this->accountName = $accountName;
    }

    /**
     * @return string
     */
    public function getFormUrl(): string
    {
        return $this->formUrl;
    }

    /**
     * @param string $formUrl
     */
    public function setFormUrl(string $formUrl): void
    {
        $this->formUrl = $formUrl;
    }

    /**
     * @return string
     */
    public function getFormName(): string
    {
        return $this->formName;
    }

    /**
     * @param string $formName
     */
    public function setFormName(string $formName): void
    {
        $this->formName = $formName;
    }

    /**
     * @return string
     */
    public function getInternalUrl(): string
    {
        return $this->internalUrl;
    }

    /**
     * @param string $internalUrl
     */
    public function setInternalUrl(string $internalUrl): void
    {
        $this->internalUrl = $internalUrl;
    }

    /**
     * @return string
     */
    public function getUserName(): string
    {
        return $this->userName;
    }

    /**
     * @param string $userName
     */
    public function setUserName(string $userName): void
    {
        $this->userName = $userName;
    }

}
