<?php declare(strict_types = 1);

namespace WhiteDigital\SettingsBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use WhiteDigital\SettingsBundle\Entity\Settings;

/**
 * @method Settings|null find($id, $lockMode = null, $lockVersion = null)
 * @method Settings|null findOneBy(array $criteria, array $orderBy = null)
 * @method Settings[]    findAll()
 * @method Settings[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class SettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Settings::class);
    }

    public function findByClassNameOrNull(string $className): ?Settings
    {
        $result = $this->createQueryBuilder('s')
            ->where('s.class = :cn')
            ->setParameter('cn', $className)
            ->getQuery()
            ->getResult();
        return $result[0];
    }

    public function findByClassName(string $className): Settings
    {
        $result = $this->findByClassNameOrNull($className);
        if (!$result) {
            throw new \RuntimeException(sprintf('Settings with class %s not found. You should probably regenerate all settings store by calling /api/settings endpoint.', $className));
        }

        return $result;
    }

    public function save(Settings $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Settings $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
