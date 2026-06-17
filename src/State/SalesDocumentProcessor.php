<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\SalesDocument;
use App\Entity\SalesDocumentLine;

/**
 * Binds embedded lines to their parent and recomputes monetary totals on every save.
 *
 * @implements ProcessorInterface<SalesDocument, SalesDocument>
 */
final readonly class SalesDocumentProcessor implements ProcessorInterface
{
    /** @param ProcessorInterface<mixed, mixed> $persistProcessor */
    public function __construct(
        private ProcessorInterface $persistProcessor,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof SalesDocument) {
            $this->syncLines($data);
            $data->recalculateTotal();
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }

    private function syncLines(SalesDocument $document): void
    {
        foreach ($document->getLines() as $line) {
            if (!$line instanceof SalesDocumentLine) {
                continue;
            }

            $line->setDocument($document);
        }
    }
}