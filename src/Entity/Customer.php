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
use Doctrine\ORM\Mapping as ORM;
use Nubit\ApiPlatform\Attribute\Auditable;
use Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * ERP example: enum segment + ManyToOne to Product — both auto-render in React.
 */
#[Auditable]
#[ORM\Entity]
#[ApiResource(
    operations: [new GetCollection(), new Post(), new Get(), new Patch(), new Delete()],
    mercure: true,
    paginationClientItemsPerPage: true,
)]
#[ApiFilter(DataGridFilter::class)]
class Customer
{
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

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank]
    #[Assert\Email]
    #[ApiProperty(
        description: 'Email',
        openapiContext: ['x-crud' => ['filterable' => true, 'sortable' => true, 'order' => 1]],
    )]
    private string $email = '';

    #[ORM\Column(length: 16)]
    #[ApiProperty(
        description: 'Segment',
        openapiContext: [
            'x-crud' => ['filterable' => true, 'sortable' => true, 'order' => 2],
            'enum' => ['retail', 'wholesale', 'enterprise'],
        ],
    )]
    private string $segment = 'retail';

    #[ORM\ManyToOne(targetEntity: Product::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[ApiProperty(
        readableLink: true,
        description: 'Preferred product',
        openapiContext: ['x-crud' => ['filterable' => true, 'sortable' => true, 'order' => 3]],
    )]
    private ?Product $preferredProduct = null;

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

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getSegment(): string
    {
        return $this->segment;
    }

    public function setSegment(string $segment): static
    {
        $this->segment = $segment;

        return $this;
    }

    public function getPreferredProduct(): ?Product
    {
        return $this->preferredProduct;
    }

    public function setPreferredProduct(?Product $preferredProduct): static
    {
        $this->preferredProduct = $preferredProduct;

        return $this;
    }
}