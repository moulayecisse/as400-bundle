<?php

namespace Cisse\Bundle\As400\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('as400');
        $rootNode = $treeBuilder->getRootNode();

        $rootNode
            ->children()
                ->arrayNode('connection')
                    ->children()
                        ->scalarNode('driver')->isRequired()->cannotBeEmpty()->end()
                        ->scalarNode('system')->isRequired()->cannotBeEmpty()->end()
                        ->scalarNode('user')->isRequired()->cannotBeEmpty()->end()
                        ->scalarNode('password')->isRequired()->cannotBeEmpty()->end()
                        ->scalarNode('commit_mode')->defaultValue('2')->end()
                        ->scalarNode('extended_dynamic')->defaultValue('1')->end()
                        ->scalarNode('package_library')->defaultValue('')->end()
                        ->scalarNode('translate_hex')->defaultValue('1')->end()
                        ->scalarNode('database')->defaultValue('')->end()
                        ->scalarNode('default_libraries')->defaultValue('')->end()
                        ->arrayNode('schema_mapping')
                            ->info('Map logical schema names to physical schema names (e.g., DICADCDE => ICADCDE)')
                            ->useAttributeAsKey('name')
                            ->prototype('scalar')->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('generator')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('entity_dir')->defaultValue('src/Entity/As400')->end()
                        ->scalarNode('repository_dir')->defaultValue('src/Repository/As400')->end()
                        ->scalarNode('entity_namespace')->defaultValue('App\\Entity\\As400')->end()
                        ->scalarNode('repository_namespace')->defaultValue('App\\Repository\\As400')->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
