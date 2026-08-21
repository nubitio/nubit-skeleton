<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Organization;
use App\Entity\OrganizationMembership;
use App\Entity\User;
use App\Security\OrganizationAuthorization;
use App\Tenant\OrganizationTenantResolver;
use Nubit\Platform\Tenant\Context\TenantContext;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Yaml\Yaml;

final class SaasTenantContractTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = dirname(__DIR__);
    }

    /**
     * Every tenant-owned entity the template ships. Add yours here: the two
     * checks below are what keep a new resource from leaking across tenants.
     */
    private const array TENANT_OWNED_ENTITIES = ['Product'];

    public function testSaasTenantBundleAndColumnIsolationAreConfigured(): void
    {
        $admin = Yaml::parseFile($this->projectDir . '/config/packages/nubit_admin.yaml');
        $tenant = Yaml::parseFile($this->projectDir . '/config/packages/nubit_tenant.yaml');
        $bundles = require $this->projectDir . '/config/bundles.php';

        self::assertSame('saas', $admin['nubit_admin']['app_profile'] ?? null);
        self::assertTrue($tenant['nubit_tenant']['enabled'] ?? false);
        self::assertSame('column', $tenant['nubit_tenant']['isolation'] ?? null);
        self::assertSame('App\\Entity\\Organization', $tenant['nubit_tenant']['tenant_entity'] ?? null);
        self::assertArrayHasKey('Nubit\\TenantBundle\\NubitTenantBundle', $bundles);
    }

    public function testBusinessEntitiesUseServerStampedTenantOwnership(): void
    {
        foreach (self::TENANT_OWNED_ENTITIES as $entity) {
            $source = (string) file_get_contents($this->projectDir . '/src/Entity/' . $entity . '.php');
            self::assertStringContainsString('implements TenantOwnedInterface', $source, $entity);
            self::assertStringContainsString('use TenantOwnedTrait;', $source, $entity);
        }
    }

    public function testRequestedOrganizationMustBeAnActiveMembership(): void
    {
        $user = new User();
        $active = $this->organization(20, 'active');
        $inactive = $this->organization(10, 'inactive');
        $user->addOrganizationMembership($this->membership($active, 'active'));
        $user->addOrganizationMembership($this->membership($inactive, 'inactive'));
        $resolver = new OrganizationTenantResolver();

        self::assertSame(
            20,
            $resolver->resolve(Request::create('/', server: ['HTTP_X_ORGANIZATION_ID' => '20']), $user)?->id,
        );
        self::assertNull($resolver->resolve(Request::create('/', server: ['HTTP_X_ORGANIZATION_ID' => '10']), $user));
        self::assertNull($resolver->resolve(Request::create('/', server: ['HTTP_X_ORGANIZATION_ID' => '999']), $user));
    }

    public function testDefaultOrganizationIsTheLowestActiveOrganizationId(): void
    {
        $user = new User();
        $user->addOrganizationMembership($this->membership($this->organization(20, 'second'), 'active'));
        $user->addOrganizationMembership($this->membership($this->organization(5, 'first'), 'active'));

        self::assertSame(5, (new OrganizationTenantResolver())->resolve(Request::create('/'), $user)?->id);
    }

    public function testPermissionsAreScopedToTheActiveOrganizationMembership(): void
    {
        $user = (new User())->setRoles(['ROLE_ADMIN']);
        $adminOrganization = $this->organization(1, 'admin');
        $memberOrganization = $this->organization(2, 'member');
        $user->addOrganizationMembership($this->membership(
            $adminOrganization,
            'active',
            OrganizationMembership::ROLE_ADMIN,
        ));
        $user->addOrganizationMembership($this->membership(
            $memberOrganization,
            'active',
            OrganizationMembership::ROLE_MEMBER,
        ));
        $context = new TenantContext();
        $authorization = new OrganizationAuthorization($context);

        $context->setTenant(1, 'admin', null, null);
        self::assertTrue($authorization->isGranted($user, OrganizationAuthorization::PRODUCT_MANAGE));

        $context->setTenant(2, 'member', null, null);
        self::assertFalse($authorization->isGranted($user, OrganizationAuthorization::PRODUCT_MANAGE));
        self::assertTrue($authorization->isGranted($user, OrganizationAuthorization::INVOICE_WRITE));

        $context->setTenant(999, 'missing', null, null);
        self::assertFalse($authorization->isGranted($user, OrganizationAuthorization::INVOICE_READ));
    }

    public function testTenantResourcesDeclareOperationSecurity(): void
    {
        foreach (self::TENANT_OWNED_ENTITIES as $entity) {
            $source = (string) file_get_contents($this->projectDir . '/src/Entity/' . $entity . '.php');
            self::assertStringContainsString("security: \"is_granted('APP_", $source, $entity);
            self::assertStringNotContainsString("is_granted('ROLE_ADMIN')", $source, $entity);
        }
    }

    private function organization(int $id, string $slug): Organization
    {
        $organization = (new Organization())
            ->setName($slug)
            ->setSlug($slug);
        $this->setId($organization, $id);

        return $organization;
    }

    private function membership(
        Organization $organization,
        string $status,
        string $role = OrganizationMembership::ROLE_MEMBER,
    ): OrganizationMembership {
        return (new OrganizationMembership())
            ->setOrganization($organization)
            ->setStatus($status)
            ->setRole($role);
    }

    private function setId(object $entity, int $id): void
    {
        $property = new \ReflectionProperty($entity, 'id');
        $property->setValue($entity, $id);
    }
}
