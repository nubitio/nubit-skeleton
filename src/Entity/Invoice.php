<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post;
use App\State\InvoiceProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nubit\ApiPlatform\Attribute\Auditable;
use Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter;
use Nubit\SequenceBundle\Attribute\Sequence;
use Nubit\WorkflowBundle\Attribute\Workflow;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Reference entity for ERP document patterns.
 *
 * Combines:
 *   - #[Sequence]  → auto-number on first persist (INV-0001, INV-0002…)
 *   - #[Workflow]  → state machine with role guards per transition
 *   - #[Auditable] → field-level diff history per row
 *   - Embedded InvoiceLine collection → master-detail form
 *
 * Copy this pattern for: purchase orders, receipts, credit notes, payroll runs.
 */
#[Sequence(field: 'number', name: 'invoice', prefix: 'INV-', padding: 4)]
#[Workflow(
    field: 'status',
    transitions: [
        'confirm' => [
            'from' => ['draft'],
            'to'   => 'confirmed',
            'label' => 'Confirm',
        ],
        'mark_paid' => [
            'from' => ['confirmed'],
            'to'   => 'paid',
            'label' => 'Mark as paid',
            'roles' => ['ROLE_ADMIN'],
        ],
        'cancel' => [
            'from' => ['draft', 'confirmed'],
            'to'   => 'cancelled',
            'label' => 'Cancel',
            'roles' => ['ROLE_ADMIN'],
        ],
        'reopen' => [
            'from' => ['cancelled'],
            'to'   => 'draft',
            'label' => 'Reopen',
            'roles' => ['ROLE_ADMIN'],
        ],
    ],
)]
#[Auditable(resource: 'invoice')]
#[ORM\Entity]
#[ORM\Table(name: 'invoice')]
#[ApiResource(
    operations: [new GetCollection(), new Post(), new Get(), new Patch(), new Delete()],
    mercure: true,
    paginationClientItemsPerPage: true,
    normalizationContext: ['groups' => ['invoice:read']],
    denormalizationContext: ['groups' => ['invoice:write']],
    processor: InvoiceProcessor::class,
)]
#[ApiFilter(DataGridFilter::class)]
class Invoice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['invoice:read'])]
    private ?int $id = null;

    /**
     * Auto-filled by #[Sequence] on first persist.
     * Read-only after creation — the frontend uses visibleOnForm on edit.
     */
    #[ORM\Column(length: 32, unique: true)]
    #[Groups(['invoice:read'])]
    #[ApiProperty(
        description: 'Number',
        openapiContext: ['x-crud' => ['filterable' => true, 'sortable' => true, 'order' => 0, 'visibleOnForm' => false]],
    )]
    private string $number = '';

    #[ORM\ManyToOne(targetEntity: Customer::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['invoice:read', 'invoice:write'])]
    #[ApiProperty(
        readableLink: true,
        description: 'Customer',
        openapiContext: ['x-crud' => ['filterable' => true, 'sortable' => true, 'order' => 1]],
    )]
    private ?Customer $customer = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotNull]
    #[Groups(['invoice:read', 'invoice:write'])]
    #[ApiProperty(
        description: 'Issue date',
        openapiContext: ['x-crud' => ['filterable' => true, 'sortable' => true, 'order' => 2]],
    )]
    private ?\DateTimeInterface $issuedAt = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['invoice:read', 'invoice:write'])]
    #[ApiProperty(
        description: 'Due date',
        openapiContext: ['x-crud' => ['filterable' => true, 'sortable' => true, 'order' => 3]],
    )]
    private ?\DateTimeInterface $dueAt = null;

    /**
     * Driven by #[Workflow] — not shown in form (set by transition endpoints).
     */
    #[ORM\Column(length: 16)]
    #[Groups(['invoice:read'])]
    #[ApiProperty(
        description: 'Status',
        openapiContext: [
            'x-crud' => ['filterable' => true, 'sortable' => true, 'order' => 4, 'visibleOnForm' => false],
            'enum' => ['draft', 'confirmed', 'paid', 'cancelled'],
        ],
    )]
    private string $status = 'draft';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    #[Groups(['invoice:read'])]
    #[ApiProperty(
        description: 'Subtotal',
        openapiContext: ['x-crud' => ['order' => 5, 'format' => 'currency', 'visibleOnForm' => false]],
    )]
    private string $subtotal = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    #[Groups(['invoice:read'])]
    #[ApiProperty(
        description: 'Tax',
        openapiContext: ['x-crud' => ['order' => 6, 'format' => 'currency', 'visibleOnForm' => false]],
    )]
    private string $tax = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    #[Groups(['invoice:read'])]
    #[ApiProperty(
        description: 'Total',
        openapiContext: ['x-crud' => ['order' => 7, 'format' => 'currency', 'visibleOnForm' => false]],
    )]
    private string $total = '0.00';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['invoice:read', 'invoice:write'])]
    #[ApiProperty(
        description: 'Notes',
        openapiContext: ['x-crud' => ['hidden' => true]],
    )]
    private ?string $notes = null;

    /** @var Collection<int, InvoiceLine> */
    #[ORM\OneToMany(
        targetEntity: InvoiceLine::class,
        mappedBy: 'invoice',
        cascade: ['persist', 'remove'],
        orphanRemoval: true,
    )]
    #[Groups(['invoice:read', 'invoice:write'])]
    #[ApiProperty(
        openapiContext: ['x-crud' => ['visibleOnForm' => false, 'hidden' => true]],
    )]
    private Collection $lines;

    public function __construct()
    {
        $this->lines = new ArrayCollection();
        $this->issuedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getNumber(): string { return $this->number; }
    public function setNumber(string $number): static { $this->number = $number; return $this; }

    public function getCustomer(): ?Customer { return $this->customer; }
    public function setCustomer(?Customer $customer): static { $this->customer = $customer; return $this; }

    public function getIssuedAt(): ?\DateTimeInterface { return $this->issuedAt; }
    public function setIssuedAt(?\DateTimeInterface $issuedAt): static { $this->issuedAt = $issuedAt; return $this; }

    public function getDueAt(): ?\DateTimeInterface { return $this->dueAt; }
    public function setDueAt(?\DateTimeInterface $dueAt): static { $this->dueAt = $dueAt; return $this; }

    public function getStatus(): string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getSubtotal(): string { return $this->subtotal; }
    public function setSubtotal(string $subtotal): static { $this->subtotal = $subtotal; return $this; }

    public function getTax(): string { return $this->tax; }
    public function setTax(string $tax): static { $this->tax = $tax; return $this; }

    public function getTotal(): string { return $this->total; }
    public function setTotal(string $total): static { $this->total = $total; return $this; }

    public function getNotes(): ?string { return $this->notes; }
    public function setNotes(?string $notes): static { $this->notes = $notes; return $this; }

    /** @return Collection<int, InvoiceLine> */
    public function getLines(): Collection { return $this->lines; }

    #[Groups(['invoice:read'])]
    #[ApiProperty(
        description: 'Lines',
        openapiContext: ['x-crud' => ['order' => 8, 'visibleOnForm' => false]],
    )]
    public function getLineCount(): int { return $this->lines->count(); }

    public function addLine(InvoiceLine $line): static
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setInvoice($this);
        }
        return $this;
    }

    public function removeLine(InvoiceLine $line): static
    {
        if ($this->lines->removeElement($line) && $line->getInvoice() === $this) {
            $line->setInvoice(null);
        }
        return $this;
    }

    public function recalculateTotals(): void
    {
        $subtotal = 0.0;
        $tax = 0.0;

        foreach ($this->lines as $line) {
            $line->recalculateLineTotal();
            $base = (float) $line->getQuantity() * (float) $line->getUnitPrice();
            $subtotal += $base;
            $tax += $base * (float) $line->getTaxRate() / 100;
        }

        $this->subtotal = number_format($subtotal, 2, '.', '');
        $this->tax      = number_format($tax, 2, '.', '');
        $this->total    = number_format($subtotal + $tax, 2, '.', '');
    }
}
