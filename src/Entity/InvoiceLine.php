<?php

declare(strict_types=1);

namespace App\Entity;

use ApiPlatform\Metadata\ApiProperty;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nubit\ApiPlatform\Attribute\EmbeddedLines;
use Nubit\TenantBundle\Contract\TenantOwnedInterface;
use Nubit\TenantBundle\Entity\TenantOwnedTrait;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Line item for an Invoice.
 *
 * #[ApiResource] exposes the schema in /api/docs.jsonld so SchemaCrudPage can
 * infer formDetail line fields from x-crud hints (no manual frontend fields).
 * #[EmbeddedLines] registers the reload endpoint used by the drawer form.
 */
#[EmbeddedLines(
    parentProperty: 'invoice',
    route: '/api/invoice_lines',
    normalizationGroups: ['invoice:read'],
)]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('APP_INVOICE_LINE_READ')"),
        new Get(security: "is_granted('APP_INVOICE_LINE_READ')"),
    ],
    normalizationContext: ['groups' => ['invoice:read']],
)]
#[ORM\Entity]
#[ORM\Table(name: 'invoice_line')]
class InvoiceLine implements TenantOwnedInterface
{
    use TenantOwnedTrait;
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['invoice:read', 'invoice:write'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Invoice::class, inversedBy: 'lines')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Invoice $invoice = null;

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull]
    #[Groups(['invoice:read', 'invoice:write'])]
    #[ApiProperty(readableLink: true)]
    private ?Product $product = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    #[Assert\Positive]
    #[Groups(['invoice:read', 'invoice:write'])]
    #[ApiProperty(openapiContext: ['x-crud' => ['order' => 1]])]
    private string $quantity = '1.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    #[Assert\PositiveOrZero]
    #[Groups(['invoice:read', 'invoice:write'])]
    #[ApiProperty(openapiContext: ['x-crud' => ['order' => 2, 'format' => 'currency']])]
    private string $unitPrice = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero]
    #[Groups(['invoice:read', 'invoice:write'])]
    #[ApiProperty(openapiContext: ['x-crud' => ['order' => 3, 'format' => 'percent']])]
    private string $taxRate = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    #[Groups(['invoice:read'])]
    #[ApiProperty(openapiContext: ['x-crud' => ['order' => 4, 'format' => 'currency']])]
    private string $lineTotal = '0.00';

    public function getId(): ?int { return $this->id; }

    public function getInvoice(): ?Invoice { return $this->invoice; }
    public function setInvoice(?Invoice $invoice): static { $this->invoice = $invoice; return $this; }

    public function getProduct(): ?Product { return $this->product; }
    public function setProduct(?Product $product): static { $this->product = $product; return $this; }

    public function getQuantity(): string { return $this->quantity; }
    public function setQuantity(string $quantity): static { $this->quantity = $quantity; return $this; }

    public function getUnitPrice(): string { return $this->unitPrice; }
    public function setUnitPrice(string $unitPrice): static { $this->unitPrice = $unitPrice; return $this; }

    public function getTaxRate(): string { return $this->taxRate; }
    public function setTaxRate(string $taxRate): static { $this->taxRate = $taxRate; return $this; }

    public function getLineTotal(): string { return $this->lineTotal; }
    public function setLineTotal(string $lineTotal): static { $this->lineTotal = $lineTotal; return $this; }

    public function recalculateLineTotal(): void
    {
        $base = (float) $this->quantity * (float) $this->unitPrice;
        $tax  = $base * (float) $this->taxRate / 100;
        $this->lineTotal = number_format($base + $tax, 2, '.', '');
    }
}
