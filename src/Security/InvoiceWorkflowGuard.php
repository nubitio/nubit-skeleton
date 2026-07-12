<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\Invoice;
use Nubit\WorkflowBundle\Contract\WorkflowGuardInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class InvoiceWorkflowGuard implements WorkflowGuardInterface
{
    private ?string $blockReason = null;

    public function __construct(
        private readonly OrganizationAuthorization $authorization,
        private readonly TokenStorageInterface $tokenStorage,
    ) {
    }

    public function canTransition(object $entity, string $transitionName): bool
    {
        $capability = match ($transitionName) {
            'confirm' => OrganizationAuthorization::INVOICE_WRITE,
            'mark_paid' => OrganizationAuthorization::INVOICE_PAY,
            'cancel' => OrganizationAuthorization::INVOICE_CANCEL,
            'reopen' => OrganizationAuthorization::INVOICE_REOPEN,
            default => null,
        };
        $user = $this->tokenStorage->getToken()?->getUser();

        if (!$entity instanceof Invoice || null === $capability || !$user instanceof UserInterface || !$this->authorization->isGranted($user, $capability)) {
            $this->blockReason = 'An active organization membership with the required permission is required.';

            return false;
        }

        $this->blockReason = null;

        return true;
    }

    public function getBlockReason(): ?string
    {
        return $this->blockReason;
    }
}
