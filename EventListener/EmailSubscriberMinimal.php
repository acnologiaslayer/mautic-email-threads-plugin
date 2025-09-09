<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEmailThreadsBundle\EventListener;

use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Event\EmailSendEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Doctrine\ORM\EntityManagerInterface;

class EmailSubscriberMinimal implements EventSubscriberInterface
{
    private EntityManagerInterface $entityManager;
    
    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }
    public static function getSubscribedEvents(): array
    {
        $events = [];
        
        // Try to detect available events dynamically
        if (class_exists('Mautic\EmailBundle\EmailEvents')) {
            $reflection = new \ReflectionClass('Mautic\EmailBundle\EmailEvents');
            $constants = $reflection->getConstants();
            
            if (isset($constants['EMAIL_SEND'])) {
                $events[$constants['EMAIL_SEND']] = ['onEmailSend', 100];
                error_log('EmailThreads: Minimal subscriber subscribed to EMAIL_SEND event');
            } elseif (isset($constants['EMAIL_ON_SEND'])) {
                $events[$constants['EMAIL_ON_SEND']] = ['onEmailSend', 50];
                error_log('EmailThreads: Minimal subscriber subscribed to EMAIL_ON_SEND event');
            }
        }
        
        // Fallback to string-based event names
        if (empty($events)) {
            $events['mautic.email.on_send'] = ['onEmailSend', 50];
            $events['mautic.email.send'] = ['onEmailSend', 100];
            error_log('EmailThreads: Minimal subscriber using fallback event subscriptions');
        }
        
        return $events;
    }

    public function onEmailSend(EmailSendEvent $event): void
    {
        try {
            error_log('EmailThreads: EmailSubscriberMinimal::onEmailSend called');
            
            // Get basic information
            $email = $event->getEmail();
            $leadData = $event->getLead();
            $content = $event->getContent();
            
            error_log('EmailThreads: Email type: ' . ($email ? $email->getEmailType() : 'null'));
            error_log('EmailThreads: Email subject: ' . ($email ? $email->getSubject() : 'null'));
            error_log('EmailThreads: Lead data type: ' . (is_array($leadData) ? 'array' : (is_object($leadData) ? get_class($leadData) : gettype($leadData))));
            error_log('EmailThreads: Content length: ' . ($content ? strlen($content) : 'null'));
            
            // Add simple test message
            $this->addSimpleTestMessage($event);
            
            // Add simulated threading content
            $this->addThreadingContent($event);
            
        } catch (\Exception $e) {
            error_log('EmailThreads: onEmailSend - Error: ' . $e->getMessage());
            error_log('EmailThreads: onEmailSend - Stack trace: ' . $e->getTraceAsString());
        }
    }
    
    private function addSimpleTestMessage(EmailSendEvent $event): void
    {
        try {
            $content = $event->getContent();
            if (!$content) {
                error_log('EmailThreads: addSimpleTestMessage - No content to modify');
                return;
            }
            
            $testMessage = '<div style="margin: 20px 0; padding: 15px; background: #e3f2fd; border-left: 4px solid #2196f3; font-family: Arial, sans-serif;">
                <strong style="color: #1976d2;">🔧 Email Threads Plugin Test (Minimal)</strong><br>
                <span style="color: #424242; font-size: 14px;">This message confirms the Email Threads plugin is working. If you see this, the plugin is active and processing emails.</span>
            </div>';
            
            $newContent = $content . $testMessage;
            $event->setContent($newContent);
            
            error_log('EmailThreads: addSimpleTestMessage - Added simple test message to email');
        } catch (\Exception $e) {
            error_log('EmailThreads: addSimpleTestMessage - Error: ' . $e->getMessage());
        }
    }
    
    private function addThreadingContent(EmailSendEvent $event): void
    {
        try {
            $content = $event->getContent();
            if (!$content) {
                error_log('EmailThreads: addThreadingContent - No content to modify');
                return;
            }
            
            $email = $event->getEmail();
            $leadData = $event->getLead();
            
            if (!$email || !$leadData || !is_array($leadData) || !isset($leadData['id'])) {
                error_log('EmailThreads: addThreadingContent - Missing required data');
                return;
            }
            
            $leadId = $leadData['id'];
            $leadName = trim(($leadData['firstname'] ?? '') . ' ' . ($leadData['lastname'] ?? ''));
            if (empty($leadName)) {
                $leadName = $leadData['email'] ?? 'Unknown';
            }
            
            // Try to find previous messages for this lead
            $previousMessages = $this->getPreviousMessages($leadId, $email->getSubject());
            
            if (empty($previousMessages)) {
                error_log('EmailThreads: addThreadingContent - No previous messages found for lead: ' . $leadId);
                return;
            }
            
            // Build threading content from real previous messages
            $threadingContent = $this->buildThreadingContent($previousMessages, $leadName);
            
            // Add threading content
            $currentContent = $event->getContent();
            $newContent = $currentContent . $threadingContent;
            $event->setContent($newContent);
            
            error_log('EmailThreads: addThreadingContent - Added real threading content for lead: ' . $leadId . ' with ' . count($previousMessages) . ' previous messages');
        } catch (\Exception $e) {
            error_log('EmailThreads: addThreadingContent - Error: ' . $e->getMessage());
        }
    }
    
    private function getPreviousMessages(int $leadId, string $subject): array
    {
        try {
            $connection = $this->entityManager->getConnection();
            
            // Clean subject for matching
            $cleanSubject = $this->cleanSubject($subject);
            
            // Find previous messages for this lead with similar subject
            $sql = "
                SELECT etm.*, et.thread_id, et.from_email, et.from_name
                FROM mt_EmailThreadMessage etm
                JOIN mt_EmailThread et ON etm.thread_id = et.id
                WHERE et.lead_id = ? 
                AND et.is_active = 1
                AND etm.date_sent < NOW()
                ORDER BY etm.date_sent DESC
                LIMIT 3
            ";
            
            $stmt = $connection->prepare($sql);
            $stmt->execute([$leadId]);
            $messages = $stmt->fetchAllAssociative();
            
            error_log('EmailThreads: getPreviousMessages - Found ' . count($messages) . ' previous messages for lead: ' . $leadId);
            
            return $messages;
        } catch (\Exception $e) {
            error_log('EmailThreads: getPreviousMessages - Error: ' . $e->getMessage());
            return [];
        }
    }
    
    private function buildThreadingContent(array $messages, string $leadName): string
    {
        if (empty($messages)) {
            return '';
        }
        
        $content = '
<div style="margin: 20px 0; padding: 15px; background-color: #f5f5f5; border-left: 4px solid #666; font-family: Arial, sans-serif;">
    <h4 style="color: #333; margin: 0 0 10px 0;">📧 Previous Messages in Thread</h4>';
        
        foreach ($messages as $message) {
            $fromEmail = $message['from_email'] ?? 'Unknown';
            $fromName = $message['from_name'] ?? '';
            $subject = $message['subject'] ?? 'No Subject';
            $dateSent = $message['date_sent'] ? date('Y-m-d H:i:s', strtotime($message['date_sent'])) : 'Unknown Date';
            $messageContent = $message['content'] ?? '';
            
            // Truncate content if too long
            if (strlen($messageContent) > 500) {
                $messageContent = substr($messageContent, 0, 500) . '...';
            }
            
            $content .= '
    <div style="border-left: 2px solid #ccc; padding-left: 15px; margin: 10px 0;">
        <p style="margin: 5px 0; color: #666;"><strong>From:</strong> ' . htmlspecialchars($fromName ? $fromName . ' <' . $fromEmail . '>' : $fromEmail) . '</p>
        <p style="margin: 5px 0; color: #666;"><strong>To:</strong> ' . htmlspecialchars($leadName) . '</p>
        <p style="margin: 5px 0; color: #666;"><strong>Subject:</strong> ' . htmlspecialchars($subject) . '</p>
        <p style="margin: 5px 0; color: #666;"><strong>Date:</strong> ' . htmlspecialchars($dateSent) . '</p>
        <div style="margin-top: 15px; padding: 10px; background: white; border-radius: 3px;">
            ' . htmlspecialchars($messageContent) . '
        </div>
    </div>';
        }
        
        $content .= '
</div>';
        
        return $content;
    }
    
    private function cleanSubject(string $subject): string
    {
        // Remove common reply prefixes
        $subject = preg_replace('/^(Re:|RE:|Fwd:|FWD:)\s*/i', '', $subject);
        return trim($subject);
    }
}
