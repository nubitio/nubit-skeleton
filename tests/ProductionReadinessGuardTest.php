<?php

declare(strict_types=1);

namespace App\Tests;

use App\Security\ProductionReadiness;
use App\Security\ProductionReadinessGuard;
use LogicException;
use PHPUnit\Framework\TestCase;

final class ProductionReadinessGuardTest extends TestCase
{
    public function testUnsafeConfigurationIsRejected(): void
    {
        $guard = new ProductionReadinessGuard(
            new ProductionReadiness(),
            'change-me-to-a-random-32-byte-secret!!',
            'postgresql://app:!ChangeMe!@database/app',
            '!ChangeThisMercureHubJWTSecretKey!',
        );

        $this->expectException(LogicException::class);
        $guard->assertReady();
    }

    public function testGeneratedConfigurationIsAccepted(): void
    {
        $guard = new ProductionReadinessGuard(
            new ProductionReadiness(),
            str_repeat('a', 64),
            'postgresql://app:a-random-password@database/app',
            str_repeat('b', 64),
        );

        $guard->assertReady();
        self::assertSame(
            [],
            (new ProductionReadiness())->inspect(
                str_repeat('a', 64),
                'postgresql://app:a-random-password@database/app',
                str_repeat('b', 64),
            ),
        );
    }
}
