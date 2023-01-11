<?php

declare(strict_types = 1);

namespace WhiteDigital\SettingsBundle\Contracts;

interface SettingsServiceInterface
{
    public function getSettings(string $className): SettingsInterface;
}
