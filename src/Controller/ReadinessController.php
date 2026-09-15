<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Throwable;

final readonly class ReadinessController
{
    public function __construct(
        private Connection $connection,
        private LoggerInterface $logger,
    ) {}

    #[Route('/api/ready', name: 'app_readiness', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        try {
            $this->connection->executeQuery('SELECT 1')->fetchOne();

            return new JsonResponse(['status' => 'ready']);
        } catch (Throwable $error) {
            $this->logger->error('Database readiness check failed.', [
                'dependency' => 'database',
                'exception_class' => $error::class,
            ]);

            return new JsonResponse([
                'status' => 'unavailable',
                'dependency' => 'database',
            ], Response::HTTP_SERVICE_UNAVAILABLE);
        }
    }
}
