<?php

declare(strict_types=1);

namespace App\Tenant;

use App\Entity\OrganizationMembership;

interface OrganizationMembershipUserInterface
{
    /** @return iterable<OrganizationMembership> */
    public function getOrganizationMemberships(): iterable;
}
