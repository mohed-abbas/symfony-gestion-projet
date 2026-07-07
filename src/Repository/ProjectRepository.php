<?php

namespace App\Repository;

use App\Entity\Project;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Project>
 */
class ProjectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Project::class);
    }

    /**
     * Projects the user belongs to, org + lead pre-loaded to avoid N+1 on the list.
     *
     * @return Project[]
     */
    public function findForUser(User $user): array
    {
        return $this->createQueryBuilder('p')
            ->innerJoin('p.memberships', 'm')
            ->andWhere('m.user = :user')
            ->setParameter('user', $user)
            ->leftJoin('p.organization', 'o')->addSelect('o')
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * All projects with org + memberships pre-loaded for the admin list.
     *
     * @return Project[]
     */
    public function findAllForAdmin(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.organization', 'o')->addSelect('o')
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countByStatus(string $status): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.status = :status')->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
