<?php

declare(strict_types=1);

namespace WhiteDigital\SettingsBundle\Contracts;

interface SettingsStoreInterface
{
    public function getStorageValue(mixed $rawValue): int|null|string;

    public function getRuntimeValue(int|null|string $rawValue): mixed;

    public function isPropertyTypeSupported(string $propertyType): bool;
}