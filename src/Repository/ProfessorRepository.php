<?php

namespace App\Repository;

use App\Entity\ClassLevel;
use App\Entity\Professor;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Professor>
 *
 * @method Professor|null find($id, $lockMode = null, $lockVersion = null)
 * @method Professor|null findOneBy(array $criteria, array $orderBy = null)
 * @method Professor[]    findAll()
 * @method Professor[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ProfessorRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Professor::class);
    }

    /**
     * Professeurs rattachés à un niveau de classe donné (classe d'un étudiant).
     *
     * @return Professor[]
     */
    public function findByClassLevel(?ClassLevel $classLevel): array
    {
        if ($classLevel === null) {
            return [];
        }

        return $this->createQueryBuilder('p')
            ->join('p.classLevels', 'cl')
            ->where('cl = :classLevel')
            ->setParameter('classLevel', $classLevel)
            ->orderBy('p.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

//    /**
//     * @return Professor[] Returns an array of Professor objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('p.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Professor
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
