# Settings Bundle

### What is it?
This bundle adds option to define multiple data structures to be stored as individual settings in a database, with caching support

### System Requirements
PHP 8.1+
Symfony 6.2+

### Installation
The recommended way to install is via Composer:

```shell
composer require whitedigital-eu/settings-bundle
```
---
After this, you need to update your database schema to use Settings entity.  
If using migrations:
```shell
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```
If by schema update:
```shell
bin/console doctrine:schema:update --force
```

This is it, now you can use settings service. It is configured and autowired as `SettingsService`.

To add a new setting, simply create a class that extends SettingsInterface.
```php
use WhiteDigital\SettingsBundle\Contracts\SettingsInterface;

class GasTankInventoryCodeSettings implements SettingsInterface
{
    /** Gāzes balonu materiālu kodi  */
    public ?array $inventoryAltIds = null;
}
```



### Exposing settings to API endpoints via ApiPlatform

To allow changing of the settings via api platform, create a custom ApiResource class, and provider/processor. And add a custom store normalizer.

Provider:
```php
use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use ApiPlatform\Exception\ResourceClassNotFoundException;
use ApiPlatform\Metadata\CollectionOperationInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\SettingsResource;
use Psr\Cache\InvalidArgumentException;
use ReflectionException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use WhiteDigital\SettingsBundle\Repository\SettingsRepository;
use WhiteDigital\SettingsBundle\Service\SettingsService;

/**
 * @implements ProviderInterface<SettingsResource>
 */
class SettingsResourceProvider extends BaseResourceProvider
{
    public function __construct(
        private readonly SettingsService    $settingsService,
        private readonly SettingsRepository $repository,
        iterable                            $collectionExtensions = [],
    )
    {
        parent::__construct($collectionExtensions);
    }

    /**
     * @throws ReflectionException
     * @throws ExceptionInterface
     * @throws ResourceClassNotFoundException
     * @throws InvalidArgumentException
     */
    public function provide(Operation $operation, array $uriVariables = [], array $context = []): object|array|null
    {
        if ($operation instanceof CollectionOperationInterface) {
            return $this->getCollection($operation, $context);
        }
        return $this->getItem($uriVariables['id'], $context);
    }

    /**
     * @param array<string, mixed> $context
     * @throws InvalidArgumentException
     */
    public function getCollection(Operation $operation, array $context = []): mixed
    {
        $this->settingsService->populateDatabase();
        return $this->applyFilterExtensionsToCollection($this->repository->createQueryBuilder('s'),
            new QueryNameGenerator(), $operation, $context);
    }

    /**
     * @param array<string, mixed> $context
     * @throws ExceptionInterface
     * @throws ResourceClassNotFoundException
     * @throws ReflectionException
     */
    public function getItem(mixed $id, array $context = []): ?SettingsResource
    {
        $entity = $this->repository->find($id);
        if (null !== $entity) {
            return SettingsResource::create($entity, $context);
        }
        return null;
    }
}
```

