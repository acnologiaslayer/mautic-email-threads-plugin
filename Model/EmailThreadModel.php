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
        private EntityManagerInterface $em
    ) {
    }

    public function getRepository()
    {
        return $this->em->getRepository(EmailThread::class);
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
        $this->em->persist($entity);
        $this->em->flush();
    }

    public function findOrCreateThread($leadData, Email $email, EmailSendEvent $event): EmailThread
    {
        $subject = $this->cleanSubject($email->getSubject());
        
        // Handle both array and entity lead data
        $leadId = null;
        $leadEmail = null;
        $leadEntity = null;
        
        if (is_array($leadData)) {
            $leadId = $leadData['id'] ?? null;
            $leadEmail = $leadData['email'] ?? null;
        } else {
            $leadEntity = $leadData;
            $leadId = $leadEntity->getId();
            $leadEmail = $leadEntity->getEmail();
        }
        
        if (!$leadId || !$leadEmail) {
            throw new \InvalidArgumentException('Invalid lead data provided');
        }
        
        // Check if thread already exists for this lead and subject
        try {
            $existingThreads = $this->getRepository()->findThreadsBySubject($subject, $leadId);
            file_put_contents('/tmp/emailthreads_debug.log', date('Y-m-d H:i:s') . " - Used custom repository method, found " . count($existingThreads) . " threads\n", FILE_APPEND);
        } catch (\Exception $e) {
            // Fallback to basic doctrine query if custom repository method fails
            file_put_contents('/tmp/emailthreads_debug.log', date('Y-m-d H:i:s') . " - Custom repository failed: " . $e->getMessage() . ", using fallback\n", FILE_APPEND);
            $existingThreads = $this->em->createQueryBuilder()
                ->select('t')
                ->from(EmailThread::class, 't')
                ->where('t.subject = :subject')
                ->andWhere('t.lead = :leadId')
                ->setParameter('subject', $subject)
                ->setParameter('leadId', $leadId)
                ->getQuery()
                ->getResult();
            file_put_contents('/tmp/emailthreads_debug.log', date('Y-m-d H:i:s') . " - Fallback query found " . count($existingThreads) . " threads\n", FILE_APPEND);
        }
        
        if (!empty($existingThreads)) {
            $thread = $existingThreads[0]; // Use the first matching thread
            $thread->setLastMessageDate(new \DateTime());
            $this->saveEntity($thread);
            file_put_contents('/tmp/emailthreads_debug.log', date('Y-m-d H:i:s') . " - Using existing thread: " . $thread->getThreadId() . "\n", FILE_APPEND);
            return $thread;
        }

        file_put_contents('/tmp/emailthreads_debug.log', date('Y-m-d H:i:s') . " - Creating new thread for subject: " . $subject . "\n", FILE_APPEND);

        // Create new thread
        $thread = new EmailThread();
        
        // Handle lead data properly for both entity and array cases
        if ($leadEntity) {
            $thread->setLead($leadEntity);
        } else {
            // For array lead data, we need to get the Lead entity
            // Try to load the lead entity from the lead ID
            try {
                $leadRepository = $this->em->getRepository(Lead::class);
                $leadEntity = $leadRepository->find($leadId);
                if ($leadEntity) {
                    $thread->setLead($leadEntity);
                } else {
                    throw new \RuntimeException("Lead not found with ID: " . $leadId);
                }
            } catch (\Exception $e) {
                // If we can't load the lead entity, we can't create a proper thread
                throw new \RuntimeException("Failed to load lead entity: " . $e->getMessage());
            }
        }
        
        $thread->setSubject($subject);
        
        // Set sender information
        $fromEmail = $email->getFromAddress() ?: $event->getFrom();
        $fromName = $email->getFromName() ?: '';
        
        $thread->setFromEmail($fromEmail);
        $thread->setFromName($fromName);
        $thread->setFirstMessageDate(new \DateTime());
        $thread->setLastMessageDate(new \DateTime());

        $this->saveEntity($thread);
        file_put_contents('/tmp/emailthreads_debug.log', date('Y-m-d H:i:s') . " - Successfully created new thread: " . $thread->getThreadId() . "\n", FILE_APPEND);
        
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
