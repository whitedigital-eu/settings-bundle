<?php

declare(strict_types = 1);

namespace WhiteDigital\SettingsBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use WhiteDigital\EntityResourceMapper\Entity\BaseEntity;
use WhiteDigital\SettingsBundle\Repository\SettingsRepository;
use WhiteDigital\SettingsBundle\Service\SettingsStore;

#[ORM\Entity(repositoryClass: SettingsRepository::class)]
#[ORM\Index(fields: ['lastModifiedBy'])]
#[ORM\Index(fields: ['createdAt'])]
#[ORM\Index(fields: ['updatedAt'])]
class Settings extends BaseEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $class = null;

    /** @var array<string, SettingsStore|array<string, mixed>> */
    #[ORM\Column(nullable: true)]
    private ?array $store = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lastModifiedBy = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClass(): ?string
    {
        return $this->class;
    }

    public function setClass(string $class): self
    {
        $this->class = $class;

        return $this;
    }

    /** @return  array<string, SettingsStore> */
    public function getStore(): array
    {
        return array_map(static function ($value) {
            if ($value instanceof SettingsStore) {
                return $value;
            }

            return SettingsStore::createFromArray($value);
        }, $this->store);
    }

    /**
     * @param array<string, SettingsStore> $store
     */
    public function setStore(array $store): self
    {
        $this->store = $store;

        return $this;
    }

    public function getLastModifiedBy(): ?string
    {
        return $this->lastModifiedBy;
    }

    public function setLastModifiedBy(?string $lastModifiedBy): self
    {
        $this->lastModifiedBy = $lastModifiedBy;

        return $this;
    }
}
