<?php

namespace App;

use App\Security\ProductionReadinessGuard;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    public function boot(): void
    {
        parent::boot();

        if ('prod' === $this->environment) {
            $this->getContainer()->get(ProductionReadinessGuard::class)->assertReady();
        }
    }
}
