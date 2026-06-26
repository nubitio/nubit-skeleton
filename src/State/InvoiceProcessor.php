<?php

declare(strict_types=1);

namespace App\State;

use ApiPlatform\State\ProcessorInterface;
use App\Entity\Invoice;
use Nubit\AdminBundle\State\AbstractEmbeddedLinesProcessor;

/**
 * Binds invoice lines to their parent and recomputes subtotal / tax / total.
 *
 * Extend afterLinesSynced() to add domain rules:
 *   - fiscal period validation
 *   - credit limit checks
 *   - external API calls (e.g. tax authority)
 *
 * @extends AbstractEmbeddedLinesProcessor<Invoice, \App\Entity\InvoiceLine>
 */
final readonly class InvoiceProcessor extends AbstractEmbeddedLinesProcessor
{
    /** @param ProcessorInterface<mixed, mixed> $persistProcessor */
    public function __construct(ProcessorInterface $persistProcessor)
    {
        parent::__construct($persistProcessor);
    }

    protected function supports(mixed $data): bool
    {
        return $data instanceof Invoice;
    }

    protected function linesProperty(): string
    {
        return 'lines';
    }

    protected function lineSetter(): string
    {
        return 'setInvoice';
    }

    protected function afterLinesSynced(mixed $data): void
    {
        if ($data instanceof Invoice) {
            $data->recalculateTotals();
        }
    }
}
