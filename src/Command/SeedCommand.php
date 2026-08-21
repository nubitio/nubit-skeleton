<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Organization;
use App\Entity\OrganizationMembership;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Nubit\Platform\Tenant\Context\TenantContext;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:seed',
    description: 'Creates the demo organization, admin membership, and tenant-owned sample data.',
)]
final class SeedCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly TenantContext $tenantContext,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $organizationRepo = $this->entityManager->getRepository(Organization::class);
        $organization = $organizationRepo->findOneBy(['slug' => 'demo']);
        if (!$organization instanceof Organization) {
            $organization = (new Organization())
                ->setName('Demo Organization')
                ->setSlug('demo');
            $this->entityManager->persist($organization);
            $this->entityManager->flush();
            $io->success('Demo organization created.');
        }

        $userRepo = $this->entityManager->getRepository(User::class);
        $user = $userRepo->findOneBy(['email' => 'admin@example.com']);
        if (!$user instanceof User) {
            $user = (new User())
                ->setEmail('admin@example.com')
                ->setRoles(['ROLE_ADMIN']);
            $user->setPassword($this->passwordHasher->hashPassword($user, 'admin1234'));
            $this->entityManager->persist($user);
            $io->success('User admin@example.com created (password: admin1234).');
        } else {
            $io->note('User admin@example.com already exists.');
        }

        $membership = null;
        foreach ($user->getOrganizationMemberships() as $candidate) {
            if ($candidate->getOrganization()?->getId() === $organization->getId()) {
                $membership = $candidate;
                break;
            }
        }
        if (!$membership instanceof OrganizationMembership) {
            $membership = (new OrganizationMembership())
                ->setOrganization($organization)
                ->setStatus('active')
                ->setRole(OrganizationMembership::ROLE_ADMIN);
            $user->addOrganizationMembership($membership);
            $this->entityManager->persist($membership);
            $io->success('Admin membership for the demo organization created.');
        } else {
            $membership->setStatus('active')->setRole(OrganizationMembership::ROLE_ADMIN);
        }

        $this->entityManager->flush();
        $organizationId = $organization->getId();
        if (null === $organizationId) {
            throw new \LogicException('The demo organization must be persisted before tenant-owned data is seeded.');
        }
        $this->tenantContext->setTenant(
            $organizationId,
            $organization->getSlug(),
            $organization->getPrimaryDomain(),
            null,
        );

        $productRepo = $this->entityManager->getRepository(Product::class);
        if (0 === $productRepo->count(['tenantId' => $organizationId])) {
            $samples = [
                ['Espresso Machine',  'SKU-001', '450.00'],
                ['Coffee Grinder',    'SKU-002', '129.90'],
                ['Milk Frother',      'SKU-003', '39.50'],
                ['Barista Kit',       'SKU-004', '89.00'],
                ['Arabica Beans 1kg', 'SKU-005', '24.99'],
            ];
            foreach ($samples as [$name, $sku, $price]) {
                $this->entityManager->persist(
                    (new Product())
                        ->setName($name)
                        ->setSku($sku)
                        ->setPrice($price),
                );
            }
            $this->entityManager->flush();
            $io->success('5 sample products created.');
        } else {
            $io->note('Products already seeded.');
        }

        $this->entityManager->flush();

        return Command::SUCCESS;
    }
}
