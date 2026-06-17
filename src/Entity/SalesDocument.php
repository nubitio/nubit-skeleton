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
use App\State\SalesDocumentProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nubit\ApiPlatform\Attribute\Auditable;
use Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Master-detail example: header fields are auto-generated in the React form;
 * line items are edited through formDetail in SalesDocumentsPage.tsx.
 */
#[Auditable(resource: 'sales_documents')]
#[ORM\Entity]
#[ORM\Table(name: 'sales_document')]
#[ApiResource(
    operations: [new GetCollection(), new Post(), new Get(), new Patch(), new Delete()],
    mercure: true,
    paginationClientItemsPerPage: true,
    normalizationContext: ['groups' => ['document:read']],
    denormalizationContext: ['groups' => ['document:write']],
    processor: SalesDocumentProcessor::class,
)]
#[ApiFilter(DataGridFilter::class)]
class SalesDocument
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['document:read'])]
    private ?int $id = null;

    #[ORM\Column(length: 32)]
    #[Assert\NotBlank]
    #[Groups(['document:read', 'document:write'])]
    #[ApiProperty(
        description: 'Number',
        openapiContext: ['x-crud' => ['filterable' => true, 'sortable' => true, 'order' => 0]],
    )]
    private string $number = '';

    #[ORM\Column(length: 16)]
    #[Groups(['document:read', 'document:write'])]
    #[ApiProperty(
        description: 'Status',
        openapiContext: [
            'x-crud' => ['filterable' => true, 'sortable' => true, 'order' => 1],
            'enum' => ['draft', 'confirmed', 'cancelled'],
        ],
    )]
    private string $status = 'draft';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    #[Groups(['document:read', 'document:write'])]
    #[ApiProperty(
        description: 'Total',
        openapiContext: ['x-crud' => ['order' => 2, 'format' => 'currency', 'visibleOnForm' => false]],
    )]
    private string $total = '0.00';

    /** @var Collection<int, SalesDocumentLine> */
    #[ORM\OneToMany(targetEntity: SalesDocumentLine::class, mappedBy: 'document', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['document:read', 'document:write'])]
    #[ApiProperty(
        openapiContext: ['x-crud' => ['visibleOnForm' => false, 'hidden' => true]],
    )]
    private Collection $lines;

    public function __construct()
    {
        $this->lines = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumber(): string
    {
        return $this->number;
    }

    public function setNumber(string $number): static
    {
        $this->number = $number;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    #[Groups(['document:read'])]
    #[ApiProperty(
        description: 'Lines',
        openapiContext: ['x-crud' => ['order' => 3, 'visibleOnForm' => false]],
    )]
    public function getLineCount(): int
    {
        return $this->lines->count();
    }

    public function getTotal(): string
    {
        return $this->total;
    }

    public function setTotal(string $total): static
    {
        $this->total = $total;

        return $this;
    }

    /** @return Collection<int, SalesDocumentLine> */
    public function getLines(): Collection
    {
        return $this->lines;
    }

    public function addLine(SalesDocumentLine $line): static
    {
        if (!$this->lines->contains($line)) {
            $this->lines->add($line);
            $line->setDocument($this);
        }

        return $this;
    }

    public function removeLine(SalesDocumentLine $line): static
    {
        if ($this->lines->removeElement($line) && $line->getDocument() === $this) {
            $line->setDocument(null);
        }

        return $this;
    }

    public function recalculateTotal(): void
    {
        $sum = 0.0;
        foreach ($this->lines as $line) {
            $line->recalculateLineTotal();
            $sum += (float) $line->getLineTotal();
        }

        $this->total = number_format($sum, 2, '.', '');
    }
}