<?php

declare(strict_types=1);

use Nubit\ApiPlatform\Doctrine\Filter\GridFilterHelper;

require dirname(__DIR__) . '/vendor/autoload.php';

$fixturePath = $argv[1] ?? null;
if (!is_string($fixturePath) || !is_file($fixturePath)) {
    fwrite(stream: STDERR, data: "PHP: fixture file not found\n");
    exit(1);
}

try {
    /** @var array{operatorCases?: list<array{name?: mixed, operator?: mixed, value?: mixed, dqlOperator?: mixed, boundValue?: mixed}>} $fixtures */
    $fixtures = json_decode(
        json: (string) file_get_contents($fixturePath),
        associative: true,
        flags: JSON_THROW_ON_ERROR,
    );
} catch (Throwable $error) {
    fwrite(stream: STDERR, data: sprintf("PHP: invalid fixture JSON: %s\n", $error->getMessage()));
    exit(1);
}

$failures = [];
$operatorCases = $fixtures['operatorCases'] ?? null;
if (!is_array($operatorCases) || [] === $operatorCases) {
    $failures[] = 'PHP: operatorCases must be a non-empty array';
}
foreach (is_array($operatorCases) ? $operatorCases : [] as $index => $fixture) {
    $name = is_string($fixture['name'] ?? null) ? $fixture['name'] : sprintf('operatorCases[%d]', $index);
    $operator = $fixture['operator'] ?? null;
    if (!is_string($operator)) {
        $failures[] = sprintf('PHP: %s: operator must be a string', $name);
        continue;
    }

    try {
        $actualOperator = GridFilterHelper::dqlOperator($operator);
        $actualValue = GridFilterHelper::valueForOperator($operator, $fixture['value'] ?? null);
        if ($actualOperator !== ($fixture['dqlOperator'] ?? null)) {
            $failures[] = sprintf(
                'PHP: %s: DQL operator expected %s, got %s',
                $name,
                var_export(value: $fixture['dqlOperator'] ?? null, return: true),
                var_export(value: $actualOperator, return: true),
            );
        }
        if ($actualValue !== ($fixture['boundValue'] ?? null)) {
            $failures[] = sprintf(
                'PHP: %s: bound value expected %s, got %s',
                $name,
                var_export(value: $fixture['boundValue'] ?? null, return: true),
                var_export(value: $actualValue, return: true),
            );
        }
    } catch (Throwable $error) {
        $failures[] = sprintf('PHP: %s: %s', $name, $error->getMessage());
    }
}

if ([] !== $failures) {
    fwrite(stream: STDERR, data: implode("\n", $failures) . "\n");
    exit(1);
}

printf("PHP: %d operator fixtures passed\n", count($fixtures['operatorCases'] ?? []));
