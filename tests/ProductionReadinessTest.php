<?php

declare(strict_types=1);

namespace App\Tests;

use App\Security\ProductionReadiness;
use PHPUnit\Framework\TestCase;

final class ProductionReadinessTest extends TestCase
{
    public function testTemplateDefaultsAreRejected(): void
    {
        $issues = (new ProductionReadiness())->inspect(
            'change-me-to-a-random-32-byte-secret!!',
            'postgresql://app:!ChangeMe!@database/app',
            '!ChangeThisMercureHubJWTSecretKey!',
        );

        self::assertCount(3, $issues);
    }

    public function testGeneratedSecretsAndDatabasePasswordPass(): void
    {
        $issues = (new ProductionReadiness())->inspect(
            str_repeat('a', 64),
            'postgresql://app:a-random-password@database/app',
            str_repeat('b', 64),
        );

        self::assertSame([], $issues);
    }
}
