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

namespace Silverback\ApiComponentsBundle\Serializer\Normalizer;

use ApiPlatform\Core\Api\ResourceClassResolverInterface;
use ApiPlatform\Util\ClassInfoTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PropertyAccess\Exception\NoSuchPropertyException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\PropertyAccess\PropertyAccessor;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\ContextAwareNormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Traversable;

/**
 * @author Daniel West <daniel@silverback.is>
 */
class PersistedNormalizer implements ContextAwareNormalizerInterface, CacheableSupportsMethodInterface, NormalizerAwareInterface
{
    use ClassInfoTrait;
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'PERSISTED_NORMALIZER_ALREADY_CALLED';

    private EntityManagerInterface $entityManager;
    private ResourceClassResolverInterface $resourceClassResolver;
    private PropertyAccessor $propertyAccessor;

    public function __construct(
        EntityManagerInterface $entityManager,
        ResourceClassResolverInterface $resourceClassResolver
    ) {
        $this->entityManager = $entityManager;
        $this->resourceClassResolver = $resourceClassResolver;
        $this->propertyAccessor = PropertyAccess::createPropertyAccessor();
    }

    public function normalize($object, $format = null, array $context = []): float|array|\ArrayObject|bool|int|string|null
    {
        $context[self::ALREADY_CALLED][] = $this->propertyAccessor->getValue($object, 'id');
        $context[MetadataNormalizer::METADATA_CONTEXT]['persisted'] = $this->entityManager->contains($object);

        return $this->normalizer->normalize($object, $format, $context);
    }

    public function supportsNormalization($data, $format = null, $context = []): bool
    {
        if (!\is_object($data) || $data instanceof Traversable) {
            return false;
        }

        if (!isset($context[self::ALREADY_CALLED])) {
            $context[self::ALREADY_CALLED] = [];
        }

        try {
            $id = $this->propertyAccessor->getValue($data, 'id');
        } catch (NoSuchPropertyException $e) {
            return false;
        }

        return !\in_array($id, $context[self::ALREADY_CALLED], true) &&
            $this->resourceClassResolver->isResourceClass($this->getObjectClass($data));
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return false;
    }
}
