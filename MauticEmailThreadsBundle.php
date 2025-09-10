<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEmailThreadsBundle;

use Mautic\PluginBundle\Bundle\PluginBundleBase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class MauticEmailThreadsBundle extends PluginBundleBase
{
    public function boot(): void
    {
        parent::boot();
    }
    
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        
        // Add Twig path configuration
        $container->loadFromExtension('twig', [
            'paths' => [
                __DIR__ . '/Views' => 'MauticEmailThreadsBundle',
            ],
        ]);
    }
}
