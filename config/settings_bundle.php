<?php declare(strict_types = 1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    $services
        ->set('settings_bundle.audit.service.audit')
        ->class(SettingsService::class)
        ->tag('whitedigital.audit', ['priority' => 1, ], )
        ->args([
            service('request_stack'),
            service('security.helper'),
            service('translator'),
            service('doctrine'),
            service('parameter_bag'),
        ]);
};
