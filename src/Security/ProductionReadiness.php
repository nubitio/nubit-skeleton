<?php

declare(strict_types=1);

namespace App\Security;

final class ProductionReadiness
{
    /** @return list<string> */
    public function inspect(string $appSecret, string $databaseUrl, string $mercureSecret): array
    {
        $issues = [];

        if (strlen($appSecret) < 32) {
            $issues[] = 'APP_SECRET must contain at least 32 bytes.';
        }
        if (str_contains(strtolower($appSecret), 'change-me')) {
            $issues[] = 'APP_SECRET still uses the template value.';
        }
        if (str_contains(strtolower($databaseUrl), 'changeme')) {
            $issues[] = 'DATABASE_URL still uses the template password.';
        }
        if (str_contains(strtolower($mercureSecret), 'changethis')) {
            $issues[] = 'MERCURE_JWT_SECRET still uses the template value.';
        }

        return $issues;
    }
}
