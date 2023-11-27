<?php declare(strict_types=1);

namespace WhiteDigital\SettingsBundle\Service;

use ApiPlatform\Api\IriConverterInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Container\ContainerExceptionInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\TaggedLocator;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use WhiteDigital\EntityResourceMapper\Resource\BaseResource;
use WhiteDigital\SettingsBundle\Contracts\SettingsInterface;
use WhiteDigital\SettingsBundle\Entity\Settings;
use WhiteDigital\SettingsBundle\Enum\SettingsStoreTypeEnum;
use WhiteDigital\SettingsBundle\Exception\SettingsException;
use WhiteDigital\SettingsBundle\Repository\SettingsRepository;

class SettingsService
{
    public function __construct(
        private readonly EntityManagerInterface $manager,
        private readonly SettingsRepository $settingsRepository,
        private readonly Security $security,
        private readonly TagAwareCacheInterface $cache,
        private readonly IriConverterInterface $iriConverter,
        #[TaggedLocator(tag: 'settings_bundle.settings')] private readonly ServiceLocator $settingsList,
    )
    {
    }

    /**
     * @template C of SettingsInterface
     *
     * @param class-string<C> $class
     *
     * @return C
     *
     * @throws SettingsException
     */
    public function getSettings(string $class): SettingsInterface
    {
        try {
            $settingsService = $this->settingsList->get($class);

            $reflect = new \ReflectionClass($settingsService);
            foreach ($reflect->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
                $propertyName = $property->getName();
                $shortName = self::getShortName($class);
                // 1. Use cached value or 2. retrieve from database, or 3. use default property value
                $settingsService->{$propertyName} = $this->cache->get("$shortName.$propertyName",
                    function (ItemInterface $item) use (
                        $class,
                        $propertyName,
                        $property,
                        $settingsService,
                        $shortName
                    ) {
                        $item->tag($shortName);
                        $data = $this->settingsRepository->findByClassNameOrNull($class);
                        if (null !== $data) {
                            $values = $data->getStore();
                            if (array_key_exists($propertyName, $values)) {
                                return $this->settingsStoreToValue($values[$propertyName]);
                            }
                            $values[$propertyName] = $this->valueToSettingsStore($property, $settingsService);
                            $data->setStore($values);
                        } else {
                            $currentUser = $this->security->getUser();
                            $data = (new Settings())->setLastModifiedBy($currentUser?->getUserIdentifier())
                                ->setClass($class)->setStore([
                                    $propertyName => $this->valueToSettingsStore($property, $settingsService),
                                ]);
                            $this->manager->persist($data);
                        }

                        $this->manager->flush();

                        return $property->getValue($settingsService);
                    });
            }

            return $settingsService;
        } catch (ContainerExceptionInterface|InvalidArgumentException|\ReflectionException $exception) {
            throw new SettingsException($exception->getMessage());
        }
    }

    /**
     * Invalidate cache based on tag, which is based on short Settings class name.
     *
     * @param class-string|string $class
     *
     * @throws InvalidArgumentException
     */
    public function invalidateCache(string $class): void
    {
        $this->cache->invalidateTags([self::getShortName($class)]);
    }

    /**
     * Calls each Settings class to populate database.
     *
     * @throws InvalidArgumentException
     */
    public function populateDatabase(): void
    {
        /** @var class-string $settingsService */
        foreach ($this->settingsList->getProvidedServices() as $settingsService) {
            $this->invalidateCache($settingsService);
            $this->getSettings($settingsService);
        }
    }

    /**
     * Return class name from any FQCN.
     */
    public static function getShortName(string $fqcn): string
    {
        return basename(str_replace('\\', '/', $fqcn));
    }

    private function settingsStoreToValue(SettingsStore $store): string|int|BaseResource|\DateTimeImmutable|null|float|array|bool
    {
        return match ($store->getType()) {
            SettingsStoreTypeEnum::String, SettingsStoreTypeEnum::Integer, SettingsStoreTypeEnum::Float, SettingsStoreTypeEnum::Array, SettingsStoreTypeEnum::Boolean => $store->getValue(),
            SettingsStoreTypeEnum::Date => $this->returnDateTimeObject($store->getValue()),
            SettingsStoreTypeEnum::Resource => $store->getValue()
                ? $this->iriConverter->getResourceFromIri($store->getValue()) : null,
        };
    }

    private function valueToSettingsStore(\ReflectionProperty $reflectionProperty, object $object): SettingsStore
    {
        if (!$reflectionProperty->getType() instanceof \ReflectionNamedType) {
            throw new \RuntimeException('Type is not ReflectionNamedType');
        }

        return match ($reflectionProperty->getType()->getName()) {
            'int' => (new SettingsStore())->setType(SettingsStoreTypeEnum::Integer)
                ->setValue($reflectionProperty->getValue($object))
                ->setLabel($this->extractLabelFromComment($reflectionProperty)),
            'string' => (new SettingsStore())->setType(SettingsStoreTypeEnum::String)
                ->setValue($reflectionProperty->getValue($object))
                ->setLabel($this->extractLabelFromComment($reflectionProperty)),
            'DateTimeImmutable' => (new SettingsStore())->setType(SettingsStoreTypeEnum::Date)
                ->setValue($this->returnIsoDate($reflectionProperty->getValue($object)))
                ->setLabel($this->extractLabelFromComment($reflectionProperty)),
            'float' => (new SettingsStore())->setType(SettingsStoreTypeEnum::Float)
                ->setValue($reflectionProperty->getValue($object))
                ->setLabel($this->extractLabelFromComment($reflectionProperty)),
            'array' => (new SettingsStore())->setType(SettingsStoreTypeEnum::Array)
                ->setValue($reflectionProperty->getValue($object))
                ->setLabel($this->extractLabelFromComment($reflectionProperty)),
            'bool' => (new SettingsStore())->setType(SettingsStoreTypeEnum::Boolean)
                ->setValue($reflectionProperty->getValue($object))
                ->setLabel($this->extractLabelFromComment($reflectionProperty)),
            default => (new SettingsStore())->setType(SettingsStoreTypeEnum::Resource)
                ->setValue($reflectionProperty->getValue($object)
                    ? $this->iriConverter->getIriFromResource($reflectionProperty->getValue($object)) : null)
                ->setResourceClass(self::getShortName($reflectionProperty->getType()->getName()))
                ->setLabel($this->extractLabelFromComment($reflectionProperty)),
        };
    }

    private function returnIsoDate(?\DateTimeImmutable $datetime): ?string
    {
        return $datetime?->format('Y-m-d');
    }

    /**
     * @param string|null $isoDate in format Y-m-d
     */
    private function returnDateTimeObject(?string $isoDate): ?\DateTimeImmutable
    {
        if (empty($isoDate)) {
            return null;
        }
        try {
            $date = new \DateTimeImmutable($isoDate);
        } catch (\Exception $exception) {
            throw new \RuntimeException('Date format invalid. Expected Y-m-d.');
        }

        return $date;
    }

    private function extractLabelFromComment(\ReflectionProperty $reflectionProperty): ?string
    {
        if ($label = $reflectionProperty->getDocComment()) {
            preg_match('/^\/\*\*(.+)\*\/$/', $label, $output);
            return trim($output[1]);
        }

        return null;
    }

}
