<?php declare(strict_types = 1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use WhiteDigital\SettingsBundle\Service\SettingsService;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    $services
        ->set('settings_bundle.service.settings_service')
        ->class(SettingsService::class)
        ->tag('settings_bundle.settings_service', ['priority' => 1])
        ->args([
            service('request_stack'),
            service('security.helper'),
            service('translator'),
            service('doctrine'),
            service('parameter_bag'),
        ]);
};
