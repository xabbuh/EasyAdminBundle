<?php

/*
 * This file is part of the EasyAdminBundle.
 *
 * (c) Javier Eguiluz <javier.eguiluz@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace EasyCorp\Bundle\EasyAdminBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @author Christian Flothmann <christian.flothmann@sensiolabs.de>
 */
class AnnotatedRouteControllerLoaderPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        if (class_exists('Symfony\Bundle\FrameworkBundle\Routing\AnnotatedRouteControllerLoader')) {
            // FrameworkBundle 3.4+ ships it own AnnotationClassLoader implementation
            return;
        }

        if (!$container->has('routing.resolver')) {
            return;
        }

        $container->register('easyadmin.routing.loader.annotation', 'EasyCorp\Bundle\EasyAdminBundle\Router\AnnotatedRouteControllerLoader')
            ->setPublic(false)
            ->addArgument(new Reference('annotation_reader'))
            ->addTag('routing.loader');

        $container->register('easyadmin.routing.loader.directory', 'Symfony\Component\Routing\Loader\AnnotationDirectoryLoader')
            ->setPublic(false)
            ->setArguments(array(
                new Reference('file_locator'),
                new Reference('easyadmin.routing.loader.annotation'),
            ))
            ->addTag('routing.loader');

        $container->register('easyadmin.routing.loader.file', 'Symfony\Component\Routing\Loader\AnnotationFileLoader')
            ->setPublic(false)
            ->addTag('routing.loader', array('priority' => -10))
            ->setArguments(array(
                new Reference('file_locator'),
                new Reference('easyadmin.routing.loader.annotation'),
            ));

        $resolverDefinition = $container->getDefinition('routing.resolver');
        $methodCalls = array_filter($resolverDefinition->getMethodCalls(), function ($methodCall) {
            return isset($methodCall[0]) && 'addLoader' === $methodCall[0];
        });

        if (!empty($methodCalls)) {
            // The RoutingResolverPass from the Routing component has already been processed. Thus, the routing.loader
            // tag used above will not have any effect anymore. Thus, we need to make sure to register our created
            // route loader definitions ourselves.
            $resolverDefinition->addMethodCall('addLoader', array(new Reference('easyadmin.routing.loader.annotation')));
            $resolverDefinition->addMethodCall('addLoader', array(new Reference('easyadmin.routing.loader.directory')));
            $resolverDefinition->addMethodCall('addLoader', array(new Reference('easyadmin.routing.loader.file')));
        }
    }
}
