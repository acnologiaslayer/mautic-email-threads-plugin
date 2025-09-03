<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEmailThreadsBundle\Entity;

use Doctrine\ORM\EntityRepository;
use Mautic\LeadBundle\Entity\Lead;

class EmailThreadRepository extends EntityRepository
{
    public function findByLead(Lead $lead): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.lead = :lead')
            ->setParameter('lead', $lead)
            ->orderBy('t.lastMessageDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByThreadId(string $threadId): ?EmailThread
    {
        return $this->findOneBy(['threadId' => $threadId]);
    }

    public function findActiveThreads(): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('t.lastMessageDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findThreadsBySubject(string $subject, $leadIdentifier): array
    {
        $queryBuilder = $this->createQueryBuilder('t')
            ->where('t.subject = :subject')
            ->setParameter('subject', $subject);
            
        if (is_numeric($leadIdentifier)) {
            // Lead ID provided
            $queryBuilder->andWhere('t.lead = :leadId')
                ->setParameter('leadId', $leadIdentifier);
        } else {
            // Lead entity provided
            $queryBuilder->andWhere('t.lead = :lead')
                ->setParameter('lead', $leadIdentifier);
        }
        
        return $queryBuilder->orderBy('t.lastMessageDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findExpiredThreads(int $daysOld): array
    {
        $expiredDate = new \DateTime("-{$daysOld} days");
        
        return $this->createQueryBuilder('t')
            ->where('t.lastMessageDate < :expiredDate')
            ->setParameter('expiredDate', $expiredDate)
            ->getQuery()
            ->getResult();
    }
}
