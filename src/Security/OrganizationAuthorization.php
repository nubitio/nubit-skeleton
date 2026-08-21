<?php

declare(strict_types=1);

namespace App\Security;

use App\Entity\OrganizationMembership;
use App\Tenant\OrganizationMembershipUserInterface;
use Nubit\Platform\Tenant\Context\TenantContext;
use Symfony\Component\Security\Core\User\UserInterface;

/** Resolves permissions only for the authenticated user's active tenant membership. */
final readonly class OrganizationAuthorization
{
    public const PRODUCT_READ = 'APP_PRODUCT_READ';
    public const PRODUCT_MANAGE = 'APP_PRODUCT_MANAGE';

    /** @var list<string> */
    public const CAPABILITIES = [
        self::PRODUCT_READ,
        self::PRODUCT_MANAGE,
    ];

    public function __construct(
        private TenantContext $tenantContext,
    ) {}

    public function isGranted(UserInterface $user, string $capability): bool
    {
        $membership = $this->activeMembership($user);
        if (null === $membership) {
            return false;
        }

        return match ($capability) {
            self::PRODUCT_READ => true,
            self::PRODUCT_MANAGE => $membership->isAdmin(),
            default => false,
        };
    }

    public function activeMembership(UserInterface $user): ?OrganizationMembership
    {
        $tenantId = $this->tenantContext->getTenantId();
        if (null === $tenantId || !$user instanceof OrganizationMembershipUserInterface) {
            return null;
        }

        foreach ($user->getOrganizationMemberships() as $membership) {
            if (
                $membership instanceof OrganizationMembership
                && $membership->isActive()
                && $membership->getOrganization()?->getId() === $tenantId
            ) {
                return $membership;
            }
        }

        return null;
    }
}
