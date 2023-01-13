<?php

declare(strict_types=1);

namespace WhiteDigital\SettingsBundle\Service;

use WhiteDigital\SettingsBundle\Enum\SettingsStoreTypeEnum;

class SettingsStore implements \JsonSerializable
{
    private SettingsStoreTypeEnum $type;

    private string|int|null|float $value;

    private ?string $resourceClass = null;

    private ?string $label = null;

    public function getResourceClass(): ?string
    {
        return $this->resourceClass;
    }

    public function setResourceClass(?string $resourceClass): self
    {
        $this->resourceClass = $resourceClass;

        return $this;
    }

    public function getType(): SettingsStoreTypeEnum
    {
        return $this->type;
    }

    public function setType(SettingsStoreTypeEnum|string $type): self
    {
        if (is_string($type)) {
            $this->type = match ($type) {
                'string' => SettingsStoreTypeEnum::String,
                'integer' => SettingsStoreTypeEnum::Integer,
                'resource' => SettingsStoreTypeEnum::Resource,
                'date' => SettingsStoreTypeEnum::Date,
                'float' => SettingsStoreTypeEnum::Float,
                default => throw new \RuntimeException('Invalid type for SettingsStore: ' . $type),
            };
        } else {
            $this->type = $type;
        }

        return $this;
    }

    public function getValue(): int|string|null|float
    {
        return $this->value;
    }

    public function setValue(int|string|null|float $value): self
    {
        $this->value = $value;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function setLabel(?string $label): self
    {
        $this->label = $label;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'type' => $this->getType(),
            'value' => $this->getValue(),
            'resourceClass' => $this->getResourceClass(),
            'label' => $this->getLabel(),
        ];
    }

    /**
     * @param string[] $value
     * @return SettingsStore
     */
    public static function createFromArray(array $value): self
    {
        return (new self())
            ->setValue($value['value'])
            ->setType($value['type'])
            ->setResourceClass($value['resourceClass'] ?? null)
            ->setLabel($value['label'] ?? null);
    }

}