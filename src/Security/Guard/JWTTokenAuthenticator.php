<?php

namespace App\Security\Guard;

use Lexik\Bundle\JWTAuthenticationBundle\Security\Guard\JWTTokenAuthenticator as BaseAuthenticator;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserInterface;

class JWTTokenAuthenticator extends BaseAuthenticator {

    public function checkCredentials($credentials, UserInterface $user) {
        // This will be used for checking some information after login and using a route.
        return true;
    }
}