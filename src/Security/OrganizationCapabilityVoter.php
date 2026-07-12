<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

final class OrganizationCapabilityVoter extends Voter
{
    public function __construct(private readonly OrganizationAuthorization $authorization)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, OrganizationAuthorization::CAPABILITIES, true);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        return $user instanceof UserInterface && $this->authorization->isGranted($user, $attribute);
    }
}
