<?php

declare(strict_types=1);

use OpenTelemetry\API\Globals;
use OpenTelemetry\API\Trace\StatusCode;

require dirname(__DIR__).'/vendor/autoload.php';

$span = Globals::tracerProvider()
    ->getTracer('nubitio/skeleton-smoke')
    ->spanBuilder('nubit.observability.smoke')
    ->startSpan();

try {
    $span->setAttribute('nubit.smoke', true);
    $span->setAttribute('deployment.environment', $_SERVER['APP_ENV'] ?? 'dev');
    $span->setStatus(StatusCode::STATUS_OK);
} finally {
    $span->end();
}

fwrite(STDOUT, "OpenTelemetry smoke span completed.\n");
