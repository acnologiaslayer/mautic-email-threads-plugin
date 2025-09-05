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
            error_log('EmailThreads: Used custom repository method, found ' . count($existingThreads) . ' threads');
        } catch (\Exception $e) {
            // Fallback to basic doctrine query if custom repository method fails
            error_log('EmailThreads: Custom repository failed: ' . $e->getMessage() . ', using fallback');
            
            // Try to use raw SQL as a last resort
            try {
                $connection = $this->em->getConnection();
                $sql = 'SELECT * FROM email_threads WHERE lead_id = ?';
                $result = $connection->executeQuery($sql, [$leadId]);
                $existingThreads = [];
                
                while ($row = $result->fetchAssociative()) {
                    // Create a simple object with the data we need
                    $thread = new \stdClass();
                    $thread->id = $row['id'];
                    $thread->threadId = $row['thread_id'];
                    $thread->subject = $row['subject'] ?? 'Unknown Subject';
                    $thread->leadId = $row['lead_id'];
                    $existingThreads[] = $thread;
                }
                
                error_log('EmailThreads: Raw SQL fallback found ' . count($existingThreads) . ' threads');
            } catch (\Exception $sqlException) {
                error_log('EmailThreads: Raw SQL fallback also failed: ' . $sqlException->getMessage());
                $existingThreads = [];
            }
        }
        
        if (!empty($existingThreads)) {
            $existingThread = $existingThreads[0];
            
            // Handle both entity objects and stdClass objects from raw SQL
            if ($existingThread instanceof EmailThread) {
                $thread = $existingThread;
                $thread->setLastMessageDate(new \DateTime());
                $this->saveEntity($thread);
                error_log('EmailThreads: Using existing thread: ' . $thread->getThreadId());
                return $thread;
            } else {
                // This is a stdClass from raw SQL, we need to load the actual entity
                $threadId = $existingThread->id;
                $thread = $this->em->find(EmailThread::class, $threadId);
                if ($thread) {
                    $thread->setLastMessageDate(new \DateTime());
                    $this->saveEntity($thread);
                    error_log('EmailThreads: Using existing thread from raw SQL: ' . $thread->getThreadId());
                    return $thread;
                } else {
                    error_log('EmailThreads: Could not load thread entity with ID: ' . $threadId);
                }
            }
        }

        error_log('EmailThreads: Creating new thread for subject: ' . $subject);

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
        error_log('EmailThreads: Successfully created new thread: ' . $thread->getThreadId());
        
        // Verify the thread was actually saved to database
        $this->verifyThreadSaved($thread);
        
        return $thread;
    }

    public function findByThreadId(string $threadId): ?EmailThread
    {
        try {
            return $this->getRepository()->findByThreadId($threadId);
        } catch (\Exception $e) {
            error_log('EmailThreads: findByThreadId repository method failed: ' . $e->getMessage() . ', using raw SQL fallback');
            
            // Fallback to raw SQL
            try {
                $connection = $this->em->getConnection();
                $sql = 'SELECT * FROM email_threads WHERE thread_id = ? LIMIT 1';
                $result = $connection->executeQuery($sql, [$threadId]);
                $row = $result->fetchAssociative();
                
                if ($row) {
                    $thread = $this->em->find(EmailThread::class, $row['id']);
                    error_log('EmailThreads: Raw SQL fallback found thread with ID: ' . ($thread ? $thread->getId() : 'null'));
                    return $thread;
                }
                
                error_log('EmailThreads: Raw SQL fallback found no thread with thread_id: ' . $threadId);
                return null;
            } catch (\Exception $sqlException) {
                error_log('EmailThreads: Raw SQL fallback also failed: ' . $sqlException->getMessage());
                return null;
            }
        }
    }

    public function findByLead(Lead $lead): array
    {
        try {
            return $this->getRepository()->findByLead($lead);
        } catch (\Exception $e) {
            error_log('EmailThreads: findByLead repository method failed: ' . $e->getMessage() . ', using raw SQL fallback');
            
            // Fallback to raw SQL
            try {
                $connection = $this->em->getConnection();
                $sql = 'SELECT * FROM email_threads WHERE lead_id = ? ORDER BY last_message_date DESC';
                $result = $connection->executeQuery($sql, [$lead->getId()]);
                $threads = [];
                
                while ($row = $result->fetchAssociative()) {
                    // Load the actual entity
                    $thread = $this->em->find(EmailThread::class, $row['id']);
                    if ($thread) {
                        $threads[] = $thread;
                    }
                }
                
                error_log('EmailThreads: Raw SQL fallback found ' . count($threads) . ' threads for lead');
                return $threads;
            } catch (\Exception $sqlException) {
                error_log('EmailThreads: Raw SQL fallback also failed: ' . $sqlException->getMessage());
                return [];
            }
        }
    }

    public function findActiveThreads(): array
    {
        try {
            return $this->getRepository()->findActiveThreads();
        } catch (\Exception $e) {
            error_log('EmailThreads: findActiveThreads repository method failed: ' . $e->getMessage() . ', using raw SQL fallback');
            
            // Fallback to raw SQL
            try {
                $connection = $this->em->getConnection();
                $sql = 'SELECT * FROM email_threads WHERE is_active = 1 ORDER BY last_message_date DESC';
                $result = $connection->executeQuery($sql);
                $threads = [];
                
                while ($row = $result->fetchAssociative()) {
                    // Load the actual entity
                    $thread = $this->em->find(EmailThread::class, $row['id']);
                    if ($thread) {
                        $threads[] = $thread;
                    }
                }
                
                error_log('EmailThreads: Raw SQL fallback found ' . count($threads) . ' active threads');
                return $threads;
            } catch (\Exception $sqlException) {
                error_log('EmailThreads: Raw SQL fallback also failed: ' . $sqlException->getMessage());
                return [];
            }
        }
    }

    public function deactivateExpiredThreads(int $daysOld = 30): int
    {
        try {
            $expiredThreads = $this->getRepository()->findExpiredThreads($daysOld);
        } catch (\Exception $e) {
            error_log('EmailThreads: findExpiredThreads repository method failed: ' . $e->getMessage() . ', using raw SQL fallback');
            
            // Fallback to raw SQL
            try {
                $connection = $this->em->getConnection();
                $expiredDate = date('Y-m-d H:i:s', strtotime("-{$daysOld} days"));
                $sql = 'SELECT * FROM email_threads WHERE last_message_date < ?';
                $result = $connection->executeQuery($sql, [$expiredDate]);
                $expiredThreads = [];
                
                while ($row = $result->fetchAssociative()) {
                    // Load the actual entity
                    $thread = $this->em->find(EmailThread::class, $row['id']);
                    if ($thread) {
                        $expiredThreads[] = $thread;
                    }
                }
                
                error_log('EmailThreads: Raw SQL fallback found ' . count($expiredThreads) . ' expired threads');
            } catch (\Exception $sqlException) {
                error_log('EmailThreads: Raw SQL fallback also failed: ' . $sqlException->getMessage());
                $expiredThreads = [];
            }
        }
        
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

    /**
     * Verify that a thread was actually saved to the database
     */
    private function verifyThreadSaved(EmailThread $thread): void
    {
        try {
            // Try to retrieve the thread from database
            $savedThread = $this->getRepository()->find($thread->getId());
            if ($savedThread) {
                error_log('EmailThreads: VERIFIED - Thread saved to database successfully. ID: ' . $thread->getId() . ', ThreadID: ' . $thread->getThreadId());
            } else {
                error_log('EmailThreads: ERROR - Thread NOT found in database after save. ID: ' . $thread->getId());
            }
            
            // Also try to find by threadId
            $threadById = $this->getRepository()->findByThreadId($thread->getThreadId());
            if ($threadById) {
                error_log('EmailThreads: VERIFIED - Thread found by threadId: ' . $thread->getThreadId());
            } else {
                error_log('EmailThreads: ERROR - Thread NOT found by threadId: ' . $thread->getThreadId());
            }
            
        } catch (\Exception $e) {
            error_log('EmailThreads: ERROR - Failed to verify thread save: ' . $e->getMessage());
        }
    }

    protected function dispatchEvent($action, &$entity, $isNew = false, \Symfony\Component\EventDispatcher\Event $event = null)
    {
        // Override to prevent default events if needed
        return null;
    }
}
