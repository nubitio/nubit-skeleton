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
    public const CUSTOMER_READ = 'APP_CUSTOMER_READ';
    public const CUSTOMER_WRITE = 'APP_CUSTOMER_WRITE';
    public const CUSTOMER_DELETE = 'APP_CUSTOMER_DELETE';
    public const SALES_DOCUMENT_READ = 'APP_SALES_DOCUMENT_READ';
    public const SALES_DOCUMENT_WRITE = 'APP_SALES_DOCUMENT_WRITE';
    public const SALES_DOCUMENT_DELETE = 'APP_SALES_DOCUMENT_DELETE';
    public const SALES_DOCUMENT_LINE_READ = 'APP_SALES_DOCUMENT_LINE_READ';
    public const INVOICE_READ = 'APP_INVOICE_READ';
    public const INVOICE_WRITE = 'APP_INVOICE_WRITE';
    public const INVOICE_DELETE = 'APP_INVOICE_DELETE';
    public const INVOICE_LINE_READ = 'APP_INVOICE_LINE_READ';
    public const INVOICE_PAY = 'APP_INVOICE_PAY';
    public const INVOICE_CANCEL = 'APP_INVOICE_CANCEL';
    public const INVOICE_REOPEN = 'APP_INVOICE_REOPEN';

    /** @var list<string> */
    public const CAPABILITIES = [
        self::PRODUCT_READ,
        self::PRODUCT_MANAGE,
        self::CUSTOMER_READ,
        self::CUSTOMER_WRITE,
        self::CUSTOMER_DELETE,
        self::SALES_DOCUMENT_READ,
        self::SALES_DOCUMENT_WRITE,
        self::SALES_DOCUMENT_DELETE,
        self::SALES_DOCUMENT_LINE_READ,
        self::INVOICE_READ,
        self::INVOICE_WRITE,
        self::INVOICE_DELETE,
        self::INVOICE_LINE_READ,
        self::INVOICE_PAY,
        self::INVOICE_CANCEL,
        self::INVOICE_REOPEN,
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
            self::PRODUCT_READ,
            self::CUSTOMER_READ,
            self::CUSTOMER_WRITE,
            self::SALES_DOCUMENT_READ,
            self::SALES_DOCUMENT_WRITE,
            self::SALES_DOCUMENT_LINE_READ,
            self::INVOICE_READ,
            self::INVOICE_WRITE,
            self::INVOICE_LINE_READ,
                => true,
            self::PRODUCT_MANAGE,
            self::CUSTOMER_DELETE,
            self::SALES_DOCUMENT_DELETE,
            self::INVOICE_DELETE,
            self::INVOICE_PAY,
            self::INVOICE_CANCEL,
            self::INVOICE_REOPEN,
                => $membership->isAdmin(),
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
