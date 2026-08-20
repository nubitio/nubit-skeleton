<?php

declare(strict_types=1);

namespace App\Command;

use App\Security\ProductionReadiness;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(name: 'app:doctor', description: 'Checks whether template defaults are safe for the active environment.')]
final class DoctorCommand extends Command
{
    public function __construct(
        private readonly ProductionReadiness $readiness,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment,
        #[Autowire('%env(resolve:APP_SECRET)%')]
        private readonly string $appSecret,
        #[Autowire('%env(resolve:DATABASE_URL)%')]
        private readonly string $databaseUrl,
        #[Autowire('%env(resolve:MERCURE_JWT_SECRET)%')]
        private readonly string $mercureSecret,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('strict', null, InputOption::VALUE_NONE, 'Fail on unsafe defaults outside production too.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $issues = $this->readiness->inspect($this->appSecret, $this->databaseUrl, $this->mercureSecret);

        if ([] === $issues) {
            $io->success(sprintf('No unsafe template defaults found for %s.', $this->environment));

            return Command::SUCCESS;
        }

        $io->warning($issues);
        if ('prod' === $this->environment || true === $input->getOption('strict')) {
            $io->error('Refusing production readiness with unsafe template defaults.');

            return Command::FAILURE;
        }

        $io->note('Template defaults are allowed in dev/test. Run app:doctor --strict before deployment.');

        return Command::SUCCESS;
    }
}
