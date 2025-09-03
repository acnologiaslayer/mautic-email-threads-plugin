<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEmailThreadsBundle\Model;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\EmailBundle\Entity\Email;
use Mautic\EmailBundle\Entity\Stat;
use Mautic\EmailBundle\Event\EmailSendEvent;
use MauticPlugin\MauticEmailThreadsBundle\Entity\EmailThread;
use MauticPlugin\MauticEmailThreadsBundle\Entity\EmailThreadMessage;

class EmailThreadMessageModel
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public function getRepository()
    {
        return $this->entityManager->getRepository(EmailThreadMessage::class);
    }

    public function getEntity($id = null): ?EmailThreadMessage
    {
        if (null === $id) {
            return new EmailThreadMessage();
        }

        return $this->getRepository()->find($id);
    }

    public function saveEntity(EmailThreadMessage $entity): void
    {
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    public function addMessageToThread(
        EmailThread $thread,
        Email $email,
        ?Stat $emailStat,
        EmailSendEvent $event
    ): EmailThreadMessage {
        $message = new EmailThreadMessage();
        $message->setThread($thread);
        $message->setEmail($email);
        $message->setEmailStat($emailStat);
        $message->setSubject($email->getSubject());
        
        // Get content from event or email
        $content = $event->getContent() ?: $email->getCustomHtml() ?: $email->getPlainText();
        $message->setContent($content);
        
        // Set sender information
        $fromEmail = $email->getFromAddress() ?: $event->getFrom();
        $fromName = $email->getFromName() ?: '';
        
        $message->setFromEmail($fromEmail);
        $message->setFromName($fromName);
        $message->setDateSent(new \DateTime());
        
        // Enhanced email type detection for campaigns and channels
        $emailType = $this->determineEmailType($email, $event);
        $message->setEmailType($emailType);
        
        // Enhanced metadata for campaigns, segments, and channels
        $metadata = $this->buildMessageMetadata($email, $event);
        $message->setMetadata($metadata);

        $this->saveEntity($message);
        
        // Update thread
        $thread->addMessage($message);
        $thread->setLastMessageDate($message->getDateSent());
        
        return $message;
    }

    /**
     * Build comprehensive metadata for campaigns, segments, and channels
     */
    private function buildMessageMetadata(Email $email, EmailSendEvent $event): array
    {
        $metadata = [
            'source' => $event->getSource() ?? 'unknown',
            'tokens' => $event->getTokens() ?? [],
            'email_id' => $email->getId(),
            'email_name' => $email->getName(),
            'email_type' => $email->getEmailType(),
        ];

        // Campaign information
        if ($email->getCategory()) {
            $metadata['category_id'] = $email->getCategory()->getId();
            $metadata['category_title'] = $email->getCategory()->getTitle();
        }

        // Campaign-specific data
        $campaignId = $event->getIdHash()['campaignId'] ?? null;
        if ($campaignId) {
            $metadata['campaign_id'] = $campaignId;
            $metadata['is_campaign_email'] = true;
        }

        // Segment/list information
        $lists = $email->getLists();
        if (!empty($lists)) {
            $metadata['segments'] = [];
            foreach ($lists as $list) {
                $metadata['segments'][] = [
                    'id' => $list->getId(),
                    'name' => $list->getName(),
                    'alias' => $list->getAlias(),
                ];
            }
        }

        // Channel information (for multi-channel campaigns)
        if ($event->getSource()) {
            $source = $event->getSource();
            if (strpos($source, 'campaign') !== false) {
                $metadata['channel'] = 'campaign';
                if (strpos($source, 'trigger') !== false) {
                    $metadata['campaign_type'] = 'triggered';
                } else {
                    $metadata['campaign_type'] = 'segment';
                }
            } elseif (strpos($source, 'broadcast') !== false) {
                $metadata['channel'] = 'broadcast';
            } elseif (strpos($source, 'api') !== false) {
                $metadata['channel'] = 'api';
            }
        }

        // Lead/contact information from event
        $leadData = $event->getLead();
        if (is_array($leadData)) {
            $metadata['lead_id'] = $leadData['id'] ?? null;
            $metadata['lead_email'] = $leadData['email'] ?? null;
            $metadata['lead_name'] = trim(($leadData['firstname'] ?? '') . ' ' . ($leadData['lastname'] ?? ''));
        }

        return $metadata;
    }

    public function getMessagesByThread(EmailThread $thread): array
    {
        try {
            // First try the Doctrine approach
            $messages = $this->getRepository()->createQueryBuilder('m')
                ->where('m.thread = :thread')
                ->setParameter('thread', $thread)
                ->orderBy('m.dateSent', 'ASC')
                ->getQuery()
                ->getResult();
                
            error_log("EmailThreads: Found " . count($messages) . " messages via Doctrine for thread " . $thread->getId());
            return $messages;
            
        } catch (\Exception $e) {
            // If Doctrine queries fail, try raw SQL approach
            error_log("EmailThreads: Doctrine query failed, trying raw SQL: " . $e->getMessage());
            return $this->getMessagesByThreadRaw($thread->getId());
        }
    }

    /**
     * Get messages using raw SQL as fallback
     */
    private function getMessagesByThreadRaw(int $threadId): array
    {
        try {
            $connection = $this->entityManager->getConnection();
            $sql = 'SELECT * FROM email_thread_messages WHERE thread_id = ? ORDER BY date_sent ASC';
            $result = $connection->executeQuery($sql, [$threadId]);
            
            $messages = [];
            while ($row = $result->fetchAssociative()) {
                // Create simple objects with the data we need
                $message = new \stdClass();
                $message->id = $row['id'];
                $message->subject = $row['subject'];
                $message->content = $row['content'];
                $message->fromEmail = $row['from_email'];
                $message->fromName = $row['from_name'];
                $message->dateSent = new \DateTime($row['date_sent']);
                $message->emailType = $row['email_type'];
                $messages[] = $message;
            }
            
            return $messages;
        } catch (\Exception $e) {
            error_log("EmailThreads: Raw SQL query also failed: " . $e->getMessage());
            return [];
        }
    }

    private function determineEmailType(Email $email, EmailSendEvent $event): string
    {
        // Enhanced email type detection
        $source = $event->getSource();
        
        // Check source for specific patterns
        if ($source) {
            // Campaign emails
            if (str_contains($source, 'campaign')) {
                if (str_contains($source, 'trigger') || str_contains($source, 'event')) {
                    return 'campaign_triggered';
                } else {
                    return 'campaign_segment';
                }
            }
            
            // Broadcast/segment emails
            if (str_contains($source, 'broadcast') || str_contains($source, 'segment')) {
                return 'broadcast';
            }
            
            // API emails
            if (str_contains($source, 'api')) {
                return 'api';
            }
            
            // Form actions
            if (str_contains($source, 'form')) {
                return 'form_action';
            }
            
            // Point actions
            if (str_contains($source, 'point')) {
                return 'point_action';
            }
        }
        
        // Check email type from entity
        $emailType = $email->getEmailType();
        if ($emailType === 'list') {
            return 'segment_email';
        } elseif ($emailType === 'template') {
            return 'template';
        }
        
        // Check if it's part of a campaign
        $campaignId = $event->getIdHash()['campaignId'] ?? null;
        if ($campaignId) {
            return 'campaign';
        }
        
        // Check if it has segments/lists
        $lists = $email->getLists();
        if (!empty($lists)) {
            return 'segment_email';
        }
        
        // Default fallback
        return 'direct_send';
    }

    protected function dispatchEvent($action, &$entity, $isNew = false, \Symfony\Component\EventDispatcher\Event $event = null)
    {
        // Override to prevent default events if needed
        return null;
    }
}
