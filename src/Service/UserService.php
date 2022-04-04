<?php

namespace App\Service;

use App\Entity\Users;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class UserService
{
  private TokenStorageInterface $tokenStorage;

  public function __construct(TokenStorageInterface $tokenStorage)
  {
    $this->tokenStorage = $tokenStorage;
  }

  public function getLoggedUser(): ?Users
  {
    $token = $this->tokenStorage->getToken();

    if ($token === null) {
        return null;
    } else {
        $user = $token->getUser();
        return is_object($user) ? $user : null;
    }
  }
}