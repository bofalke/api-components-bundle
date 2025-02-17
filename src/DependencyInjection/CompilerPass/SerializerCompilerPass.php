<?php

/*
 * This file is part of the Silverback API Components Bundle Project
 *
 * (c) Daniel West <daniel@silverback.is>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Silverback\ApiComponentsBundle\DependencyInjection\CompilerPass;

use Silverback\ApiComponentsBundle\Serializer\MappingLoader\CwaResourceLoader;
use Silverback\ApiComponentsBundle\Serializer\MappingLoader\PublishableLoader;
use Silverback\ApiComponentsBundle\Serializer\MappingLoader\TimestampedLoader;
use Silverback\ApiComponentsBundle\Serializer\MappingLoader\UploadableLoader;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

/**
 * @author Vincent Chalamon <vincent@les-tilleuls.coop>
 */
class SerializerCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container)
    {
        $definitions = ['serializer.mapping.chain_loader', 'serializer.mapping.cache_warmer'];
        foreach ($definitions as $definitonId) {
            $definition = $container->getDefinition($definitonId);
            $definition->replaceArgument(
                0,
                array_merge(
                    $definition->getArgument(0),
                    [
                        new Reference(PublishableLoader::class),
                        new Reference(TimestampedLoader::class),
                        new Reference(CwaResourceLoader::class),
                        new Reference(UploadableLoader::class),
                    ]
                )
            );
        }
    }
}
