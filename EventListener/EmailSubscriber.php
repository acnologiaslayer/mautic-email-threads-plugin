<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEmailThreadsBundle\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Event\EmailSendEvent;
use MauticPlugin\MauticEmailThreadsBundle\Entity\EmailThreadMessage;
use MauticPlugin\MauticEmailThreadsBundle\Model\EmailThreadModel;
use MauticPlugin\MauticEmailThreadsBundle\Model\EmailThreadMessageModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class EmailSubscriber implements EventSubscriberInterface
{
    private $threadModel;
    private $messageModel;
    private $coreParametersHelper;
    private $router;
    private $entityManager;

    public function __construct(
        $threadModel = null,
        $messageModel = null,
        $coreParametersHelper = null,
        $router = null,
        EntityManagerInterface $entityManager = null
    ) {
        $this->threadModel = $threadModel;
        $this->messageModel = $messageModel;
        $this->coreParametersHelper = $coreParametersHelper;
        $this->router = $router;
        $this->entityManager = $entityManager;
        
        // Log constructor call
        error_log('EmailThreads: EmailSubscriber constructor called');
        
        // Log available EmailEvents constants for debugging
        $this->logAvailableEmailEvents();
    }
    
    /**
     * Log available EmailEvents constants for debugging
     */
    private function logAvailableEmailEvents(): void
    {
        try {
            $reflection = new \ReflectionClass(EmailEvents::class);
            $constants = $reflection->getConstants();
            error_log('EmailThreads: Available EmailEvents constants: ' . implode(', ', array_keys($constants)));
        } catch (\Exception $e) {
            error_log('EmailThreads: Could not get EmailEvents constants: ' . $e->getMessage());
        }
    }

    public static function getSubscribedEvents(): array
    {
        $events = [];
        
        // Try to get available EmailEvents constants
        try {
            $reflection = new \ReflectionClass(EmailEvents::class);
            $constants = $reflection->getConstants();
            
            // Use EMAIL_SEND if available (Mautic 5+)
            if (isset($constants['EMAIL_SEND'])) {
                $events[EmailEvents::EMAIL_SEND] = ['onEmailSend', 100];
                error_log('EmailThreads: Using EMAIL_SEND event');
            }
            
            // Use EMAIL_ON_SEND if available (Mautic 4+)
            if (isset($constants['EMAIL_ON_SEND'])) {
                $events[EmailEvents::EMAIL_ON_SEND] = ['onEmailSend', 50];
                error_log('EmailThreads: Using EMAIL_ON_SEND event');
            }
            
            // Try EMAIL_PRE_SEND if available (earlier in the process)
            if (isset($constants['EMAIL_PRE_SEND'])) {
                $events[EmailEvents::EMAIL_PRE_SEND] = ['onEmailPreSend', 200];
                error_log('EmailThreads: Using EMAIL_PRE_SEND event');
            }
            
            // Use EMAIL_ON_DISPLAY if available
            if (isset($constants['EMAIL_ON_DISPLAY'])) {
                $events[EmailEvents::EMAIL_ON_DISPLAY] = ['onEmailDisplay', 0];
            }
            
        } catch (\Exception $e) {
            error_log('EmailThreads: Could not get EmailEvents constants, using fallback events');
        }
        
        // Fallback to string-based event names for compatibility
        $events['mautic.email_send'] = ['onEmailSend', 100];
        $events['mautic.email_on_send'] = ['onEmailSend', 50];
        $events['mautic.email_pre_send'] = ['onEmailPreSend', 200];
        $events['mautic.campaign_on_trigger'] = ['onCampaignTrigger', 0];
        $events['mautic.campaign.on_event_trigger'] = ['onCampaignEventTrigger', 0];
        
        return $events;
    }

    public function onEmailSend(EmailSendEvent $event): void
    {
        try {
            error_log('EmailThreads: EmailSubscriber::onEmailSend called');
            
            // Always add a simple test message to verify the plugin is working
            $this->addSimpleTestMessage($event);
            
            // Check database tables exist before processing
            $this->verifyDatabaseTables();
            
            // Add debugging information about the email and lead
            $this->debugEmailAndLead($event);
            
            // Check if content was already modified (contains our thread content)
            $content = $event->getContent();
            if ($content && strpos($content, 'email-thread-container') !== false) {
                error_log('EmailThreads: onEmailSend - Thread content already present, skipping');
                return;
            }
            
            // Process email threading
            $this->processEmailThreading($event);
            
        } catch (\Exception $e) {
            error_log('EmailThreads: onEmailSend - Error: ' . $e->getMessage());
            error_log('EmailThreads: onEmailSend - Stack trace: ' . $e->getTraceAsString());
            // Don't re-throw the exception to prevent 500 errors
        }
    }
    
    /**
     * Add a simple test message to verify the plugin is working
     */
    private function addSimpleTestMessage(EmailSendEvent $event): void
    {
        try {
            $content = $event->getContent();
            if (!$content) {
                error_log('EmailThreads: addSimpleTestMessage - No content to modify');
                return;
            }
            
            $testMessage = '<div style="margin: 20px 0; padding: 15px; background: #e3f2fd; border-left: 4px solid #2196f3; font-family: Arial, sans-serif;">
                <strong style="color: #1976d2;">🔧 Email Threads Plugin Test</strong><br>
                <span style="color: #424242; font-size: 14px;">This message confirms the Email Threads plugin is working. If you see this, the plugin is active and processing emails.</span>
            </div>';
            
            $newContent = $content . $testMessage;
            $event->setContent($newContent);
            
            error_log('EmailThreads: addSimpleTestMessage - Added simple test message to email');
        } catch (\Exception $e) {
            error_log('EmailThreads: addSimpleTestMessage - Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Force content modification as a last resort
     */
    private function forceContentModification(EmailSendEvent $event): void
    {
        try {
            $content = $event->getContent();
            if (!$content) {
                error_log('EmailThreads: forceContentModification - No content to modify');
                return;
            }
            
            // Check if we already have thread content
            if (strpos($content, 'email-thread-container') !== false) {
                error_log('EmailThreads: forceContentModification - Thread content already present');
                return;
            }
            
            // Try to get thread information from the event
            $email = $event->getEmail();
            $leadData = $event->getLead();
            
            if (!$email || !$leadData) {
                error_log('EmailThreads: forceContentModification - Missing email or lead data');
                return;
            }
            
            // Get lead information
            $leadId = null;
            if (is_array($leadData)) {
                $leadId = $leadData['id'] ?? null;
            } else {
                $leadId = $leadData->getId();
            }
            
            if (!$leadId) {
                error_log('EmailThreads: forceContentModification - No lead ID');
                return;
            }
            
            // Try to find existing thread
            $thread = $this->threadModel->findOrCreateThread($leadData, $email, $event);
            if (!$thread) {
                error_log('EmailThreads: forceContentModification - Could not find or create thread');
                return;
            }
            
            // Get existing messages
            $existingMessages = $this->messageModel->getMessagesByThread($thread);
            if (empty($existingMessages)) {
                error_log('EmailThreads: forceContentModification - No existing messages');
                return;
            }
            
            // Generate thread content
            $threadContent = $this->generateThreadContentWithPrevious($existingMessages, $email, $event);
            if (empty($threadContent)) {
                error_log('EmailThreads: forceContentModification - No thread content generated');
                return;
            }
            
            // Force inject the content
            $this->injectThreadContent($event, $threadContent, $thread);
            error_log('EmailThreads: forceContentModification - Forced content injection completed');
            
        } catch (\Exception $e) {
            error_log('EmailThreads: forceContentModification - Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Verify that database tables exist and are accessible
     */
    private function verifyDatabaseTables(): void
    {
        try {
            if (!$this->entityManager) {
                error_log('EmailThreads: ERROR - EntityManager not available, skipping database verification');
                return;
            }
            
            $connection = $this->entityManager->getConnection();
            
            // Check if mt_EmailThread table exists
            $threadsTableExists = $connection->executeQuery("SHOW TABLES LIKE 'mt_EmailThread'")->rowCount() > 0;
            error_log('EmailThreads: Database check - mt_EmailThread table exists: ' . ($threadsTableExists ? 'YES' : 'NO'));
            
            // Check if mt_EmailThreadMessage table exists
            $messagesTableExists = $connection->executeQuery("SHOW TABLES LIKE 'mt_EmailThreadMessage'")->rowCount() > 0;
            error_log('EmailThreads: Database check - mt_EmailThreadMessage table exists: ' . ($messagesTableExists ? 'YES' : 'NO'));
            
            if (!$threadsTableExists || !$messagesTableExists) {
                error_log('EmailThreads: ERROR - Required database tables are missing! Plugin may not work correctly.');
                error_log('EmailThreads: Please install/upgrade the plugin through Mautic admin to create the tables.');
            } else {
                // Check table structure
                $threadsColumns = $connection->executeQuery("DESCRIBE mt_EmailThread")->fetchAllAssociative();
                $messagesColumns = $connection->executeQuery("DESCRIBE mt_EmailThreadMessage")->fetchAllAssociative();
                
                error_log('EmailThreads: mt_EmailThread table has ' . count($threadsColumns) . ' columns');
                error_log('EmailThreads: mt_EmailThreadMessage table has ' . count($messagesColumns) . ' columns');
                
                // Check if we can query the tables
                $threadCount = $connection->executeQuery("SELECT COUNT(*) FROM mt_EmailThread")->fetchOne();
                $messageCount = $connection->executeQuery("SELECT COUNT(*) FROM mt_EmailThreadMessage")->fetchOne();
                
                error_log('EmailThreads: Current database state - Threads: ' . $threadCount . ', Messages: ' . $messageCount);
            }
            
        } catch (\Exception $e) {
            error_log('EmailThreads: ERROR - Database verification failed: ' . $e->getMessage());
        }
    }

    /**
     * Main method for processing email threading
     */
    private function processEmailThreading(EmailSendEvent $event): void
    {
        // Check if content was already modified (contains our thread content)
        $content = $event->getContent();
        if ($content && strpos($content, 'email-thread-container') !== false) {
            error_log('EmailThreads: processEmailThreading - Thread content already present, skipping');
            return;
        }
        
        // Enhanced logging to debug segment emails
        $email = $event->getEmail();
        $leadData = $event->getLead();
        
        error_log('EmailThreads: processEmailThreading called');
        error_log('EmailThreads: Email type: ' . ($email ? $email->getEmailType() : 'null'));
        error_log('EmailThreads: Email subject: ' . ($email ? $email->getSubject() : 'null'));
        error_log('EmailThreads: Lead data type: ' . (is_array($leadData) ? 'array' : (is_object($leadData) ? get_class($leadData) : gettype($leadData))));
        
        // Check if services are available
        if (!$this->threadModel || !$this->messageModel) {
            error_log('EmailThreads: Missing required services, skipping');
            return;
        }
        
        // Check if plugin is enabled
        $isEnabled = $this->coreParametersHelper ? $this->coreParametersHelper->get('emailthreads_enabled', true) : true;
        error_log("EmailThreads: Plugin enabled check: " . ($isEnabled ? 'true' : 'false'));
        
        if (!$isEnabled) {
            error_log("EmailThreads: Plugin is disabled, skipping");
            return;
        }

        if (!$leadData || !$email) {
            error_log("EmailThreads: Missing lead data or email, skipping");
            return;
        }

        try {
            // Handle both array and entity lead data
            $leadEntity = null;
            $leadId = null;
            $leadEmail = null;
            
            if (is_array($leadData)) {
                $leadId = $leadData['id'] ?? null;
                $leadEmail = $leadData['email'] ?? null;
            } else {
                $leadEntity = $leadData;
                $leadId = $leadEntity->getId();
                $leadEmail = $leadEntity->getEmail();
            }
            
            if (!$leadId || !$leadEmail) {
                error_log('EmailThreads: Missing leadId or leadEmail, leadId: ' . ($leadId ?? 'null') . ', leadEmail: ' . ($leadEmail ?? 'null'));
                return;
            }
            
            error_log('EmailThreads: About to create/find thread for leadId: ' . $leadId . ', leadEmail: ' . $leadEmail);
            
            // Create or update thread - pass lead data that we have
            $thread = $this->threadModel->findOrCreateThread($leadData, $email, $event);
            
            if (!$thread) {
                error_log('EmailThreads: ERROR: Failed to create/find thread');
                return;
            }
            
            // Debug logging
            error_log("EmailThreads: Thread ID: " . $thread->getThreadId() . ", Lead ID: " . $leadId);
            error_log('EmailThreads: Created/found thread: ' . $thread->getThreadId() . ' for lead: ' . $leadId);
            
            // Get existing messages BEFORE adding the current message
            $existingMessages = $this->messageModel->getMessagesByThread($thread);
            error_log('EmailThreads: Existing messages in thread: ' . count($existingMessages));
            
            // Debug: Log details about existing messages
            if (!empty($existingMessages)) {
                foreach ($existingMessages as $index => $msg) {
                    error_log('EmailThreads: Existing message ' . $index . ': Subject="' . $msg->getSubject() . '", Content length=' . strlen($msg->getContent()));
                }
            } else {
                error_log('EmailThreads: No existing messages found in thread ' . $thread->getThreadId());
            }
            
            // Now add the current message to the thread
            $currentMessage = $this->messageModel->addMessageToThread($thread, $email, null, $event);
            if (!$currentMessage) {
                error_log('EmailThreads: ERROR: Failed to add current message to thread');
                return;
            }
            error_log('EmailThreads: Added current message to thread');
            
                    // Check if we should inject previous messages into the email
        $injectPreviousMessages = $this->coreParametersHelper ? 
            $this->coreParametersHelper->get('emailthreads_inject_previous_messages', true) : true;
        
        if ($injectPreviousMessages && !empty($existingMessages)) {
            // Generate thread content with existing messages (previous messages only)
            $threadContent = $this->generateThreadContentWithPrevious($existingMessages, $email, $event);
            
            // Debug logging
            error_log("EmailThreads: Generated thread content length: " . strlen($threadContent));
            
            // Update email content with threaded conversation
            if (!empty($threadContent)) {
                $this->injectThreadContent($event, $threadContent, $thread);
                error_log("EmailThreads: Injected thread content into email");
            } else {
                error_log("EmailThreads: No thread content to inject (first message)");
            }
        } else {
            error_log("EmailThreads: Skipping thread content injection - injectPreviousMessages: " . ($injectPreviousMessages ? 'true' : 'false') . ", existingMessages: " . count($existingMessages));
        }
            
        } catch (\Exception $e) {
            // Log error but don't break email sending
            error_log('EmailThreads error: ' . $e->getMessage() . ' in ' . $e->getFile() . ' on line ' . $e->getLine());
            error_log('EmailThreads stack trace: ' . $e->getTraceAsString());
        }
    }
    
    /**
     * Debug email and lead information
     */
    private function debugEmailAndLead(EmailSendEvent $event): void
    {
        try {
            $email = $event->getEmail();
            $leadData = $event->getLead();
            
            error_log('EmailThreads: === EMAIL AND LEAD DEBUG ===');
            
            if ($email) {
                error_log('EmailThreads: Email ID: ' . $email->getId());
                error_log('EmailThreads: Email Subject: ' . $email->getSubject());
                error_log('EmailThreads: Email From: ' . $email->getFromAddress());
                error_log('EmailThreads: Email Type: ' . $email->getEmailType());
            } else {
                error_log('EmailThreads: ERROR - No email object in event');
            }
            
            if ($leadData) {
                if (is_array($leadData)) {
                    error_log('EmailThreads: Lead Data (Array): ID=' . ($leadData['id'] ?? 'null') . ', Email=' . ($leadData['email'] ?? 'null'));
                } else {
                    error_log('EmailThreads: Lead Data (Object): ID=' . $leadData->getId() . ', Email=' . $leadData->getEmail());
                }
            } else {
                error_log('EmailThreads: ERROR - No lead data in event');
            }
            
            error_log('EmailThreads: === END EMAIL AND LEAD DEBUG ===');
            
        } catch (\Exception $e) {
            error_log('EmailThreads: debugEmailAndLead - Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Add a simple test message to verify the plugin is working
     */
    private function addTestMessage(EmailSendEvent $event): void
    {
        try {
            $content = $event->getContent();
            if (!$content) {
                return;
            }
            
            $testMessage = '<div style="margin-top: 20px; padding: 10px; background: #f0f8ff; border-left: 3px solid #007bff; font-size: 12px; color: #666;">
                <strong>Email Threads Plugin Test:</strong> This message confirms the plugin is working. Thread content will appear here when previous messages exist.
            </div>';
            
            $newContent = $content . $testMessage;
            $event->setContent($newContent);
            
            error_log('EmailThreads: addTestMessage - Added test message to email');
        } catch (\Exception $e) {
            error_log('EmailThreads: addTestMessage - Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Create a test thread to verify database functionality
     */
    private function createTestThread(EmailSendEvent $event): void
    {
        try {
            if (!$this->entityManager) {
                error_log('EmailThreads: createTestThread - EntityManager not available, skipping test thread creation');
                return;
            }
            
            $email = $event->getEmail();
            $leadData = $event->getLead();
            
            if (!$email || !$leadData) {
                error_log('EmailThreads: createTestThread - Missing email or lead data');
                return;
            }
            
            // Create a test thread with a unique subject
            $testSubject = 'TEST_THREAD_' . date('Y-m-d_H-i-s') . '_' . uniqid();
            error_log('EmailThreads: createTestThread - Creating test thread with subject: ' . $testSubject);
            
            // Create a test thread
            $testThread = new \MauticPlugin\MauticEmailThreadsBundle\Entity\EmailThread();
            
            // Set lead
            if (is_array($leadData)) {
                $leadId = $leadData['id'] ?? null;
                if ($leadId) {
                    $leadRepository = $this->entityManager->getRepository(\Mautic\LeadBundle\Entity\Lead::class);
                    $leadEntity = $leadRepository->find($leadId);
                    if ($leadEntity) {
                        $testThread->setLead($leadEntity);
                    }
                }
            } else {
                $testThread->setLead($leadData);
            }
            
            $testThread->setSubject($testSubject);
            $testThread->setFromEmail($email->getFromAddress() ?: 'test@example.com');
            $testThread->setFromName($email->getFromName() ?: 'Test Sender');
            $testThread->setFirstMessageDate(new \DateTime());
            $testThread->setLastMessageDate(new \DateTime());
            
            $this->entityManager->persist($testThread);
            $this->entityManager->flush();
            
            error_log('EmailThreads: createTestThread - Test thread created with ID: ' . $testThread->getId());
            
            // Create a test message
            $testMessage = new \MauticPlugin\MauticEmailThreadsBundle\Entity\EmailThreadMessage();
            $testMessage->setThread($testThread);
            $testMessage->setEmail($email);
            $testMessage->setSubject($testSubject);
            $testMessage->setContent('This is a test message to verify database functionality.');
            $testMessage->setFromEmail($email->getFromAddress() ?: 'test@example.com');
            $testMessage->setFromName($email->getFromName() ?: 'Test Sender');
            $testMessage->setDateSent(new \DateTime());
            $testMessage->setEmailType('test');
            
            $this->entityManager->persist($testMessage);
            $this->entityManager->flush();
            
            error_log('EmailThreads: createTestThread - Test message created with ID: ' . $testMessage->getId());
            
            // Verify the test data was saved
            $savedThread = $this->entityManager->getRepository(\MauticPlugin\MauticEmailThreadsBundle\Entity\EmailThread::class)->find($testThread->getId());
            $savedMessage = $this->entityManager->getRepository(\MauticPlugin\MauticEmailThreadsBundle\Entity\EmailThreadMessage::class)->find($testMessage->getId());
            
            if ($savedThread && $savedMessage) {
                error_log('EmailThreads: createTestThread - SUCCESS: Test thread and message verified in database');
            } else {
                error_log('EmailThreads: createTestThread - ERROR: Test thread or message not found in database');
            }
            
        } catch (\Exception $e) {
            error_log('EmailThreads: createTestThread - Error: ' . $e->getMessage());
        }
    }
    
    /**
     * Force create a thread and message for testing purposes
     */
    private function forceCreateThreadForTesting(EmailSendEvent $event): void
    {
        try {
            if (!$this->entityManager) {
                error_log('EmailThreads: forceCreateThreadForTesting - EntityManager not available');
                return;
            }
            
            $email = $event->getEmail();
            $leadData = $event->getLead();
            
            if (!$email || !$leadData) {
                error_log('EmailThreads: forceCreateThreadForTesting - Missing email or lead data');
                return;
            }
            
            // Get lead ID
            $leadId = is_array($leadData) ? ($leadData['id'] ?? null) : $leadData->getId();
            if (!$leadId) {
                error_log('EmailThreads: forceCreateThreadForTesting - No lead ID');
                return;
            }
            
            // Create a thread with a simple subject for testing
            $testSubject = 'Test Thread for ' . $email->getSubject();
            error_log('EmailThreads: forceCreateThreadForTesting - Creating thread with subject: ' . $testSubject);
            
            // Check if a thread already exists for this lead and subject using raw SQL
            $connection = $this->entityManager->getConnection();
            $sql = 'SELECT * FROM email_threads WHERE lead_id = ? AND subject = ? LIMIT 1';
            $result = $connection->executeQuery($sql, [$leadId, $testSubject]);
            $row = $result->fetchAssociative();
            
            $existingThread = null;
            if ($row) {
                $existingThread = $this->entityManager->find(\MauticPlugin\MauticEmailThreadsBundle\Entity\EmailThread::class, $row['id']);
            }
            
            if ($existingThread) {
                error_log('EmailThreads: forceCreateThreadForTesting - Using existing thread: ' . $existingThread->getId());
                $thread = $existingThread;
            } else {
                // Create new thread
                $thread = new \MauticPlugin\MauticEmailThreadsBundle\Entity\EmailThread();
                
                // Set lead
                if (is_array($leadData)) {
                    $leadRepository = $this->entityManager->getRepository(\Mautic\LeadBundle\Entity\Lead::class);
                    $leadEntity = $leadRepository->find($leadId);
                    if ($leadEntity) {
                        $thread->setLead($leadEntity);
                    }
                } else {
                    $thread->setLead($leadData);
                }
                
                $thread->setSubject($testSubject);
                $thread->setFromEmail($email->getFromAddress() ?: 'test@example.com');
                $thread->setFromName($email->getFromName() ?: 'Test Sender');
                $thread->setFirstMessageDate(new \DateTime());
                $thread->setLastMessageDate(new \DateTime());
                
                $this->entityManager->persist($thread);
                $this->entityManager->flush();
                
                error_log('EmailThreads: forceCreateThreadForTesting - Created new thread: ' . $thread->getId());
            }
            
            // Create a message for this thread
            $message = new \MauticPlugin\MauticEmailThreadsBundle\Entity\EmailThreadMessage();
            $message->setThread($thread);
            $message->setEmail($email);
            $message->setSubject($email->getSubject());
            $message->setContent($event->getContent() ?: 'Test message content');
            $message->setFromEmail($email->getFromAddress() ?: 'test@example.com');
            $message->setFromName($email->getFromName() ?: 'Test Sender');
            $message->setDateSent(new \DateTime());
            $message->setEmailType('test');
            
            $this->entityManager->persist($message);
            $this->entityManager->flush();
            
            error_log('EmailThreads: forceCreateThreadForTesting - Created message: ' . $message->getId());
            
            // Now try to get existing messages and inject them
            $existingMessages = $this->messageModel->getMessagesByThread($thread);
            error_log('EmailThreads: forceCreateThreadForTesting - Found ' . count($existingMessages) . ' existing messages');
            
            if (count($existingMessages) > 1) { // More than just the current message
                $threadContent = $this->generateThreadContentWithPrevious($existingMessages, $email, $event);
                if (!empty($threadContent)) {
                    $this->injectThreadContent($event, $threadContent, $thread);
                    error_log('EmailThreads: forceCreateThreadForTesting - Injected thread content');
                } else {
                    error_log('EmailThreads: forceCreateThreadForTesting - No thread content generated');
                }
            } else {
                error_log('EmailThreads: forceCreateThreadForTesting - Only one message in thread, no previous messages to show');
            }
            
        } catch (\Exception $e) {
            error_log('EmailThreads: forceCreateThreadForTesting - Error: ' . $e->getMessage());
        }
    }

    /**
     * Generate Gmail/Outlook style threaded content showing only previous messages
     */
    private function generateThreadContentWithPrevious(array $previousMessages, $currentEmail, $event): string
    {
        error_log('EmailThreads: generateThreadContentWithPrevious called with ' . count($previousMessages) . ' messages');
        
        if (empty($previousMessages)) {
            error_log('EmailThreads: No previous messages to show');
            return ''; // No previous messages to show
        }
        
        error_log('EmailThreads: Generating thread content with ' . count($previousMessages) . ' previous messages');
        
        // Debug: Log each message being processed
        foreach ($previousMessages as $index => $message) {
            $subject = is_object($message) ? $message->getSubject() : $message->subject;
            $contentLength = is_object($message) ? strlen($message->getContent()) : strlen($message->content);
            error_log('EmailThreads: Processing message ' . $index . ': Subject="' . $subject . '", Content length=' . $contentLength);
        }
        
        // Create a more Gmail/Outlook style thread separator
        $threadHtml = '<div class="email-thread-history">';
        
        // Show previous messages in reverse order (most recent first)
        $reversedMessages = array_reverse($previousMessages);
        $maxMessages = 3; // Limit to last 3 messages to avoid clutter
        
        foreach (array_slice($reversedMessages, 0, $maxMessages) as $index => $message) {
            // Handle both entity objects and stdClass objects
            $messageDate = ($message instanceof EmailThreadMessage) 
                ? $message->getDateSent()->format('M j, Y \a\t g:i A')
                : $message->dateSent->format('M j, Y \a\t g:i A');
                
            $fromName = ($message instanceof EmailThreadMessage)
                ? ($message->getFromName() ?: $message->getFromEmail())
                : ($message->fromName ?: $message->fromEmail);
                
            $content = ($message instanceof EmailThreadMessage)
                ? $message->getContent()
                : $message->content;
            
            // Get and clean the content
            $quotedContent = $this->quoteMessageContent($content);
            
            // Gmail/Outlook style quote block
            $threadHtml .= sprintf(
                '<div class="previous-message" style="margin: 20px 0; border-left: 2px solid #dadce0; padding-left: 20px; position: relative;">
                    <div class="message-header" style="font-size: 13px; color: #5f6368; margin-bottom: 12px; font-weight: 500;">
                        <span style="color: #1a73e8;">%s</span> wrote:
                    </div>
                    <div class="quoted-content" style="font-size: 14px; color: #3c4043; line-height: 1.5; background: #f8f9fa; padding: 12px; border-radius: 4px; border: 1px solid #e8eaed;">
                        %s
                    </div>
                    <div style="font-size: 11px; color: #5f6368; margin-top: 8px;">
                        %s
                    </div>
                </div>',
                htmlspecialchars($fromName),
                $quotedContent,
                $messageDate
            );
        }
        
        if (count($previousMessages) > $maxMessages) {
            $threadHtml .= '<div style="font-size: 12px; color: #5f6368; text-align: center; margin: 15px 0; font-style: italic;">... and ' . (count($previousMessages) - $maxMessages) . ' earlier messages</div>';
        }
        
        $threadHtml .= '</div>';
        
        return $threadHtml;
    }

    /**
     * Generate Gmail/Outlook style threaded content with quoted previous messages
     */
    private function generateThreadContent($thread, $currentMessage): string
    {
        $messages = $this->messageModel->getMessagesByThread($thread);
        $threadHtml = '';
        
        foreach ($messages as $index => $message) {
            $isLast = ($index === count($messages) - 1);
            $messageDate = $message->getDateSent()->format('M j, Y \a\t g:i A');
            
            if ($isLast) {
                // Current message - show full content
                $threadHtml .= sprintf(
                    '<div class="email-message current-message" style="margin-bottom: 20px;">
                        <div class="message-content">%s</div>
                    </div>',
                    $message->getContent()
                );
            } else {
                // Previous messages - show as quoted/collapsed
                $quotedContent = $this->quoteMessageContent($message->getContent());
                $threadHtml .= sprintf(
                    '<div class="email-message quoted-message" style="margin: 20px 0; border-left: 3px solid #ccc; padding-left: 15px; color: #666;">
                        <div class="message-header" style="font-size: 12px; color: #888; margin-bottom: 10px;">
                            <strong>On %s, %s wrote:</strong>
                        </div>
                        <div class="quoted-content" style="font-size: 13px;">%s</div>
                    </div>',
                    $messageDate,
                    $message->getFromName() ?: $message->getFromEmail(),
                    $quotedContent
                );
            }
        }
        
        return $threadHtml;
    }

    /**
     * Quote message content like Gmail/Outlook
     */
    private function quoteMessageContent(string $content): string
    {
        // Convert HTML to text with better formatting
        $textContent = html_entity_decode(strip_tags($content));
        
        // Remove extra whitespace and normalize line breaks
        $textContent = preg_replace('/\s+/', ' ', $textContent);
        $textContent = trim($textContent);
        
        // Remove common email signatures and footers
        $textContent = preg_replace('/\n\s*--\s*\n.*$/s', '', $textContent);
        $textContent = preg_replace('/\n\s*Best regards.*$/s', '', $textContent);
        $textContent = preg_replace('/\n\s*Sincerely.*$/s', '', $textContent);
        $textContent = preg_replace('/\n\s*Thanks.*$/s', '', $textContent);
        
        // Break into sentences for better readability
        $sentences = preg_split('/(?<=[.!?])\s+/', $textContent);
        
        // Limit to first 2-3 sentences to keep quotes concise
        $maxSentences = 3;
        if (count($sentences) > $maxSentences) {
            $sentences = array_slice($sentences, 0, $maxSentences);
            $textContent = implode(' ', $sentences) . '...';
        } else {
            $textContent = implode(' ', $sentences);
        }
        
        // Limit overall length
        if (strlen($textContent) > 250) {
            $textContent = substr($textContent, 0, 250) . '...';
        }
        
        // Return clean text without extra styling (styling is handled in the parent container)
        return htmlspecialchars($textContent);
    }

    /**
     * Inject threaded content into the email
     */
    private function injectThreadContent(EmailSendEvent $event, string $threadContent, $thread): void
    {
        $content = $event->getContent();
        if (!$content || empty($threadContent)) {
            error_log('EmailThreads: injectThreadContent - No content or thread content to inject');
            return; // No content or no thread history to add
        }

        error_log('EmailThreads: injectThreadContent - Original content length: ' . strlen($content));
        error_log('EmailThreads: injectThreadContent - Thread content length: ' . strlen($threadContent));

        // Generate thread URL
        $threadUrl = $this->generateThreadUrl($thread->getThreadId());
        error_log('EmailThreads: injectThreadContent - Thread URL: ' . $threadUrl);
        
        // Create thread footer with conversation history only if there's actual thread content
        $threadFooter = sprintf(
            '<div class="email-thread-container" style="margin-top: 30px; border-top: 1px solid #e1e5e9; padding-top: 20px;">
                <div class="thread-header" style="margin-bottom: 15px;">
                    <div style="font-size: 12px; color: #5f6368; margin-bottom: 15px; font-weight: 500;">--- Previous Messages ---</div>
                </div>
                %s
                <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #e1e5e9; text-align: center;">
                    <a href="%s" style="color: #1a73e8; text-decoration: none; font-size: 12px;">View full conversation online</a>
                </div>
            </div>',
            $threadContent,
            $threadUrl
        );

        error_log('EmailThreads: injectThreadContent - Thread footer length: ' . strlen($threadFooter));

        // Try multiple approaches to ensure content is modified
        $this->modifyEmailContent($event, $content, $threadFooter);
    }
    
    /**
     * Try multiple approaches to modify email content
     */
    private function modifyEmailContent(EmailSendEvent $event, string $originalContent, string $threadFooter): void
    {
        // Approach 1: Direct content modification
        $newContent = $originalContent . $threadFooter;
        $event->setContent($newContent);
        error_log('EmailThreads: modifyEmailContent - Approach 1: Direct content modification');
        error_log('EmailThreads: modifyEmailContent - New content length: ' . strlen($newContent));
        
        // Approach 2: Try to modify the email entity directly
        try {
            $email = $event->getEmail();
            if ($email) {
                $customHtml = $email->getCustomHtml();
                if ($customHtml) {
                    $email->setCustomHtml($customHtml . $threadFooter);
                    error_log('EmailThreads: modifyEmailContent - Approach 2: Modified email entity customHtml');
                }
                
                $plainText = $email->getPlainText();
                if ($plainText) {
                    $plainTextFooter = strip_tags($threadFooter);
                    $email->setPlainText($plainText . "\n\n" . $plainTextFooter);
                    error_log('EmailThreads: modifyEmailContent - Approach 2: Modified email entity plainText');
                }
            }
        } catch (\Exception $e) {
            error_log('EmailThreads: modifyEmailContent - Approach 2 failed: ' . $e->getMessage());
        }
        
        // Approach 3: Try to modify tokens
        try {
            $tokens = $event->getTokens();
            if ($tokens && isset($tokens['content'])) {
                $tokens['content'] = $tokens['content'] . $threadFooter;
                $event->setTokens($tokens);
                error_log('EmailThreads: modifyEmailContent - Approach 3: Modified tokens');
            }
        } catch (\Exception $e) {
            error_log('EmailThreads: modifyEmailContent - Approach 3 failed: ' . $e->getMessage());
        }
        
        // Verify the content was actually modified
        $finalContent = $event->getContent();
        if (strpos($finalContent, 'email-thread-container') !== false) {
            error_log('EmailThreads: modifyEmailContent - SUCCESS: Thread content found in final content');
        } else {
            error_log('EmailThreads: modifyEmailContent - WARNING: Thread content not found in final content');
            error_log('EmailThreads: modifyEmailContent - Final content length: ' . strlen($finalContent));
            error_log('EmailThreads: modifyEmailContent - Final content preview: ' . substr($finalContent, -200));
        }
        
        // Additional debugging: Check if the event content was actually set
        $eventContentAfter = $event->getContent();
        error_log('EmailThreads: modifyEmailContent - Event content after modification: ' . strlen($eventContentAfter) . ' bytes');
        if (strlen($eventContentAfter) > strlen($originalContent)) {
            error_log('EmailThreads: modifyEmailContent - Content was extended by ' . (strlen($eventContentAfter) - strlen($originalContent)) . ' bytes');
        } else {
            error_log('EmailThreads: modifyEmailContent - Content was NOT extended - this indicates a problem!');
        }
    }

    /**
     * Handle email display events (for tracking when emails are viewed)
     */
    public function onEmailPreSend($event): void
    {
        error_log('EmailThreads: EmailSubscriber::onEmailPreSend called');
        
        // Check if this is an EmailSendEvent
        if (!$event instanceof EmailSendEvent) {
            error_log('EmailThreads: onEmailPreSend - Event is not EmailSendEvent, skipping');
            return;
        }
        
        // Process email threading with highest priority
        $this->processEmailThreading($event);
    }

    public function onEmailDisplay($event): void
    {
        error_log('EmailThreads: EmailSubscriber::onEmailDisplay called');
        // This can be used to track email opens in threads
        // Implementation can be added later if needed
    }


    /**
     * Handle campaign trigger events (for segment emails)
     */
    public function onCampaignTrigger($event): void
    {
        error_log('EmailThreads: EmailSubscriber::onCampaignTrigger called');
        // Check if this is an email campaign and process accordingly
        if (method_exists($event, 'getEmail') && $event->getEmail()) {
            $this->onEmailSend($event);
        }
    }

    /**
     * Handle campaign event trigger (for segment emails)
     */
    public function onCampaignEventTrigger($event): void
    {
        error_log('EmailThreads: EmailSubscriber::onCampaignEventTrigger called');
        // Check if this is an email campaign and process accordingly
        if (method_exists($event, 'getEmail') && $event->getEmail()) {
            $this->onEmailSend($event);
        }
    }

    private function generateThreadUrl(string $threadId): string
    {
        if (!$this->router) {
            return '/email-thread/' . $threadId; // Fallback URL
        }
        
        try {
            return $this->router->generate(
                'mautic_emailthreads_public',
                ['threadId' => $threadId],
                UrlGeneratorInterface::ABSOLUTE_URL
            );
        } catch (\Exception $e) {
            return '/email-thread/' . $threadId; // Fallback URL
        }
    }
}
