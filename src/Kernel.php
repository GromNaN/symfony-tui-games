<?php

namespace App;

use App\Command\MenuCommand;
use Symfony\Component\Console\ConsoleBundle;
use Symfony\Component\DependencyInjection\Kernel\AbstractKernel;
use Symfony\Component\DependencyInjection\Kernel\KernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

class Kernel extends AbstractKernel
{
    use KernelTrait;

    private function getBundlesDefinition(): array
    {
        return [
            ConsoleBundle::class => ['all' => true],
        ];
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->services()
            ->load('App\\', __DIR__.'/')
            ->autoconfigure()
            ->autowire();

        if ('test' === $this->getEnvironment()) {
            $container->services()
                ->set(MenuCommand::class)
                ->autowire()
                ->public();
        }
    }
}
