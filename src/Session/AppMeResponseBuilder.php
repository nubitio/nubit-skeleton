<?php

declare(strict_types=1);

namespace App\Session;

use App\Security\OrganizationAuthorization;
use Nubit\AdminBundle\Session\MeResponseBuilderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Extends /api/me with a granular permissions map.
 *
 * The frontend reads this via useSession().profile.permissions and uses it
 * to show/hide UI actions (buttons, menu items, form sections). The real
 * security gates remain in Symfony — this is UX-only mirroring.
 *
 * Pattern for ERP modules:
 *   'module.action' => bool
 *
 * This mirrors OrganizationAuthorization; API voters remain the real gate.
 */
final readonly class AppMeResponseBuilder implements MeResponseBuilderInterface
{
    public function __construct(
        private MeResponseBuilderInterface $inner,
        private OrganizationAuthorization $authorization,
    ) {}

    public function build(UserInterface $user): array
    {
        $response = $this->inner->build($user);
        $response['permissions'] = $this->resolvePermissions($user);
        $membership = $this->authorization->activeMembership($user);
        if (null !== $membership && null !== ($organization = $membership->getOrganization())) {
            $response['organization'] = [
                'id' => $organization->getId(),
                'name' => $organization->getName(),
                'slug' => $organization->getSlug(),
                'role' => $membership->getRole(),
            ];
        }

        return $response;
    }

    /**
     * Maps the active organization membership to fine-grained permission keys.
     * Keep this list sorted alphabetically for easy auditing.
     *
     * @return array<string, bool>
     */
    private function resolvePermissions(UserInterface $user): array
    {
        $can = fn(string $capability): bool => $this->authorization->isGranted($user, $capability);

        return [
            // Invoices
            'invoice.create' => $can(OrganizationAuthorization::INVOICE_WRITE),
            'invoice.edit' => $can(OrganizationAuthorization::INVOICE_WRITE),
            'invoice.confirm' => $can(OrganizationAuthorization::INVOICE_WRITE),
            'invoice.pay' => $can(OrganizationAuthorization::INVOICE_PAY),
            'invoice.cancel' => $can(OrganizationAuthorization::INVOICE_CANCEL),
            'invoice.delete' => $can(OrganizationAuthorization::INVOICE_DELETE),

            // Products / catalog
            'catalog.manage' => $can(OrganizationAuthorization::PRODUCT_MANAGE),

            // Customers
            'customer.manage' => $can(OrganizationAuthorization::CUSTOMER_WRITE),

            // Reports (future)
            'report.view' => $can(OrganizationAuthorization::INVOICE_READ),
            'report.export' => $can(OrganizationAuthorization::PRODUCT_MANAGE),
        ];
    }
}
