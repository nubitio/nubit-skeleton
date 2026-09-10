<?php

declare(strict_types=1);

namespace App\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

final class ProductionReadinessGuard
{
    public function __construct(
        private readonly ProductionReadiness $readiness,
        #[Autowire('%env(resolve:APP_SECRET)%')]
        #[\SensitiveParameter]
        private readonly string $appSecret,
        #[Autowire('%env(resolve:DATABASE_URL)%')]
        #[\SensitiveParameter]
        private readonly string $databaseUrl,
        #[Autowire('%env(resolve:MERCURE_JWT_SECRET)%')]
        #[\SensitiveParameter]
        private readonly string $mercureSecret,
    ) {
    }

    public function assertReady(): void
    {
        $issues = $this->readiness->inspect($this->appSecret, $this->databaseUrl, $this->mercureSecret);
        if ([] !== $issues) {
            throw new \LogicException(sprintf(
                "Unsafe production configuration:\n- %s",
                implode("\n- ", $issues),
            ));
        }
    }
}
