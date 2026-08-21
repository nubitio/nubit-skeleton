<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'organization')]
#[ORM\UniqueConstraint(name: 'UNIQ_ORGANIZATION_SLUG', columns: ['slug'])]
class Organization
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 160)]
    private string $name = '';

    #[ORM\Column(length: 80)]
    private string $slug = '';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $primaryDomain = null;

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

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;
        return $this;
    }

    public function getPrimaryDomain(): ?string
    {
        return $this->primaryDomain;
    }

    public function setPrimaryDomain(?string $primaryDomain): static
    {
        $this->primaryDomain = $primaryDomain;
        return $this;
    }
}
