<?php

namespace App\Security\Guard;

use Lexik\Bundle\JWTAuthenticationBundle\Security\Guard\JWTTokenAuthenticator as BaseAuthenticator;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;

class JWTTokenAuthenticator extends BaseAuthenticator {

    public function checkCredentials($credentials, UserInterface $user) {
//        if (!$user->getIsActive() && $user->getDeleted() !== null) {
//            throw new CustomUserMessageAuthenticationException("Your user account have been deleted.");
//        }
//
//        if (!$user->getIsActive()) {
//            throw new CustomUserMessageAuthenticationException("Your user account is not active.");
//        }

        return true;
    }
}