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
            
            $emailType = $email ? $email->getEmailType() : 'unknown';
            $emailSubject = $email ? $email->getSubject() : 'no subject';
            $leadId = is_array($leadData) ? ($leadData['id'] ?? 'unknown') : (is_object($leadData) ? $leadData->getId() : 'unknown');
            
            error_log('EmailThreads: Processing email - Type: ' . $emailType . ', Subject: ' . $emailSubject . ', Lead ID: ' . $leadId);
            error_log('EmailThreads: Content length: ' . ($content ? strlen($content) : 'null'));
            
            // Process all email types: template, list, campaign, trigger, segment, etc.
            if (!$email || !$leadData) {
                error_log('EmailThreads: Skipping - missing email or lead data');
                return;
            }
            
            // Save current email to database for threading (non-blocking)
            try {
                $this->saveEmailToDatabase($event);
            } catch (\Exception $e) {
                error_log('EmailThreads: saveEmailToDatabase failed - ' . $e->getMessage());
                // Don't let database errors break email sending
            }
            
            // Add threading content (non-blocking)
            try {
                $this->addThreadingContent($event);
            } catch (\Exception $e) {
                error_log('EmailThreads: addThreadingContent failed - ' . $e->getMessage());
                // Don't let threading errors break email sending
            }
            
        } catch (\Exception $e) {
            error_log('EmailThreads: onEmailSend - Critical Error: ' . $e->getMessage());
            error_log('EmailThreads: onEmailSend - Stack trace: ' . $e->getTraceAsString());
            // Don't re-throw - let email sending continue
        }
    }
    
    
    private function addThreadingContent(EmailSendEvent $event): void
    {
        try {
            $content = $event->getContent();
            if (!$content || !is_string($content)) {
                error_log('EmailThreads: addThreadingContent - No valid content to modify');
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
            
            if (empty($threadingContent)) {
                error_log('EmailThreads: addThreadingContent - No threading content generated');
                return;
            }
            
            // Ensure we have valid content before setting and preserve unsubscribe links
            $currentContent = $event->getContent();
            if ($currentContent && is_string($currentContent)) {
                // Preserve unsubscribe links by inserting threading content before them
                $unsubscribePattern = '/(<a[^>]*unsubscribe[^>]*>.*?<\/a>)/i';
                if (preg_match($unsubscribePattern, $currentContent, $matches)) {
                    // Insert threading content before unsubscribe link
                    $newContent = str_replace($matches[0], $threadingContent . $matches[0], $currentContent);
                } else {
                    // No unsubscribe link found, append to end
                    $newContent = $currentContent . $threadingContent;
                }
                $event->setContent($newContent);
                error_log('EmailThreads: addThreadingContent - Added real threading content for lead: ' . $leadId . ' with ' . count($previousMessages) . ' previous messages');
            } else {
                error_log('EmailThreads: addThreadingContent - Current content is invalid, skipping modification');
            }
        } catch (\Exception $e) {
            error_log('EmailThreads: addThreadingContent - Error: ' . $e->getMessage());
        }
    }
    
    private function getPreviousMessages(int $leadId, string $subject): array
    {
        try {
            // Get database connection details from environment
            $dbHost = getenv('MAUTIC_DB_HOST') ?: 'db';
            $dbPort = 3306;
            $dbName = getenv('MAUTIC_DB_NAME') ?: 'mautic';
            $dbUser = getenv('MAUTIC_DB_USER') ?: 'mautic';
            $dbPassword = getenv('MAUTIC_DB_PASSWORD') ?: 'mauticpass';
            
            // Create PDO connection
            $dsn = "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4";
            $pdo = new \PDO($dsn, $dbUser, $dbPassword, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);
            
            error_log('EmailThreads: getPreviousMessages - Looking for messages for lead: ' . $leadId . ', subject: ' . $subject);
            
            // First, let's check if there are any threads for this lead at all
            $checkSql = "SELECT COUNT(*) as count FROM mt_EmailThread WHERE lead_id = ?";
            $stmt = $pdo->prepare($checkSql);
            $stmt->execute([$leadId]);
            $threadCount = $stmt->fetchColumn();
            error_log('EmailThreads: getPreviousMessages - Found ' . $threadCount . ' threads for lead: ' . $leadId);
            
            // Check if there are any messages at all
            $messageCheckSql = "SELECT COUNT(*) as count FROM mt_EmailThreadMessage etm JOIN mt_EmailThread et ON etm.thread_id = et.id WHERE et.lead_id = ?";
            $stmt = $pdo->prepare($messageCheckSql);
            $stmt->execute([$leadId]);
            $messageCount = $stmt->fetchColumn();
            error_log('EmailThreads: getPreviousMessages - Found ' . $messageCount . ' messages for lead: ' . $leadId);
            
            // Find previous messages for this lead with similar subject
            $sql = "
                SELECT etm.*, et.thread_id, et.from_email, et.from_name
                FROM mt_EmailThreadMessage etm
                JOIN mt_EmailThread et ON etm.thread_id = et.id
                WHERE et.lead_id = ? 
                AND et.is_active = 1
                AND etm.date_sent < NOW()
                ORDER BY etm.date_sent DESC
            ";
            
            error_log('EmailThreads: getPreviousMessages - Executing SQL: ' . $sql . ' with leadId: ' . $leadId);
            
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$leadId]);
            $messages = $stmt->fetchAll();
            
            error_log('EmailThreads: getPreviousMessages - Found ' . count($messages) . ' previous messages for lead: ' . $leadId);
            
            return $messages;
        } catch (\Exception $e) {
            error_log('EmailThreads: getPreviousMessages - Error: ' . $e->getMessage());
            return [];
        }
    }
    
    private function buildThreadingContent(array $messages, string $leadName): string
    {
        try {
            if (empty($messages)) {
                return '';
            }
            
            $messageCount = count($messages);
            
            $threadId = 'thread_' . uniqid();
            $content = '
<div style="margin: 20px 0; border-top: 1px solid #e1e5e9; padding-top: 20px; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif;">
    <div style="margin-bottom: 16px; border-bottom: 1px solid #e1e5e9; padding-bottom: 8px;">
        <div style="display: flex; align-items: center; cursor: pointer;" onclick="toggleThread(\'' . $threadId . '\')">
            <div style="width: 16px; height: 16px; margin-right: 8px; display: flex; align-items: center; justify-content: center; background: #f1f3f4; border-radius: 3px;">
                <span id="' . $threadId . '_arrow" style="color: #5f6368; font-size: 10px; font-weight: bold;">▼</span>
            </div>
            <span style="color: #5f6368; font-size: 12px; font-weight: 500; text-transform: uppercase; letter-spacing: 0.3px;">
                ' . $messageCount . ' Previous Message' . ($messageCount > 1 ? 's' : '') . '
            </span>
        </div>
    </div>
    <div id="' . $threadId . '_content" style="display: block;">';
            
            foreach ($messages as $index => $message) {
                if (!is_array($message)) {
                    continue;
                }
                
                $fromEmail = $message['from_email'] ?? 'Unknown';
                $fromName = $message['from_name'] ?? '';
                $subject = $message['subject'] ?? 'No Subject';
                $dateSent = $message['date_sent'] ? $this->formatDate($message['date_sent']) : 'Unknown Date';
                $messageContent = $message['content'] ?? '';
                
                // Ensure messageContent is a string
                if (!is_string($messageContent)) {
                    $messageContent = '';
                }
                
                // Clean and format the content
                $formattedContent = $this->formatMessageContent($messageContent);
                
                // Create sender display
                $senderDisplay = $fromName ? htmlspecialchars($fromName) . ' <span style="color: #5f6368;">' . htmlspecialchars($fromEmail) . '</span>' : htmlspecialchars($fromEmail);
                
                // Calculate nesting level (older messages get more indented)
                $nestingLevel = $index * 20;
                $borderColor = $this->getNestingBorderColor($index);
                
                $content .= '
        <div style="margin-left: ' . $nestingLevel . 'px; border-left: 3px solid ' . $borderColor . '; padding-left: 16px; margin-bottom: 20px; position: relative; transition: all 0.2s ease;">
            <div style="margin-bottom: 8px;">
                <div style="display: flex; align-items: center; margin-bottom: 4px;">
                    <span style="font-weight: 500; color: #202124; font-size: 14px;">' . $senderDisplay . '</span>
                    <span style="margin: 0 8px; color: #5f6368; font-size: 12px;">•</span>
                    <span style="color: #5f6368; font-size: 12px;">' . htmlspecialchars($dateSent) . '</span>
                </div>
                <div style="color: #202124; font-size: 14px; font-weight: 500; margin-bottom: 8px;">
                    ' . htmlspecialchars($subject) . '
                </div>
            </div>
            <div style="color: #3c4043; font-size: 14px; line-height: 1.5; background: #f8f9fa; border-radius: 8px; padding: 12px; border: 1px solid #e8eaed; position: relative;">
                ' . $formattedContent . '
                <div style="position: absolute; top: 8px; right: 8px; opacity: 0.6; font-size: 10px; color: #5f6368;">
                    #' . ($index + 1) . '
                </div>
            </div>
        </div>';
            }
            
            $content .= '
    </div>
</div>

<script>
function toggleThread(threadId) {
    var content = document.getElementById(threadId + "_content");
    var arrow = document.getElementById(threadId + "_arrow");
    
    if (content && arrow) {
        if (content.style.display === "none") {
            content.style.display = "block";
            arrow.innerHTML = "▼";
        } else {
            content.style.display = "none";
            arrow.innerHTML = "▶";
        }
    }
}
</script>';
            
            return $content;
        } catch (\Exception $e) {
            error_log('EmailThreads: buildThreadingContent - Error: ' . $e->getMessage());
            return '';
        }
    }
    
    private function getNestingBorderColor(int $level): string
    {
        $colors = [
            '#1a73e8', // Blue for first message
            '#34a853', // Green for second message
            '#ea4335', // Red for third message
            '#fbbc04', // Yellow for fourth message
            '#9aa0a6', // Gray for additional messages
        ];
        
        return $colors[$level % count($colors)];
    }
    
    private function formatDate(string $dateString): string
    {
        try {
            $date = new \DateTime($dateString);
            $now = new \DateTime();
            $diff = $now->diff($date);
            
            if ($diff->days == 0) {
                return $date->format('g:i A');
            } elseif ($diff->days == 1) {
                return 'Yesterday at ' . $date->format('g:i A');
            } elseif ($diff->days < 7) {
                return $date->format('l') . ' at ' . $date->format('g:i A');
            } else {
                return $date->format('M j, Y') . ' at ' . $date->format('g:i A');
            }
        } catch (\Exception $e) {
            return date('M j, Y g:i A', strtotime($dateString));
        }
    }
    
    private function formatMessageContent(string $content): string
    {
        try {
            // Remove signatures (common patterns)
            $content = preg_replace('/\n\s*--\s*\n.*$/s', '', $content); // Remove -- signature
            $content = preg_replace('/\n\s*Best regards?.*$/s', '', $content); // Remove "Best regards" signatures
            $content = preg_replace('/\n\s*Sincerely.*$/s', '', $content); // Remove "Sincerely" signatures
            $content = preg_replace('/\n\s*Thanks?.*$/s', '', $content); // Remove "Thanks" signatures
            $content = preg_replace('/\n\s*Regards.*$/s', '', $content); // Remove "Regards" signatures
            $content = preg_replace('/\n\s*Sent from.*$/s', '', $content); // Remove "Sent from" signatures
            $content = preg_replace('/\n\s*Get Outlook.*$/s', '', $content); // Remove Outlook signatures
            $content = preg_replace('/\n\s*Get Gmail.*$/s', '', $content); // Remove Gmail signatures
            
            // Remove all CSS attributes and style tags completely
            $content = preg_replace('/style\s*=\s*["\'][^"\']*["\']/', '', $content);
            $content = preg_replace('/<style[^>]*>.*?<\/style>/s', '', $content);
            $content = preg_replace('/class\s*=\s*["\'][^"\']*["\']/', '', $content);
            $content = preg_replace('/id\s*=\s*["\'][^"\']*["\']/', '', $content);
            
            // Remove HTML tags but preserve line breaks and basic formatting (no attributes)
            $content = strip_tags($content, '<p><br><strong><em><u><a><ul><ol><li><h1><h2><h3><h4><h5><h6><blockquote><pre>');
            
            // Clean up excessive whitespace and remove any remaining CSS-like text
            $content = preg_replace('/\s+/', ' ', $content);
            $content = preg_replace('/(<br\s*\/?>)+/', '<br>', $content);
            $content = preg_replace('/\([^)]*padding[^)]*\)/', '', $content); // Remove (padding = 0) type text
            $content = preg_replace('/\([^)]*margin[^)]*\)/', '', $content); // Remove (margin = 0) type text
            $content = preg_replace('/\([^)]*style[^)]*\)/', '', $content); // Remove (style = ...) type text
            
            // Truncate if too long (increased limit for better visibility)
            if (strlen(strip_tags($content)) > 2000) {
                $content = $this->truncateHtml($content, 2000);
                $content .= '<br><br><em>... (message truncated for display)</em>';
            }
            
            // Ensure proper line breaks
            $content = nl2br($content);
            
            return $content;
        } catch (\Exception $e) {
            error_log('EmailThreads: formatMessageContent - Error: ' . $e->getMessage());
            return htmlspecialchars(substr($content, 0, 500)) . (strlen($content) > 500 ? '...' : '');
        }
    }
    
    private function truncateHtml(string $html, int $limit): string
    {
        $text = strip_tags($html);
        if (strlen($text) <= $limit) {
            return $html;
        }
        
        $truncated = substr($text, 0, $limit);
        $lastSpace = strrpos($truncated, ' ');
        if ($lastSpace !== false) {
            $truncated = substr($truncated, 0, $lastSpace);
        }
        
        return $truncated;
    }
    
    private function cleanSubject(string $subject): string
    {
        // Remove common reply prefixes
        $subject = preg_replace('/^(Re:|RE:|Fwd:|FWD:)\s*/i', '', $subject);
        return trim($subject);
    }
    
    private function saveEmailToDatabase(EmailSendEvent $event): void
    {
        try {
            $email = $event->getEmail();
            $leadData = $event->getLead();
            $content = $event->getContent();
            
            if (!$email || !$leadData || !is_array($leadData) || !isset($leadData['id'])) {
                error_log('EmailThreads: saveEmailToDatabase - Missing required data');
                return;
            }
            
            $leadId = $leadData['id'];
            $subject = $email->getSubject();
            $fromEmail = $email->getFromAddress();
            $fromName = $email->getFromName();
            
            // Get database connection details from environment
            $dbHost = getenv('MAUTIC_DB_HOST') ?: 'db';
            $dbPort = 3306;
            $dbName = getenv('MAUTIC_DB_NAME') ?: 'mautic';
            $dbUser = getenv('MAUTIC_DB_USER') ?: 'mautic';
            $dbPassword = getenv('MAUTIC_DB_PASSWORD') ?: 'mauticpass';
            
            // Create PDO connection
            $dsn = "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4";
            $pdo = new \PDO($dsn, $dbUser, $dbPassword, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
            ]);
            
            // Generate thread ID
            $threadId = 'thread_' . $leadId . '_' . md5($subject . $leadId);
            
            // Check if thread exists
            $checkThreadSql = "SELECT id FROM mt_EmailThread WHERE thread_id = ? AND lead_id = ?";
            $stmt = $pdo->prepare($checkThreadSql);
            $stmt->execute([$threadId, $leadId]);
            $existingThread = $stmt->fetch();
            
            $threadDbId = null;
            if ($existingThread) {
                $threadDbId = $existingThread['id'];
                error_log('EmailThreads: saveEmailToDatabase - Using existing thread ID: ' . $threadDbId);
            } else {
                // Create new thread
                $createThreadSql = "
                    INSERT INTO mt_EmailThread (thread_id, lead_id, subject, from_email, from_name, first_message_date, last_message_date, is_active, date_added, date_modified)
                    VALUES (?, ?, ?, ?, ?, NOW(), NOW(), 1, NOW(), NOW())
                ";
                $stmt = $pdo->prepare($createThreadSql);
                $stmt->execute([$threadId, $leadId, $subject, $fromEmail, $fromName]);
                $threadDbId = $pdo->lastInsertId();
                error_log('EmailThreads: saveEmailToDatabase - Created new thread ID: ' . $threadDbId);
            }
            
            // Save message
            $saveMessageSql = "
                INSERT INTO mt_EmailThreadMessage (thread_id, subject, content, from_email, from_name, date_sent, email_type, date_added, date_modified)
                VALUES (?, ?, ?, ?, ?, NOW(), 'sent', NOW(), NOW())
            ";
            $stmt = $pdo->prepare($saveMessageSql);
            $stmt->execute([$threadDbId, $subject, $content, $fromEmail, $fromName]);
            $messageId = $pdo->lastInsertId();
            
            error_log('EmailThreads: saveEmailToDatabase - Saved message ID: ' . $messageId . ' for thread: ' . $threadDbId . ', lead: ' . $leadId);
            
        } catch (\Exception $e) {
            error_log('EmailThreads: saveEmailToDatabase - Error: ' . $e->getMessage());
        }
    }
}
