<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEmailThreadsBundle\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Event\EmailSendEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class EmailSubscriberSimple implements EventSubscriberInterface
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
        
        error_log('EmailThreads: EmailSubscriberSimple constructor called');
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
                error_log('EmailThreads: Subscribed to EMAIL_SEND event');
            } elseif (isset($constants['EMAIL_ON_SEND'])) {
                $events[$constants['EMAIL_ON_SEND']] = ['onEmailSend', 50];
                error_log('EmailThreads: Subscribed to EMAIL_ON_SEND event');
            }
        }
        
        // Fallback to string-based event names
        if (empty($events)) {
            $events['mautic.email.on_send'] = ['onEmailSend', 50];
            $events['mautic.email.send'] = ['onEmailSend', 100];
            error_log('EmailThreads: Using fallback event subscriptions');
        }
        
        return $events;
    }

    public function onEmailSend(EmailSendEvent $event): void
    {
        try {
            error_log('EmailThreads: EmailSubscriberSimple::onEmailSend called');
            
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
            
            // Check if we have the required services
            if (!$this->threadModel || !$this->messageModel) {
                error_log('EmailThreads: Missing required services - threadModel: ' . ($this->threadModel ? 'OK' : 'NULL') . ', messageModel: ' . ($this->messageModel ? 'OK' : 'NULL'));
                return;
            }
            
            // Check if plugin is enabled
            $isEnabled = $this->coreParametersHelper ? $this->coreParametersHelper->get('emailthreads_enabled', true) : true;
            error_log("EmailThreads: Plugin enabled: " . ($isEnabled ? 'true' : 'false'));
            
            if (!$isEnabled) {
                error_log("EmailThreads: Plugin is disabled, skipping");
                return;
            }
            
            // Basic threading logic
            if ($leadData && $email) {
                $this->processBasicThreading($event);
            }
            
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
                <span style="color: #424242; font-size: 14px;">This message confirms the Email Threads plugin is working. If you see this, the plugin is active and processing emails.</span>
            </div>';
            
            $newContent = $content . $testMessage;
            $event->setContent($newContent);
            
            error_log('EmailThreads: addSimpleTestMessage - Added simple test message to email');
        } catch (\Exception $e) {
            error_log('EmailThreads: addSimpleTestMessage - Error: ' . $e->getMessage());
        }
    }
    
    private function processBasicThreading(EmailSendEvent $event): void
    {
        try {
            $email = $event->getEmail();
            $leadData = $event->getLead();
            
            // Get lead information
            $leadId = null;
            if (is_array($leadData)) {
                $leadId = $leadData['id'] ?? null;
            } else {
                $leadId = $leadData->getId();
            }
            
            if (!$leadId) {
                error_log('EmailThreads: processBasicThreading - No lead ID');
                return;
            }
            
            error_log('EmailThreads: processBasicThreading - Processing for lead ID: ' . $leadId);
            
            // Try to create or find thread
            $thread = $this->threadModel->findOrCreateThread($leadData, $email, $event);
            
            if (!$thread) {
                error_log('EmailThreads: processBasicThreading - Failed to create/find thread');
                return;
            }
            
            error_log('EmailThreads: processBasicThreading - Thread ID: ' . $thread->getThreadId());
            
            // Get existing messages
            $existingMessages = $this->messageModel->getMessagesByThread($thread);
            error_log('EmailThreads: processBasicThreading - Existing messages: ' . count($existingMessages));
            
            // Add current message
            $currentMessage = $this->messageModel->addMessageToThread($thread, $email, null, $event);
            if (!$currentMessage) {
                error_log('EmailThreads: processBasicThreading - Failed to add current message');
                return;
            }
            
            error_log('EmailThreads: processBasicThreading - Added current message to thread');
            
            // If we have existing messages, try to inject them
            if (!empty($existingMessages)) {
                $this->injectPreviousMessages($event, $existingMessages);
            }
            
        } catch (\Exception $e) {
            error_log('EmailThreads: processBasicThreading - Error: ' . $e->getMessage());
        }
    }
    
    private function injectPreviousMessages(EmailSendEvent $event, array $existingMessages): void
    {
        try {
            error_log('EmailThreads: injectPreviousMessages - Injecting ' . count($existingMessages) . ' previous messages');
            
            $content = $event->getContent();
            if (!$content) {
                error_log('EmailThreads: injectPreviousMessages - No content to modify');
                return;
            }
            
            // Generate simple thread content
            $threadContent = '<div style="margin: 20px 0; padding: 15px; background: #f5f5f5; border-left: 4px solid #4caf50; font-family: Arial, sans-serif;">
                <strong style="color: #2e7d32;">📧 Previous Messages in This Thread</strong><br>';
            
            foreach ($existingMessages as $index => $message) {
                $threadContent .= '<div style="margin: 10px 0; padding: 10px; background: white; border: 1px solid #ddd;">
                    <strong>Message ' . ($index + 1) . ':</strong> ' . htmlspecialchars($message->getSubject() ?: 'No Subject') . '<br>
                    <small>Date: ' . ($message->getDateSent() ? $message->getDateSent()->format('Y-m-d H:i:s') : 'Unknown') . '</small>
                </div>';
            }
            
            $threadContent .= '</div>';
            
            $newContent = $content . $threadContent;
            $event->setContent($newContent);
            
            error_log('EmailThreads: injectPreviousMessages - Injected thread content');
            
        } catch (\Exception $e) {
            error_log('EmailThreads: injectPreviousMessages - Error: ' . $e->getMessage());
        }
    }
}
