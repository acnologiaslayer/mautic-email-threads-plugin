<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEmailThreadsBundle\Model;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\EmailBundle\Entity\Email;
use Mautic\EmailBundle\Event\EmailSendEvent;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\MauticEmailThreadsBundle\Entity\EmailThread;
use MauticPlugin\MauticEmailThreadsBundle\Entity\EmailThreadRepository;

class EmailThreadModel
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function getRepository(): EmailThreadRepository
    {
        return $this->entityManager->getRepository(EmailThread::class);
    }

    public function getEntity($id = null): ?EmailThread
    {
        if (null === $id) {
            return new EmailThread();
        }

        return $this->getRepository()->find($id);
    }

    public function saveEntity(EmailThread $entity): void
    {
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function findOrCreateThread(Lead $lead, Email $email, EmailSendEvent $event): EmailThread
    {
        $subject = $this->cleanSubject($email->getSubject());
        
        // Check if thread already exists for this lead and subject
        $existingThreads = $this->getRepository()->findThreadsBySubject($subject, $lead);
        
        if (!empty($existingThreads)) {
            $thread = $existingThreads[0]; // Use the first matching thread
            $thread->setLastMessageDate(new \DateTime());
            $this->saveEntity($thread);
            return $thread;
        }

        // Create new thread
        $thread = new EmailThread();
        $thread->setLead($lead);
        $thread->setSubject($subject);
        
        // Set sender information
        $fromEmail = $email->getFromAddress() ?: $event->getFrom();
        $fromName = $email->getFromName() ?: '';
        
        $thread->setFromEmail($fromEmail);
        $thread->setFromName($fromName);
        $thread->setFirstMessageDate(new \DateTime());
        $thread->setLastMessageDate(new \DateTime());

        $this->saveEntity($thread);
        
        return $thread;
    }

    public function findByThreadId(string $threadId): ?EmailThread
    {
        return $this->getRepository()->findByThreadId($threadId);
    }

    public function findByLead(Lead $lead): array
    {
        return $this->getRepository()->findByLead($lead);
    }

    public function findActiveThreads(): array
    {
        return $this->getRepository()->findActiveThreads();
    }

    public function deactivateExpiredThreads(int $daysOld = 30): int
    {
        $expiredThreads = $this->getRepository()->findExpiredThreads($daysOld);
        $deactivatedCount = 0;

        foreach ($expiredThreads as $thread) {
            $thread->setIsActive(false);
            $this->saveEntity($thread);
            $deactivatedCount++;
        }

        return $deactivatedCount;
    }

    private function cleanSubject(string $subject): string
    {
        // Remove common reply prefixes
        $subject = preg_replace('/^(Re:|RE:|Fwd:|FWD:|Fw:)\s*/i', '', $subject);
        $subject = trim($subject);
        
        return $subject;
    }

    protected function dispatchEvent($action, &$entity, $isNew = false, \Symfony\Component\EventDispatcher\Event $event = null)
    {
        // Override to prevent default events if needed
        return null;
    }
}
