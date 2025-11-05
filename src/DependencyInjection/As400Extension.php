<?php

namespace Cisse\Bundle\As400\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class As400Extension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('services.yaml');

        // Load dev-specific services
        if (
            file_exists(__DIR__ . '/../Resources/config/services_dev.yaml')
            && in_array($container->getParameter('kernel.environment'), ['dev', 'test'], true)
        ) {
            $loader->load('services_dev.yaml');
        }

        // Set connection parameters
        if (isset($config['connection'])) {
            foreach ($config['connection'] as $key => $value) {
                $container->setParameter("as400.connection.$key", $value);
            }
        }

        // Set generator parameters
        if (isset($config['generator'])) {
            foreach ($config['generator'] as $key => $value) {
                $container->setParameter("as400.generator.$key", $value);
            }
        }
    }

    public function getAlias(): string
    {
        return 'as400';
    }

    public function prepend(ContainerBuilder $container): void
    {
        // Register the bundle's template namespace
        if ($container->hasExtension('twig')) {
            // For local bundle development
            $bundlePath = dirname(__DIR__, 2);
            $container->prependExtensionConfig('twig', [
                'paths' => [
                    $bundlePath . '/src/Resources/views' => 'As400'
                ]
            ]);
        }
    }
}
