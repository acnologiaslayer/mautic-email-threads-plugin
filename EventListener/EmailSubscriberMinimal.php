<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEmailThreadsBundle\EventListener;

use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Event\EmailSendEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class EmailSubscriberMinimal implements EventSubscriberInterface
{
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
}
