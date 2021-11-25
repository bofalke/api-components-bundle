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

use ApiPlatform\Core\Api\IriConverterInterface;
use Doctrine\ORM\Mapping\ClassMetadataInfo;
use Doctrine\Persistence\ManagerRegistry;
use Silverback\ApiComponentsBundle\DataProvider\PageDataProvider;
use Silverback\ApiComponentsBundle\Entity\Core\AbstractComponent;
use Silverback\ApiComponentsBundle\Entity\Core\ComponentPosition;
use Silverback\ApiComponentsBundle\Exception\InvalidArgumentException;
use Silverback\ApiComponentsBundle\Exception\UnexpectedValueException;
use Silverback\ApiComponentsBundle\Helper\ComponentPosition\ComponentPositionSortValueHelper;
use Silverback\ApiComponentsBundle\Helper\Publishable\PublishableStatusChecker;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\PropertyAccess\Exception\NoSuchIndexException;
use Symfony\Component\PropertyAccess\Exception\UnexpectedTypeException;
use Symfony\Component\PropertyAccess\PropertyAccess;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\CacheableSupportsMethodInterface;
use Symfony\Component\Serializer\Normalizer\ContextAwareDenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ContextAwareNormalizerInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

/**
 * When creating a new component position the sort value should be set if not already explicitly set in the request.
 *
 * @author Daniel West <daniel@silverback.is>
 */
class ComponentPositionNormalizer implements CacheableSupportsMethodInterface, ContextAwareDenormalizerInterface, DenormalizerAwareInterface, ContextAwareNormalizerInterface, NormalizerAwareInterface
{
    use DenormalizerAwareTrait;
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'COMPONENT_POSITION_NORMALIZER_ALREADY_CALLED';

    private PageDataProvider $pageDataProvider;
    private ComponentPositionSortValueHelper $componentPositionSortValueHelper;
    private RequestStack $requestStack;
    private Security $security;
    private PublishableStatusChecker $publishableStatusChecker;
    private ManagerRegistry $registry;
    private IriConverterInterface $iriConverter;

    public function __construct(
        PageDataProvider $pageDataProvider,
        ComponentPositionSortValueHelper $componentPositionSortValueHelper,
        RequestStack $requestStack,
        Security $security,
        PublishableStatusChecker $publishableStatusChecker,
        ManagerRegistry $registry,
        IriConverterInterface $iriConverter
    ) {
        $this->pageDataProvider = $pageDataProvider;
        $this->componentPositionSortValueHelper = $componentPositionSortValueHelper;
        $this->requestStack = $requestStack;
        $this->security = $security;
        $this->publishableStatusChecker = $publishableStatusChecker;
        $this->registry = $registry;
        $this->iriConverter = $iriConverter;
    }

    public function hasCacheableSupportsMethod(): bool
    {
        return false;
    }

    public function supportsDenormalization($data, $type, $format = null, array $context = []): bool
    {
        return !isset($context[self::ALREADY_CALLED]) && ComponentPosition::class === $type;
    }

    public function denormalize($data, $type, $format = null, array $context = [])
    {
        $context[self::ALREADY_CALLED] = true;

        $originalObject = $context[AbstractNormalizer::OBJECT_TO_POPULATE] ?? null;
        $originalSortValue = $originalObject ? $originalObject->sortValue : null;

        /** @var ComponentPosition $object */
        $object = $this->denormalizer->denormalize($data, $type, $format, $context);

        $this->componentPositionSortValueHelper->calculateSortValue($object, $originalSortValue);

        return $object;
    }

    public function supportsNormalization($data, $format = null, array $context = []): bool
    {
        return $data instanceof ComponentPosition && !isset($context[self::ALREADY_CALLED]);
    }

    public function normalize($object, $format = null, array $context = [])
    {
        /* @var ComponentPosition $object */
        /* @var mixed|null        $format */

        $context[self::ALREADY_CALLED] = true;

        $context[MetadataNormalizer::METADATA_CONTEXT]['static_component'] = $object->component ? $this->iriConverter->getIriFromItem($object->component) : null;
        if ($object->pageDataProperty && (bool) $this->requestStack->getCurrentRequest()) {
            $object = $this->normalizeForPageData($object);
        }

        $component = $object->component;
        if ($component && $this->publishableStatusChecker->getAnnotationReader()->isConfigured($component) && $this->publishableStatusChecker->isGranted($component)) {
            $object->setComponent($this->normalizePublishableComponent($component));
        }

        return $this->normalizer->normalize($object, $format, $context);
    }

    private function normalizePublishableComponent(AbstractComponent $component)
    {
        $configuration = $this->publishableStatusChecker->getAnnotationReader()->getConfiguration($type = \get_class($component));
        $em = $this->registry->getManagerForClass(\get_class($component));
        if (!$em) {
            throw new InvalidArgumentException(sprintf('Could not find entity manager for class %s', $type));
        }
        /** @var ClassMetadataInfo $classMetadata */
        $classMetadata = $em->getClassMetadata($type);
        $draft = $classMetadata->getFieldValue($component, $configuration->reverseAssociationName);

        return $draft ?? $component;
    }

    private function normalizeForPageData(ComponentPosition $object): ComponentPosition
    {
        $pageData = $this->pageDataProvider->getPageData();
        if (!$pageData) {
            return $object;
            // Todo: causes a 500 error, perhaps we should look to return 404 and the front-end to handle this on server-side when it will not be admin then try again client-side
//            if ($object->component || $this->security->isGranted('ROLE_ADMIN')) {
//                return $object;
//            }
//            throw new UnexpectedValueException('Could not find page data for this route.');
        }

        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        try {
            $component = $propertyAccessor->getValue($pageData, $object->pageDataProperty);
        } catch (UnexpectedTypeException | NoSuchIndexException $e) {
            return $object;
        }

        if (!$component) {
            // it is now optional to have the page data defined
            return $object;
            // throw new UnexpectedValueException(sprintf('Page data does not contain a value at %s', $object->pageDataProperty));
        }

        if (!$component instanceof AbstractComponent) {
            throw new InvalidArgumentException(sprintf('The page data property %s is not a component', $object->pageDataProperty));
        }

        $object->setComponent($component);

        return $object;
    }
}
