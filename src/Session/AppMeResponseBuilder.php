<?php

declare(strict_types=1);

namespace App\Session;

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
 * Add new permissions here as the ERP grows. Role checks stay centralized
 * so changing who can approve invoices is a one-line edit.
 */
final readonly class AppMeResponseBuilder implements MeResponseBuilderInterface
{
    public function __construct(
        private MeResponseBuilderInterface $inner,
    ) {
    }

    public function build(UserInterface $user): array
    {
        $response = $this->inner->build($user);
        $roles = $user->getRoles();

        $response['permissions'] = $this->resolvePermissions($roles);

        return $response;
    }

    /**
     * Maps roles to fine-grained permission keys.
     * Keep this list sorted alphabetically for easy auditing.
     *
     * @param list<string> $roles
     * @return array<string, bool>
     */
    private function resolvePermissions(array $roles): array
    {
        $isAdmin = in_array('ROLE_ADMIN', $roles, true);
        $isUser  = in_array('ROLE_USER', $roles, true) || $isAdmin;

        return [
            // Invoices
            'invoice.create'  => $isUser,
            'invoice.edit'    => $isUser,
            'invoice.confirm' => $isUser,
            'invoice.pay'     => $isAdmin,
            'invoice.cancel'  => $isAdmin,
            'invoice.delete'  => $isAdmin,

            // Products / catalog
            'catalog.manage'  => $isAdmin,

            // Customers
            'customer.manage' => $isUser,

            // Reports (future)
            'report.view'     => $isUser,
            'report.export'   => $isAdmin,
        ];
    }
}
