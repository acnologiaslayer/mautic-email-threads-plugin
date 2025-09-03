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
        
        // Determine email type
        $emailType = $this->determineEmailType($email, $event);
        $message->setEmailType($emailType);
        
        // Set metadata
        $metadata = [
            'campaign_id' => $email->getCategory() ? $email->getCategory()->getId() : null,
            'source' => $event->getSource() ?? 'unknown',
            'tokens' => $event->getTokens() ?? [],
        ];
        $message->setMetadata($metadata);

        $this->saveEntity($message);
        
        // Update thread
        $thread->addMessage($message);
        $thread->setLastMessageDate($message->getDateSent());
        
        return $message;
    }

    public function getMessagesByThread(EmailThread $thread): array
    {
        return $this->getRepository()->createQueryBuilder('m')
            ->where('m.thread = :thread')
            ->setParameter('thread', $thread)
            ->orderBy('m.dateSent', 'ASC')
            ->getQuery()
            ->getResult();
    }

    private function determineEmailType(Email $email, EmailSendEvent $event): string
    {
        // Try to determine the email type based on various factors
        $source = $event->getSource();
        
        if ($source) {
            if (str_contains($source, 'campaign')) {
                return 'campaign';
            }
            if (str_contains($source, 'broadcast')) {
                return 'broadcast';
            }
        }
        
        // Check if it's a campaign email
        if ($email->getEmailType() === 'list') {
            return 'broadcast';
        }
        
        // Default to template
        return 'template';
    }

    protected function dispatchEvent($action, &$entity, $isNew = false, \Symfony\Component\EventDispatcher\Event $event = null)
    {
        // Override to prevent default events if needed
        return null;
    }
}
