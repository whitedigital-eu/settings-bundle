<?php

declare(strict_types=1);

namespace WhiteDigital\SettingsBundle\SettingsStore;

use Symfony\Component\Serializer\Encoder\JsonEncoder;
use Symfony\Component\Serializer\SerializerInterface;
use WhiteDigital\SettingsBundle\Contracts\SettingsStoreInterface;

class SymfonySerializableSettingsStore implements SettingsStoreInterface
{
    public function __construct(protected readonly SerializerInterface $serializer)
    {
    }

    public function getStorageValue(mixed $rawValue): int|null|string
    {
        return $this->serializer->serialize($rawValue, JsonEncoder::FORMAT);
    }

    public function getRuntimeValue(int|null|string $rawValue): mixed
    {
        return $this->serializer->deserialize($rawValue);
    }

    public function isPropertyTypeSupported(string $propertyType): bool
    {
        return $this->serializer->supports
    }
}