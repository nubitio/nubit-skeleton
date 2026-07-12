<?php

declare(strict_types=1);

namespace App\Tenant;

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

        $memberships = [];
        foreach ($user->getOrganizationMemberships() as $membership) {
            if (!$membership instanceof OrganizationMembership || !$membership->isActive()) {
                continue;
            }

            $organization = $membership->getOrganization();
            if (null !== $organization && null !== $organization->getId()) {
                $memberships[$organization->getId()] = $organization;
            }
        }

        if ($memberships === []) {
            return null;
        }

        if (null !== $requestedId) {
            $organization = $memberships[(int) $requestedId] ?? null;
            if (null === $organization) {
                return null;
            }

            return new ResolvedTenant($organization->getId(), $organization->getSlug(), $organization->getPrimaryDomain());
        }

        ksort($memberships, SORT_NUMERIC);
        $organization = reset($memberships);

        return new ResolvedTenant($organization->getId(), $organization->getSlug(), $organization->getPrimaryDomain());
    }
}
