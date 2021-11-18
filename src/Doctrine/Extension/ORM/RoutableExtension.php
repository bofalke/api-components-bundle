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

namespace Silverback\ApiComponentsBundle\Doctrine\Extension\ORM;

use ApiPlatform\Core\Bridge\Doctrine\Orm\Extension\ContextAwareQueryCollectionExtensionInterface;
use ApiPlatform\Core\Bridge\Doctrine\Orm\Util\QueryNameGeneratorInterface;
use ApiPlatform\Core\Security\ResourceAccessCheckerInterface;
use Doctrine\ORM\QueryBuilder;
use Silverback\ApiComponentsBundle\Entity\Core\RoutableInterface;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class RoutableExtension implements ContextAwareQueryCollectionExtensionInterface
{
    private ?string $securityStr;
    private ResourceAccessCheckerInterface $resourceAccessChecker;

    public function __construct(?string $securityStr, ResourceAccessCheckerInterface $resourceAccessChecker)
    {
        $this->securityStr = $securityStr;
        $this->resourceAccessChecker = $resourceAccessChecker;
    }

    public function applyToCollection(QueryBuilder $queryBuilder, QueryNameGeneratorInterface $queryNameGenerator, string $resourceClass, string $operationName = null, array $context = []): void
    {
        if (!$this->securityStr) {
            return;
        }

        $refl = new \ReflectionClass($resourceClass);
        if (!$refl->implementsInterface(RoutableInterface::class)) {
            return;
        }

        if ($this->resourceAccessChecker->isGranted($resourceClass, $this->securityStr)) {
            return;
        }

        $alias = $queryBuilder->getRootAliases()[0];
        $queryBuilder
            ->andWhere(
                $queryBuilder->expr()->isNotNull("$alias.route")
            );
    }
}
