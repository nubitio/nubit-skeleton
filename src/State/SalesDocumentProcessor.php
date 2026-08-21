<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\State\ProcessorInterface;
use App\Entity\SalesDocument;
use Nubit\AdminBundle\State\AbstractEmbeddedLinesProcessor;

/**
 * Binds embedded lines to their parent and recomputes monetary totals on every save.
 *
 * @extends AbstractEmbeddedLinesProcessor<SalesDocument, SalesDocumentLine>
 */
final readonly class SalesDocumentProcessor extends AbstractEmbeddedLinesProcessor
{
    /** @param ProcessorInterface<mixed, mixed> $persistProcessor */
    public function __construct(ProcessorInterface $persistProcessor)
    {
        parent::__construct($persistProcessor);
    }

    protected function supports(mixed $data): bool
    {
        return $data instanceof SalesDocument;
    }

    protected function linesProperty(): string
    {
        return 'lines';
    }

    protected function lineSetter(): string
    {
        return 'setDocument';
    }

    protected function afterLinesSynced(mixed $data): void
    {
        if ($data instanceof SalesDocument) {
            $data->recalculateTotal();
        }
    }
}
