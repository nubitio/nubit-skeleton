<?php

declare(strict_types=1);

namespace App\Tenant;

use App\Entity\Organization;
use App\Entity\OrganizationMembership;
use Nubit\TenantBundle\Resolver\ResolvedTenant;
use Nubit\TenantBundle\Resolver\TenantResolverInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

final class OrganizationTenantResolver implements TenantResolverInterface
{
    public function resolve(Request $request, ?UserInterface $user): ?ResolvedTenant
    {
        if (!$user instanceof OrganizationMembershipUserInterface) {
            return null;
        }

        $requestedId = $request->headers->get('X-Organization-Id');
        if (null !== $requestedId && ('' === trim($requestedId) || !ctype_digit($requestedId))) {
            return null;
        }

        $memberships = $this->collectActiveOrganizations($user);

        if ($memberships === []) {
            return null;
        }

        if (null !== $requestedId) {
            $organizationId = (int) $requestedId;
            $organization = $memberships[$organizationId] ?? null;
            if (null === $organization) {
                return null;
            }

            return new ResolvedTenant($organizationId, $organization->getSlug(), $organization->getPrimaryDomain());
        }

        ksort($memberships, SORT_NUMERIC);
        // Keys are the non-null organization ids collected above; the array is non-empty here.
        $organizationId = (int) array_key_first($memberships);
        $organization = $memberships[$organizationId];

        return new ResolvedTenant($organizationId, $organization->getSlug(), $organization->getPrimaryDomain());
    }

    /**
     * Active organizations the user belongs to, keyed by their non-null id.
     *
     * @return array<int, Organization>
     */
    private function collectActiveOrganizations(OrganizationMembershipUserInterface $user): array
    {
        $organizations = [];

        foreach ($user->getOrganizationMemberships() as $membership) {
            if (!$membership instanceof OrganizationMembership || !$membership->isActive()) {
                continue;
            }

            $organization = $membership->getOrganization();
            $organizationId = $organization?->getId();
            if (null !== $organization && null !== $organizationId) {
                $organizations[$organizationId] = $organization;
            }
        }

        return $organizations;
    }
}
