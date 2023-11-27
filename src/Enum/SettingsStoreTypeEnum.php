<?php

declare(strict_types=1);

namespace WhiteDigital\SettingsBundle\Enum;

enum SettingsStoreTypeEnum: string
{
    case Integer = 'integer';
    case String = 'string';
    case Resource = 'resource';
    case Date = 'date';
    case Float = 'float';
    case Array = 'array';
    case Boolean = 'boolean';
}
