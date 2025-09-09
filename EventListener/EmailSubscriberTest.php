<?php

namespace MauticPlugin\MauticEmailThreadsBundle\EventListener;

use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Event\EmailSendEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Test EmailSubscriber that only modifies content without database operations
 */
class EmailSubscriberTest implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            EmailEvents::EMAIL_ON_SEND => ['onEmailSend', 0],
        ];
    }

    public function onEmailSend(EmailSendEvent $event): void
    {
        error_log('EmailThreadsTest: onEmailSend triggered');
        
        try {
            $email = $event->getEmail();
            $lead = $event->getLead();
            
            if (!$email || !$lead) {
                error_log('EmailThreadsTest: Missing email or lead data');
                return;
            }
            
            error_log('EmailThreadsTest: Processing email for lead ID: ' . $lead['id']);
            
            // Get current content
            $content = $event->getContent();
            
            // Add test threading content
            $threadedContent = $this->addTestThreadContent($content, $lead);
            
            // Update the email content
            $event->setContent($threadedContent);
            
            error_log('EmailThreadsTest: Content modified successfully');
            
        } catch (\Exception $e) {
            error_log('EmailThreadsTest: Error in onEmailSend: ' . $e->getMessage());
        }
    }
    
    private function addTestThreadContent(string $content, array $lead): string
    {
        // Simulate previous message content
        $previousMessage = '
<div style="border-left: 4px solid #ccc; padding-left: 15px; margin: 20px 0; color: #666;">
    <h4 style="color: #333;">Previous Message:</h4>
    <p><strong>From:</strong> test@example.com</p>
    <p><strong>Subject:</strong> Previous email subject</p>
    <p><strong>Date:</strong> ' . date('Y-m-d H:i:s', strtotime('-1 day')) . '</p>
    <div style="margin-top: 10px;">
        <p>This is a simulated previous message content to test the threading functionality.</p>
        <p>Lead: ' . ($lead['firstname'] ?? 'Unknown') . ' ' . ($lead['lastname'] ?? '') . '</p>
    </div>
</div>';
        
        // Add the test blue box
        $testBox = '
<div style="background-color: #e3f2fd; border: 2px solid #2196f3; padding: 10px; margin: 15px 0; border-radius: 5px;">
    <strong style="color: #1976d2;">✅ EmailThreads Test Plugin Active</strong><br>
    <small style="color: #666;">Thread processing enabled - Previous messages should appear above</small>
</div>';
        
        // Combine content
        return $content . $previousMessage . $testBox;
    }
}
