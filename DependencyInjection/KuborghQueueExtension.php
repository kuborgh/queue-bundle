<?php
/*
 * (c) Kuborgh GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Kuborgh\QueueBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class KuborghQueueExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        // Basic config
        $container->setParameter('kuborgh_queue.concurrency', $config['concurrency']);
        $container->setParameter('kuborgh_queue.auto_cleanup', $config['auto_cleanup']);

        // Console command
        if (isset($config['console_path']) && !empty($config['console_path'])) {
            $container->setParameter('kuborgh_queue.console_path', $config['console_path']);
        } else {
            $container->setParameter('kuborgh_queue.console_path', $container->getParameter('kernel.root_dir').'/console');
        }

        // Laod database configuration
        $this->loadDbConfig($config, $container);

        // Load services
        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.yml');
    }

    /**
     * Load/detect DB config
     *
     * @param array            $config    Config
     * @param ContainerBuilder $container Container
     */
    protected function loadDbConfig($config, $container)
    {
        foreach (array('host', 'name', 'user', 'password') as $name) {
            if (isset($config['database'][$name])) {
                // Set via config
                $paramNameQueue = sprintf('kuborgh_queue.database.%s', $name);
                $container->setParameter($paramNameQueue, $config['database'][$name]);
            } else {
                // Find fallback
                $this->setDbParamFallback($name, $container);
            }
        }
    }

    /**
     * @param string           $name      Name of the config part
     * @param ContainerBuilder $container Container
     *
     * @throws \Exception
     */
    protected function setDbParamFallback($name, $container)
    {
        $fallbacks = array('database_%s', 'database.%s', 'sylius.database.%s');
        $paramNameQueue = sprintf('kuborgh_queue.database.%s', $name);

        // Try some fallbacks
        foreach ($fallbacks as $fallback) {
            $paramName = sprintf($fallback, $name);
            if ($container->hasParameter($paramName)) {
                $container->setParameter($paramNameQueue, $container->getParameter($paramName));
                return;
            }
        }
        $excMsg = sprintf('Could not find database configuration for kuborgh_queue. Please add %s to your application config', $paramNameQueue);
        throw new \Exception($excMsg);
    }
}
