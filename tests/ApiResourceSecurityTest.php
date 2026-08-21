<?php

declare(strict_types=1);

namespace App\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Every exposed resource has to say who may reach it.
 *
 * API Platform leaves an operation open when no `security:` expression is
 * given, so a resource added without one is world-readable to any authenticated
 * session — the kind of gap that is invisible until it matters. This walks
 * src/Entity and fails on the first one that forgot.
 */
final class ApiResourceSecurityTest extends TestCase
{
    /** @return iterable<string, array{string, string}> */
    public static function apiResources(): iterable
    {
        $dir = \dirname(__DIR__) . '/src/Entity';
        foreach (glob($dir . '/*.php') ?: [] as $file) {
            $source = (string) file_get_contents($file);
            if (!str_contains($source, '#[ApiResource')) {
                continue;
            }

            yield basename($file, '.php') => [basename($file, '.php'), $source];
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('apiResources')]
    public function testEveryOperationDeclaresSecurity(string $entity, string $source): void
    {
        preg_match_all(
            '/new (Get|GetCollection|Post|Patch|Put|Delete)\s*\(([^)]*)\)/',
            $source,
            $matches,
            PREG_SET_ORDER,
        );

        self::assertNotSame([], $matches, sprintf('%s exposes no operations to check.', $entity));

        foreach ($matches as [$whole, $operation, $arguments]) {
            self::assertStringContainsString(
                'security:',
                $arguments,
                sprintf('%s::%s has no security expression.', $entity, $operation),
            );
        }
    }

    /** At least one resource must exist, or the check above passes vacuously. */
    public function testTheTemplateExposesItsReferenceResource(): void
    {
        $names = array_keys(iterator_to_array(self::apiResources()));

        self::assertContains('Product', $names);
    }
}
