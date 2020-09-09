<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserChecker implements UserCheckerInterface {

    public function checkPreAuth(UserInterface $user) {
        if (!$user instanceof User) {
            return;
        }

        if (!$user->getIsActive()) {
            throw new CustomUserMessageAuthenticationException("Your user account is not active.");
        }

        if (!$user->getIsActive() && $user->getDeleted() !== null) {
            throw new CustomUserMessageAuthenticationException("Your user account have been deleted.");
        }
    }

    public function checkPostAuth(UserInterface $user) {
        if (!$user instanceof User) {
            return;
        }
    }
}