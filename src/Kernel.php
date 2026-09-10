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
            if (null === $container) {
                throw new \LogicException('The production service container is unavailable.');
            }

            /** @var ProductionReadinessGuard $guard */
            $guard = $container->get(ProductionReadinessGuard::class);
            $guard->assertReady();
        }
    }
}
