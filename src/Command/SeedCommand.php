<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Product;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:seed', description: 'Creates sample products and, when explicitly requested, an administrator.')]
final class SeedCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('admin-email', null, InputOption::VALUE_REQUIRED, 'Email for a new administrator.')
            ->addOption('admin-password', null, InputOption::VALUE_REQUIRED, 'Password for a new administrator.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $adminEmailOption = $input->getOption('admin-email');
        $adminPasswordOption = $input->getOption('admin-password');
        if ((null !== $adminEmailOption && !is_string($adminEmailOption)) || (null !== $adminPasswordOption && !is_string($adminPasswordOption))) {
            $io->error('Administrator credentials must be strings.');

            return Command::INVALID;
        }
        $adminEmail = $adminEmailOption;
        $adminPassword = $adminPasswordOption;
        if (null !== $adminEmail xor null !== $adminPassword) {
            $io->error('Both --admin-email and --admin-password are required to create an administrator.');

            return Command::INVALID;
        }
        if (null !== $adminPassword && strlen($adminPassword) < 16) {
            $io->error('Administrator passwords must contain at least 16 bytes.');

            return Command::INVALID;
        }
        if (null !== $adminEmail && !filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
            $io->error('The administrator email address is invalid.');

            return Command::INVALID;
        }

        if (null !== $adminEmail) {
            $userRepo = $this->entityManager->getRepository(User::class);
            $user = $userRepo->findOneBy(['email' => $adminEmail]);
            if (!$user instanceof User) {
                $user = (new User())
                    ->setEmail($adminEmail)
                    ->setRoles(['ROLE_ADMIN']);
                $user->setPassword($this->passwordHasher->hashPassword($user, $adminPassword));
                $this->entityManager->persist($user);
                $io->success(sprintf('Administrator %s created.', $adminEmail));
            } else {
                $io->note(sprintf('User %s already exists.', $adminEmail));
            }
        }

        $productRepo = $this->entityManager->getRepository(Product::class);
        if (0 === $productRepo->count([])) {
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
            $io->success('5 sample products created.');
        } else {
            $io->note('Products already seeded.');
        }

        $this->entityManager->flush();

        return Command::SUCCESS;
    }
}
