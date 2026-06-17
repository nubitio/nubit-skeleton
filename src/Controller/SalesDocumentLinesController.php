<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\SalesDocumentLine;
use App\Repository\SalesDocumentLineRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Plain JSON array for SmartCrud formDetail reload — the form expects
 * response.data to be a row list, not a Hydra collection envelope.
 */
final class SalesDocumentLinesController
{
    #[Route('/api/sales_document_lines', name: 'app_sales_document_lines', methods: ['GET'])]
    public function __invoke(Request $request, SalesDocumentLineRepository $lines): JsonResponse
    {
        $documentId = $request->query->getInt('document');
        if ($documentId <= 0) {
            return new JsonResponse([]);
        }

        $rows = $lines->findBy(['document' => $documentId], ['id' => 'ASC']);

        return new JsonResponse(array_map(
            static fn (SalesDocumentLine $line): array => [
                'id' => $line->getId(),
                'product' => null !== $line->getProduct()?->getId()
                    ? '/api/products/' . $line->getProduct()->getId()
                    : null,
                'quantity' => $line->getQuantity(),
                'unitPrice' => $line->getUnitPrice(),
                'lineTotal' => $line->getLineTotal(),
            ],
            $rows,
        ));
    }
}