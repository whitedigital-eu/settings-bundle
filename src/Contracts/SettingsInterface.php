<?php

declare(strict_types=1);

namespace WhiteDigital\SettingsBundle\Contracts;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag(name: 'settings_bundle.settings')]
interface SettingsInterface
{
}