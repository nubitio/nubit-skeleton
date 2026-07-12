<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Customer;
use App\Entity\Organization;
use App\Entity\OrganizationMembership;
use App\Entity\Product;
use App\Entity\SalesDocument;
use App\Entity\SalesDocumentLine;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Nubit\Platform\Tenant\Context\TenantContext;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:seed', description: 'Creates the demo organization, admin membership, and tenant-owned sample data.')]
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
            $user = new User()
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
            $membership
                ->setStatus('active')
                ->setRole(OrganizationMembership::ROLE_ADMIN);
        }

        $this->entityManager->flush();
        $organizationId = $organization->getId();
        if (null === $organizationId) {
            throw new \LogicException('The demo organization must be persisted before tenant-owned data is seeded.');
        }
        $this->tenantContext->setTenant($organizationId, $organization->getSlug(), $organization->getPrimaryDomain(), null);

        $productRepo = $this->entityManager->getRepository(Product::class);
        if (0 === $productRepo->count(['tenantId' => $organizationId])) {
            $samples = [
                ['Espresso Machine', 'SKU-001', '450.00'],
                ['Coffee Grinder', 'SKU-002', '129.90'],
                ['Milk Frother', 'SKU-003', '39.50'],
                ['Barista Kit', 'SKU-004', '89.00'],
                ['Arabica Beans 1kg', 'SKU-005', '24.99'],
            ];
            foreach ($samples as [$name, $sku, $price]) {
                $this->entityManager->persist(
                    new Product()->setName($name)->setSku($sku)->setPrice($price),
                );
            }
            $this->entityManager->flush();
            $io->success('5 sample products created.');
        } else {
            $io->note('Products already seeded.');
        }

        $customerRepo = $this->entityManager->getRepository(Customer::class);
        if (0 === $customerRepo->count(['tenantId' => $organizationId])) {
            $products = $productRepo->findBy(['tenantId' => $organizationId], ['id' => 'ASC'], 2);
            $preferred = $products[0] ?? null;
            $samples = [
                ['Acme Retail', 'retail@acme.example', 'retail'],
                ['Global Wholesale Co.', 'sales@globalwholesale.example', 'wholesale'],
                ['Enterprise Systems Ltd.', 'procurement@enterprise.example', 'enterprise'],
            ];
            foreach ($samples as [$name, $email, $segment]) {
                $customer = new Customer()
                    ->setName($name)
                    ->setEmail($email)
                    ->setSegment($segment);
                if ($preferred) {
                    $customer->setPreferredProduct($preferred);
                }
                $this->entityManager->persist($customer);
            }
            $io->success('3 sample customers created.');
        } else {
            $io->note('Customers already seeded.');
        }

        $salesRepo = $this->entityManager->getRepository(SalesDocument::class);
        if (0 === $salesRepo->count(['tenantId' => $organizationId])) {
            $products = $productRepo->findBy(['tenantId' => $organizationId], ['id' => 'ASC'], 2);
            if (\count($products) >= 2) {
                $document = new SalesDocument()
                    ->setNumber('SD-0001')
                    ->setStatus('confirmed');
                $document->addLine(
                    (new SalesDocumentLine())
                        ->setProduct($products[0])
                        ->setQuantity('2.00')
                        ->setUnitPrice($products[0]->getPrice()),
                );
                $document->addLine(
                    (new SalesDocumentLine())
                        ->setProduct($products[1])
                        ->setQuantity('1.00')
                        ->setUnitPrice($products[1]->getPrice()),
                );
                $document->recalculateTotal();
                $this->entityManager->persist($document);
                $io->success('Sample sales document SD-0001 created.');
            }
        } else {
            $io->note('Sales documents already seeded.');
        }

        $this->entityManager->flush();

        return Command::SUCCESS;
    }
}
