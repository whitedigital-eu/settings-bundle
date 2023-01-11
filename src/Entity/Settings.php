<?php

declare(strict_types = 1);

namespace WhiteDigital\SettingsBundle\Entity;

use Doctrine\ORM\Mapping as ORM;
use WhiteDigital\EntityResourceMapper\Entity\BaseEntity;
use WhiteDigital\SettingsBundle\Contracts\SettingsEntityInterface;
use WhiteDigital\SettingsBundle\Repository\SettingsRepository;

#[ORM\Entity(repositoryClass: SettingsRepository::class)]
#[ORM\Index(fields: ['lastModifiedBy'])]
#[ORM\Index(fields: ['createdAt'])]
#[ORM\Index(fields: ['updatedAt'])]
class Settings extends BaseEntity implements SettingsEntityInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $className = null;

    private ?array $data = null;

    #[ORM\Column(length: 255)]
    private ?string $lastModifiedBy = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClassName(): ?string
    {
        return $this->className;
    }

    public function setClassName(string $className): self
    {
        $this->className = $className;

        return $this;
    }

    public function getData(): ?array
    {
        return $this->data;
    }

    public function setData(?array $data): self
    {
        $this->data = $data;

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
