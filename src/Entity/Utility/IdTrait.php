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

namespace Silverback\ApiComponentsBundle\Entity\Utility;

use ApiPlatform\Metadata\ApiProperty;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Doctrine\UuidGenerator;
use Ramsey\Uuid\UuidInterface;

/**
 * Reusable trait by application developer so keep annotations as we cannot map with XML.
 *
 * @author Daniel West <daniel@silverback.is>
 */
trait IdTrait
{
    /**
     * Must allow return `null` for lowest dependencies.
     */
    #[Orm\Id]
    #[ORM\Column(type: 'uuid', unique: true, nullable: false)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    #[ApiProperty(readable: false, identifier: true)]
    protected ?UuidInterface $id = null;

    public function getId(): ?UuidInterface
    {
        return $this->id;
    }
}
