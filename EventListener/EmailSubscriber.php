<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEmailThreadsBundle\EventListener;

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

    public function __construct(
        $threadModel = null,
        $messageModel = null,
        $coreParametersHelper = null,
        $router = null
    ) {
        $this->threadModel = $threadModel;
        $this->messageModel = $messageModel;
        $this->coreParametersHelper = $coreParametersHelper;
        $this->router = $router;
        
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
        $events['mautic.campaign_on_trigger'] = ['onCampaignTrigger', 0];
        $events['mautic.campaign.on_event_trigger'] = ['onCampaignEventTrigger', 0];
        
        return $events;
    }

    public function onEmailSend(EmailSendEvent $event): void
    {
        error_log('EmailThreads: EmailSubscriber::onEmailSend called');
        
        // Check if content was already modified (contains our thread content)
        $content = $event->getContent();
        if ($content && strpos($content, 'email-thread-container') !== false) {
            error_log('EmailThreads: onEmailSend - Thread content already present, skipping');
            return;
        }
        
        // Process email threading
        $this->processEmailThreading($event);
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

        // Append to email content
        $newContent = $content . $threadFooter;
        $event->setContent($newContent);
        
        error_log('EmailThreads: injectThreadContent - New content length: ' . strlen($newContent));
        error_log('EmailThreads: injectThreadContent - Content successfully updated');
    }

    /**
     * Handle email display events (for tracking when emails are viewed)
     */
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
