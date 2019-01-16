<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\Bundle\DebugBundle\DependencyInjection;

use Symfony\Bundle\DebugBundle\Command\ServerDumpPlaceholderCommand;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;

/**
 * DebugExtension.
 *
 * @author Nicolas Grekas <p@tchwork.com>
 */
class DebugExtension extends Extension
{
    /**
     * {@inheritdoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');

        $container->getDefinition('var_dumper.cloner')
            ->addMethodCall('setMaxItems', [$config['max_items']])
            ->addMethodCall('setMinDepth', [$config['min_depth']])
            ->addMethodCall('setMaxString', [$config['max_string_length']]);

        if (method_exists(HtmlDumper::class, 'setTheme') && 'dark' !== $config['theme']) {
            $container->getDefinition('var_dumper.html_dumper')
                ->addMethodCall('setTheme', array($config['theme']));
        }

        if (null === $config['dump_destination']) {
            $container->getDefinition('var_dumper.command.server_dump')
                ->setClass(ServerDumpPlaceholderCommand::class)
            ;
        } elseif (0 === strpos($config['dump_destination'], 'tcp://')) {
            $container->getDefinition('debug.dump_listener')
                ->replaceArgument(2, new Reference('var_dumper.server_connection'))
            ;
            $container->getDefinition('data_collector.dump')
                ->replaceArgument(4, new Reference('var_dumper.server_connection'))
            ;
            $container->getDefinition('var_dumper.dump_server')
                ->replaceArgument(0, $config['dump_destination'])
            ;
            $container->getDefinition('var_dumper.server_connection')
                ->replaceArgument(0, $config['dump_destination'])
            ;
        } else {
            $container->getDefinition('var_dumper.cli_dumper')
                ->replaceArgument(0, $config['dump_destination'])
            ;
            $container->getDefinition('data_collector.dump')
                ->replaceArgument(4, new Reference('var_dumper.cli_dumper'))
            ;
            $container->getDefinition('var_dumper.command.server_dump')
                ->setClass(ServerDumpPlaceholderCommand::class)
            ;
        }

        if (method_exists(CliDumper::class, 'setDisplayOptions')) {
            $container->getDefinition('var_dumper.cli_dumper')
                ->addMethodCall('setDisplayOptions', array(array(
                    'fileLinkFormat' => new Reference('debug.file_link_formatter', ContainerBuilder::IGNORE_ON_INVALID_REFERENCE),
                )))
            ;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function getXsdValidationBasePath()
    {
        return __DIR__.'/../Resources/config/schema';
    }

    /**
     * {@inheritdoc}
     */
    public function getNamespace()
    {
        return 'http://symfony.com/schema/dic/debug';
    }
}
