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
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Nubit\AdminBundle\Media\Entity\Media;
use Nubit\ApiPlatform\Attribute\Auditable;
use Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter;
use Nubit\TenantBundle\Contract\TenantOwnedInterface;
use Nubit\TenantBundle\Entity\TenantOwnedTrait;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Example CRUD resource: the `x-crud` hints drive the React datagrid/form
 * that @nubitio/react-admin generates from /api/docs.jsonld — no frontend
 * field definitions needed.
 */
#[Auditable]
#[ORM\Entity]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('APP_PRODUCT_READ')"),
        new Post(security: "is_granted('APP_PRODUCT_MANAGE')"),
        new Get(security: "is_granted('APP_PRODUCT_READ')"),
        new Patch(security: "is_granted('APP_PRODUCT_MANAGE')"),
        new Delete(security: "is_granted('APP_PRODUCT_MANAGE')"),
    ],
    mercure: true,
    paginationClientItemsPerPage: true,
)]
#[ApiFilter(DataGridFilter::class)]
class Product implements TenantOwnedInterface
{
    use TenantOwnedTrait;
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 160)]
    #[Assert\NotBlank]
    #[ApiProperty(
        description: 'Name',
        openapiContext: ['x-crud' => ['filterable' => true, 'sortable' => true, 'order' => 0]],
    )]
    private string $name = '';

    #[ORM\Column(length: 64, nullable: true)]
    #[ApiProperty(
        description: 'SKU',
        openapiContext: ['x-crud' => ['filterable' => true, 'sortable' => true, 'order' => 1]],
    )]
    private ?string $sku = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    #[Assert\PositiveOrZero]
    #[ApiProperty(
        description: 'Price',
        openapiContext: ['x-crud' => ['filterable' => true, 'sortable' => true, 'order' => 2, 'format' => 'currency']],
    )]
    private string $price = '0.00';

    #[ORM\Column]
    #[ApiProperty(
        description: 'Active',
        openapiContext: ['x-crud' => ['filterable' => true, 'sortable' => true, 'order' => 3]],
    )]
    private bool $active = true;

    // Instant upload: the form POSTs the file to /api/media on selection and
    // submits only the resulting IRI here. format:'image' renders the upload
    // control automatically (use 'file' for non-image attachments).
    #[ORM\ManyToOne(targetEntity: Media::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[ApiProperty(
        readableLink: true,
        description: 'Photo',
        openapiContext: ['x-crud' => ['order' => 4, 'format' => 'image', 'hideInGrid' => true]],
    )]
    private ?Media $photo = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getSku(): ?string
    {
        return $this->sku;
    }

    public function setSku(?string $sku): static
    {
        $this->sku = $sku;

        return $this;
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function setPrice(string $price): static
    {
        $this->price = $price;

        return $this;
    }

    public function getPhoto(): ?Media
    {
        return $this->photo;
    }

    public function setPhoto(?Media $photo): static
    {
        $this->photo = $photo;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }
}
