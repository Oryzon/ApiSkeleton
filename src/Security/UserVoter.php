<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class UserVoter extends Voter {

    const VIEW = 'view';
    const EDIT = 'edit';
    const ADD = 'add';

    protected function supports($attribute, $subject) {
        if (!in_array($attribute, array(self::VIEW, self::EDIT, self::ADD))) {
            return false;
        }

        if (!$subject instanceof User) {
            return false;
        }

        return true;
    }

    protected function voteOnAttribute($attribute, $subject, TokenInterface $token) {
        $userAsking = $token->getUser();

        if (!$userAsking instanceof User) {
            return false;
        }

        $userEntity = $subject;

        switch($attribute) {
            case self::VIEW:
                return $this->canView($userEntity, $userAsking);
            case self::EDIT:
                return $this->canEdit($userEntity, $userAsking);
            case self::ADD:
                return $this->canAdd($userAsking);
            default:
                return false;
        }
    }

    private function canView(User $userEntity, User $userAsking) {
        return true;
    }

    private function canEdit(User $userEntity, User $userAsking) {
        return (
            $userEntity->getId() == $userAsking->getId() ||
            in_array('ROLE_ADMIN', $userAsking->getRoles())
        );
    }

    private function canAdd(User $userAsking) {
        return in_array('ROLE_ADMIN', $userAsking->getRoles());
    }
}