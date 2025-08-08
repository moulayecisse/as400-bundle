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
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
