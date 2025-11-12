<?php

namespace Survos\CiineBundle;

use Survos\CiineBundle\Command\PlayCommand;
use Survos\CiineBundle\Command\ScreenshotCommand;
use Survos\CiineBundle\Command\UploadCommand;
use Survos\CiineBundle\Controller\CastController;
use Survos\CiineBundle\Controller\ScreenshotController;
use Survos\CiineBundle\Service\CiineService;
use Survos\CiineBundle\Twig\TwigExtension;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class SurvosCiineBundle extends AbstractBundle
{
    protected string $extensionAlias = 'survos_ciine';

    /**
     * @param array<mixed> $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->autowire(CiineService::class)
            ->setAutowired(true)
            ->setAutoconfigured(true)
            ->setPublic(true);

        // CastController is too app-specific right now! Maybe take in a URL for the cast data
        foreach ([ScreenshotController::class, CastController::class ] as $class) {
            $builder->autowire($class)
                ->setPublic(true)
                ->setAutowired(true)
                ->setAutoconfigured(true)
                ->addTag('controller.service_arguments')
                ->addTag('controller.service_subscriber');


        }
        // eh?  Do we need this?
        $definition = $builder
            ->autowire('survos.ciine_twig', TwigExtension::class)
            ->addTag('twig.extension')
            ->setArgument('$config', $config)
        ;

        //        $definition->setArgument('$seed', $config['seed']);
        //        $definition->setArgument('$prefix', $config['function_prefix']);

        $builder->autowire(ScreenshotCommand::class)
            ->addTag('console.command');

        $builder->autowire(UploadCommand::class)
            ->setArgument('$httpClient', new Reference('http_client'))
            ->setArgument('$projectDir', '%kernel.project_dir%')
            ->setArgument('$config', $config)
//            ->setArgument('$config', $config)
            ->addTag('console.command')
        ;


        $builder->autowire(PlayCommand::class)
            ->setArgument('$httpClient', new Reference('http_client'))
            ->setArgument('$projectDir', '%kernel.project_dir%')
            ->setArgument('$config', $config)
//            ->setArgument('$config', $config)
            ->addTag('console.command')
        ;

    }

    public function configure(DefinitionConfigurator $definition): void
    {
        // since the configuration is short, we can add it here
        $definition->rootNode()
            ->children()
//            ->scalarNode('screenshow_endpoint')->defaultValue(null)->end()
            ->scalarNode('endpoint')->defaultValue('%env(default::CIINE_ENDPOINT)%')->end()
            ->scalarNode('dir')->defaultValue('%env(default::CIINE_LOCAL_DIR)%')->end()
            ->end();
        ;
    }
}
