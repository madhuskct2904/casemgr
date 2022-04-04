<?php

namespace App\Service;

use App\Entity\EmailRecipient;

class EmailBodyParser
{
    protected $rawBody;
    protected $parsedBody = '';
    protected $recipient;

    public function setRawBody(string $body): void
    {
        $this->rawBody = $body;
    }

    public function setRecipient(EmailRecipient $recipient): void
    {
        $this->recipient = $recipient;
    }

    public function getParsedBody(): string
    {
        return $this->parsedBody;
    }

    public function parse(): string
    {
        $user = $this->recipient->getUser();
        $userData = $user ? $user->getData() : null;

        $firstName = $userData ? $userData->getFirstName() : '';
        $lastName = $userData ? $userData->getLastName() : '';
        $email = $this->recipient->getEmail();

        $this->parsedBody = $this->rawBody;

        $this->parsedBody = str_replace('%first_name%', $firstName, $this->parsedBody);
        $this->parsedBody = str_replace('%last_name%', $lastName, $this->parsedBody);
        $this->parsedBody = str_replace('%email%', $email, $this->parsedBody);

        $this->parsedBody = nl2br($this->parsedBody);
        $this->parsedBody = str_replace('><br />', '>', $this->parsedBody);

        return $this->parsedBody;
    }
}
