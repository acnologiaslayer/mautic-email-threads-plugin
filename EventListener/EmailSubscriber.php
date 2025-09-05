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
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EmailEvents::EMAIL_ON_SEND     => ['onEmailSend', 0],
            EmailEvents::EMAIL_ON_DISPLAY  => ['onEmailDisplay', 0], 
            EmailEvents::EMAIL_PRE_SEND    => ['onEmailPreSend', 0],
            // Add more email events to catch all possibilities including campaigns
            'mautic.email_on_send'         => ['onEmailSend', 0],
            'mautic.email_pre_send'        => ['onEmailPreSend', 0],
            'mautic.campaign_on_trigger'   => ['onCampaignTrigger', 0],
            // Campaign events that might be used for segment emails
            'mautic.campaign.on_event_trigger' => ['onCampaignEventTrigger', 0],
        ];
    }

    public function onEmailSend(EmailSendEvent $event): void
    {
        // Enhanced logging to debug segment emails
        $email = $event->getEmail();
        $leadData = $event->getLead();
        
        error_log('EmailThreads: EmailSubscriber::onEmailSend called');
        error_log('EmailThreads: Email type: ' . ($email ? $email->getEmailType() : 'null'));
        error_log('EmailThreads: Email subject: ' . ($email ? $email->getSubject() : 'null'));
        error_log('EmailThreads: Lead data type: ' . (is_array($leadData) ? 'array' : (is_object($leadData) ? get_class($leadData) : gettype($leadData))));
        
        // Check if services are available
        if (!$this->threadModel || !$this->messageModel) {
            error_log('EmailThreads: Missing required services, skipping');
            return;
        }
        
        // For debugging - temporarily disable the enabled check
        $isEnabled = $this->coreParametersHelper ? $this->coreParametersHelper->get('emailthreads_enabled', true) : true;
        error_log("EmailThreads: Plugin enabled check: " . ($isEnabled ? 'true' : 'false'));
        
        if (!$isEnabled) {
            error_log("EmailThreads: Plugin is disabled, skipping");
            return;
        }

        $leadData = $event->getLead();
        $email = $event->getEmail();
        
        error_log("EmailThreads: Processing email send event - Email ID: " . ($email ? $email->getId() : 'null'));
        error_log('EmailThreads: Processing email ID: ' . ($email ? $email->getId() : 'null'));
        
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
            
            // First add the current message to get all thread messages
            $currentMessage = $this->messageModel->addMessageToThread($thread, $email, null, $event);
            if (!$currentMessage) {
                error_log('EmailThreads: ERROR: Failed to add current message to thread');
                return;
            }
            error_log('EmailThreads: Added current message to thread');
            
            // Now inject quoted content from previous messages
            $allMessages = $this->messageModel->getMessagesByThread($thread);
            error_log('EmailThreads: Total messages in thread now: ' . count($allMessages));
            
            if (empty($allMessages)) {
                error_log('EmailThreads: WARNING: No messages found in thread after adding current message');
            }
            
            // Generate thread content with quoted previous messages (exclude current message)
            $previousMessages = array_slice($allMessages, 0, -1); // All except the last (current) message
            error_log('EmailThreads: Previous messages for threading: ' . count($previousMessages));
            
            $threadContent = $this->generateThreadContentWithPrevious($previousMessages, $email, $event);
            
            // Debug logging
            error_log("EmailThreads: Generated thread content length: " . strlen($threadContent));
            
            // Update email content with threaded conversation
            if (!empty($threadContent)) {
                $this->injectThreadContent($event, $threadContent, $thread);
                error_log("EmailThreads: Injected thread content into email");
            } else {
                error_log("EmailThreads: No thread content to inject (first message)");
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
        
        $threadHtml = '<div class="email-thread-history" style="margin-top: 20px; border-top: 1px solid #ddd; padding-top: 15px;">';
        $threadHtml .= '<h4 style="margin: 0 0 15px 0; font-size: 14px; color: #666;">Previous Messages:</h4>';
        
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
            
            $threadHtml .= sprintf(
                '<div class="previous-message" style="margin: 15px 0; border-left: 3px solid #007bff; padding-left: 15px; background: #f8f9fa; padding: 10px 10px 10px 15px;">
                    <div class="message-header" style="font-size: 12px; color: #666; margin-bottom: 8px; font-weight: bold;">
                        On %s, %s wrote:
                    </div>
                    <div class="quoted-content" style="font-size: 13px; color: #555; font-style: italic;">
                        %s
                    </div>
                </div>',
                $messageDate,
                htmlspecialchars($fromName),
                $quotedContent
            );
        }
        
        if (count($previousMessages) > $maxMessages) {
            $threadHtml .= '<div style="font-size: 12px; color: #999; text-align: center; margin: 10px 0;">... and ' . (count($previousMessages) - $maxMessages) . ' earlier messages</div>';
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
        if (strlen($textContent) > 200) {
            $textContent = substr($textContent, 0, 200) . '...';
        }
        
        // Add quote styling
        return '<em style="color: #666;">"' . htmlspecialchars($textContent) . '"</em>';
    }

    /**
     * Inject threaded content into the email
     */
    private function injectThreadContent(EmailSendEvent $event, string $threadContent, $thread): void
    {
        $content = $event->getContent();
        if (!$content || empty($threadContent)) {
            return; // No content or no thread history to add
        }

        // Generate thread URL
        $threadUrl = $this->generateThreadUrl($thread->getThreadId());
        
        // Create thread footer with conversation history only if there's actual thread content
        $threadFooter = sprintf(
            '<div class="email-thread-container" style="margin-top: 20px; border-top: 2px solid #007bff; padding-top: 15px; background: #f8f9fa; padding: 15px; border-radius: 5px;">
                <div class="thread-header" style="margin-bottom: 10px;">
                    <h4 style="margin: 0; font-size: 14px; color: #007bff;">📧 Email Thread</h4>
                    <p style="margin: 3px 0 0 0; font-size: 11px; color: #666;">
                        <a href="%s" style="color: #007bff; text-decoration: none;">🔗 View full conversation online</a>
                    </p>
                </div>
                %s
            </div>',
            $threadUrl,
            $threadContent
        );

        // Append to email content
        $event->setContent($content . $threadFooter);
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
     * Handle pre-send events (for additional processing before send)
     */
    public function onEmailPreSend($event): void
    {
        error_log('EmailThreads: EmailSubscriber::onEmailPreSend called');
        // This can be used for additional pre-processing
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
