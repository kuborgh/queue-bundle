<?php
/*
 * (c) Kuborgh GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Kuborgh\QueueBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('kuborgh_queue');

        $rootNode
            ->children()
                ->arrayNode('kuborgh_queue')
                    ->children()
                        ->integerNode('concurrency')->defaultValue(1)->end()
                        ->booleanNode('auto_cleanup')->defaultTrue()->end()
                        ->scalarNode('console_path')->defaultValue('%kernel.root_dir%/console')->end()
                    ->end()
            ->end();

        return $treeBuilder;
    }
}
