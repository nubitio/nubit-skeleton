<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Product;
use App\Entity\SalesDocument;
use App\Entity\SalesDocumentLine;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:seed', description: 'Creates the demo admin user and sample products.')]
final class SeedCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $userRepo = $this->entityManager->getRepository(User::class);
        if (null === $userRepo->findOneBy(['email' => 'admin@example.com'])) {
            $user = new User()
                ->setEmail('admin@example.com')
                ->setRoles(['ROLE_ADMIN']);
            $user->setPassword($this->passwordHasher->hashPassword($user, 'admin1234'));
            $this->entityManager->persist($user);
            $io->success('User admin@example.com created (password: admin1234).');
        } else {
            $io->note('User admin@example.com already exists.');
        }

        $productRepo = $this->entityManager->getRepository(Product::class);
        if (0 === $productRepo->count([])) {
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
            $io->success('5 sample products created.');
        } else {
            $io->note('Products already seeded.');
        }

        $salesRepo = $this->entityManager->getRepository(SalesDocument::class);
        if (0 === $salesRepo->count([])) {
            $products = $productRepo->findBy([], ['id' => 'ASC'], 2);
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
