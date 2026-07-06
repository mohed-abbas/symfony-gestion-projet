<?php

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * @return Notification[]
     */
    public function findForUser(User $user, int $limit = 30): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.user = :user')->setParameter('user', $user)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countUnread(User $user): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.user = :user')->setParameter('user', $user)
            ->andWhere('n.isRead = false')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Marque toutes les notifications non lues d'un utilisateur comme lues.
     */
    public function markAllRead(User $user): void
    {
        $this->createQueryBuilder('n')
            ->update()
            ->set('n.isRead', 'true')
            ->andWhere('n.user = :user')->setParameter('user', $user)
            ->andWhere('n.isRead = false')
            ->getQuery()
            ->execute();
    }
}