Processor:
```php
use ApiPlatform\Exception\ResourceClassNotFoundException;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Patch;
use App\ApiResource\SettingsResource;
use Psr\Cache\InvalidArgumentException;
use ReflectionException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\Serializer\Exception\ExceptionInterface;
use WhiteDigital\EntityResourceMapper\Mapper\EntityToResourceMapper;
use WhiteDigital\EntityResourceMapper\Mapper\ResourceToEntityMapper;
use WhiteDigital\SettingsBundle\Entity\Settings;
use WhiteDigital\SettingsBundle\Repository\SettingsRepository;
use WhiteDigital\SettingsBundle\Service\SettingsService;

class SettingsResourceProcessor extends BaseResourceProcessor
{
    public function __construct(
        private readonly EntityToResourceMapper $entityToResourceMapper,
        private readonly ResourceToEntityMapper $resourceToEntityMapper,
        private readonly SettingsRepository     $repository,
        private readonly SettingsService        $settingsService,
        private readonly Security               $security,
    )
    {
    }

    /**
     * @throws InvalidArgumentException
     * @throws ResourceClassNotFoundException
     * @throws ReflectionException
     * @throws ExceptionInterface
     */
    public function process(
        mixed     $data,
        Operation $operation,
        array     $uriVariables = [],
        array     $context = []
    ): ?SettingsResource
    {
        if ($operation instanceof Patch) {
            return $this->persist($data, $context);
        }
        throw new MethodNotAllowedHttpException(['GET', 'PATCH']);
    }

    /**
     * @throws InvalidArgumentException
     * @throws ResourceClassNotFoundException
     * @throws ExceptionInterface
     * @throws ReflectionException
     */
    protected function persist(mixed $data, array $context = []): SettingsResource
    {
        /** @var Settings $settings */
        $settings = $data; // already denormalized with custom SettingsStoreNormalizer
        $settings->setLastModifiedBy($this->security->getUser()?->getUserIdentifier());
        $this->repository->save($settings, true);
        $this->settingsService->invalidateCache($settings->getClass());
        return SettingsResource::create($settings, $context);
    }
}
```

Normalizer:
```php
use ApiPlatform\Api\IriConverterInterface;
use App\ApiResource\SettingsResource;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use WhiteDigital\SettingsBundle\Enum\SettingsStoreTypeEnum;
use WhiteDigital\SettingsBundle\Repository\SettingsRepository;
use WhiteDigital\SettingsBundle\Service\SettingsService;

class SettingsStoreNormalizer implements DenormalizerInterface
{
    public function __construct(
        private readonly SettingsRepository    $settingsRepository,
        private readonly IriConverterInterface $iriConverter,
    )
    {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): object
    {
        $id = $context['uri_variables']['id'];
        if (null === $settingsEntity = $this->settingsRepository->find($id)) {
            throw new \RuntimeException('Settings entity not found by id: ' . $id);
        }

        $existingStore = $settingsEntity->getStore();

        foreach ($data['store'] as $key => $value) {
            $existingStoreValue = $existingStore[$key];
            if ((null !== $value['value']) && (null !== $existingStoreValue->getResourceClass())) {
                $resource = $this->iriConverter->getResourceFromIri($value['value']);
                $resourceClass = SettingsService::getShortName($resource::class);
                if ($existingStoreValue->getResourceClass() !== $resourceClass) {
                    throw new \RuntimeException(sprintf('Resource class %s must match property "%s" class %s',
                        $resourceClass, $key, $existingStore[$key]->getResourceClass()));
                }
            }
            if (SettingsStoreTypeEnum::Date === $existingStoreValue->getType()) { // validate only
                try {
                    if (null !== $value['value']) {
                        new \DateTimeImmutable($value['value']);
                    }
                } catch (\Exception $exception) {
                    throw new \RuntimeException('Date format ' . $value['value'] . ' invalid. Expected Y-m-d.');
                }
            }
            // validate only
            if (null !== $value['value'] && SettingsStoreTypeEnum::Array === $existingStoreValue->getType() && !is_array($value['value'])) {
                throw new \RuntimeException($value['value'] . ' Is not a valid array');
            }
            if (null === $value['value'] && in_array($existingStoreValue->getType(), [
                    SettingsStoreTypeEnum::Integer,
                    SettingsStoreTypeEnum::String,
                    SettingsStoreTypeEnum::Float,
                ], true)) {
                throw new \RuntimeException('Integer, String, Float settings values cannot be null.');
            }
            $existingStoreValue->setValue($value['value']);
            $existingStore[$key] = $existingStoreValue;
        }

        $settingsEntity->setStore($existingStore);

        return $settingsEntity;
    }

    /**
     * @param array<string, mixed> $context
     */
    public function supportsDenormalization(
        mixed   $data,
        string  $type,
        ?string $format = null,
        array   $context = []
    ): bool
    {
        return SettingsResource::class === $type;
    }
}
```