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
            $container = $this->getContainer();
            /** @var ProductionReadinessGuard $guard */
            $guard = $container->get(ProductionReadinessGuard::class);
            $guard->assertReady();
        }
    }
}
