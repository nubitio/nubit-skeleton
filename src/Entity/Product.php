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
use Nubit\ApiPlatform\Doctrine\Filter\DataGridFilter;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The template's one reference resource: the `x-crud` hints drive the React
 * datagrid and form that @nubitio/react-admin generates from
 * /api/docs.jsonld — no frontend field definitions needed.
 *
 * Copy this shape for your own entities. Optional modules (media uploads,
 * audit trails, workflows, sequences) are off in this template; see the
 * admin-bundle README for what each one adds and how to turn it on.
 */
#[ORM\Entity]
#[ApiResource(
    operations: [
        new GetCollection(security: "is_granted('ROLE_USER')"),
        new Post(security: "is_granted('ROLE_ADMIN')"),
        new Get(security: "is_granted('ROLE_USER')"),
        new Patch(security: "is_granted('ROLE_ADMIN')"),
        new Delete(security: "is_granted('ROLE_ADMIN')"),
    ],
    mercure: true,
    paginationClientItemsPerPage: true,
)]
#[ApiFilter(DataGridFilter::class)]
class Product
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 160)]
    #[Assert\NotBlank]
    #[ApiProperty(description: 'Name', openapiContext: ['x-crud' => [
        'filterable' => true,
        'sortable' => true,
        'order' => 0,
    ]])]
    private string $name = '';

    #[ORM\Column(length: 64, nullable: true)]
    #[ApiProperty(description: 'SKU', openapiContext: ['x-crud' => [
        'filterable' => true,
        'sortable' => true,
        'order' => 1,
    ]])]
    private ?string $sku = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 12, scale: 2)]
    #[Assert\PositiveOrZero]
    #[ApiProperty(description: 'Price', openapiContext: ['x-crud' => [
        'filterable' => true,
        'sortable' => true,
        'order' => 2,
        'format' => 'currency',
    ]])]
    private string $price = '0.00';

    #[ORM\Column]
    #[ApiProperty(description: 'Active', openapiContext: ['x-crud' => [
        'filterable' => true,
        'sortable' => true,
        'order' => 3,
    ]])]
    private bool $active = true;

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
