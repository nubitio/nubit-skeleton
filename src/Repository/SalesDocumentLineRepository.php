<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\SalesDocumentLine;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<SalesDocumentLine> */
final class SalesDocumentLineRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SalesDocumentLine::class);
    }
}
