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
        file_put_contents('/tmp/emailthreads_debug.log', date('Y-m-d H:i:s') . " - EmailSubscriber constructor called\n", FILE_APPEND);
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EmailEvents::EMAIL_ON_SEND     => ['onEmailSend', 0],
            EmailEvents::EMAIL_ON_DISPLAY  => ['onEmailDisplay', 0],
            EmailEvents::EMAIL_PRE_SEND    => ['onEmailPreSend', 0],
            // Add more email events to catch all possibilities
            'mautic.email_on_send'         => ['onEmailSend', 0],
            'mautic.email_pre_send'        => ['onEmailPreSend', 0],
        ];
    }

    public function onEmailSend(EmailSendEvent $event): void
    {
        // Simple file logging to verify the event is triggered
        file_put_contents('/tmp/emailthreads_debug.log', date('Y-m-d H:i:s') . " - EmailSubscriber::onEmailSend called\n", FILE_APPEND);
        
        // Always inject test content to verify the plugin is working
        $this->injectSimpleTestContent($event);
        
        // Check if services are available
        if (!$this->threadModel || !$this->messageModel) {
            file_put_contents('/tmp/emailthreads_debug.log', date('Y-m-d H:i:s') . " - Missing required services, skipping\n", FILE_APPEND);
            return;
        }
        
        // For debugging - temporarily disable the enabled check
        $isEnabled = $this->coreParametersHelper ? $this->coreParametersHelper->get('emailthreads_enabled', true) : true;
        error_log("EmailThreads: Plugin enabled check: " . ($isEnabled ? 'true' : 'false'));
        
        if (!$isEnabled) {
            error_log("EmailThreads: Plugin is disabled, skipping");
            file_put_contents('/tmp/emailthreads_debug.log', date('Y-m-d H:i:s') . " - Plugin disabled, skipping\n", FILE_APPEND);
            return;
        }

        $leadData = $event->getLead();
        $email = $event->getEmail();
        
        error_log("EmailThreads: Processing email send event - Email ID: " . ($email ? $email->getId() : 'null'));
        file_put_contents('/tmp/emailthreads_debug.log', date('Y-m-d H:i:s') . " - Processing email ID: " . ($email ? $email->getId() : 'null') . "\n", FILE_APPEND);
        
        if (!$leadData || !$email) {
            error_log("EmailThreads: Missing lead data or email, skipping");
            file_put_contents('/tmp/emailthreads_debug.log', date('Y-m-d H:i:s') . " - Missing lead data or email, skipping\n", FILE_APPEND);
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
                return;
            }
            
            // Create or update thread - pass lead data that we have
            $thread = $this->threadModel->findOrCreateThread($leadData, $email, $event);
            
            // Debug logging
            error_log("EmailThreads: Thread ID: " . $thread->getThreadId() . ", Lead ID: " . $leadId);
            file_put_contents('/tmp/emailthreads_debug.log', date('Y-m-d H:i:s') . " - Created/found thread: " . $thread->getThreadId() . " for lead: " . $leadId . "\n", FILE_APPEND);
            
            // First add the current message to get all thread messages
            $currentMessage = $this->messageModel->addMessageToThread($thread, $email, null, $event);
            file_put_contents('/tmp/emailthreads_debug.log', date('Y-m-d H:i:s') . " - Added current message to thread\n", FILE_APPEND);
            
            // Now inject quoted content from previous messages
            $allMessages = $this->messageModel->getMessagesByThread($thread);
            file_put_contents('/tmp/emailthreads_debug.log', date('Y-m-d H:i:s') . " - Total messages in thread now: " . count($allMessages) . "\n", FILE_APPEND);
            
            // Generate thread content with quoted previous messages (exclude current message)
            $previousMessages = array_slice($allMessages, 0, -1); // All except the last (current) message
            $threadContent = $this->generateThreadContentWithPrevious($previousMessages, $email, $event);
            
            // Debug logging
            error_log("EmailThreads: Generated thread content length: " . strlen($threadContent));
            file_put_contents('/tmp/emailthreads_debug.log', date('Y-m-d H:i:s') . " - Generated thread content length: " . strlen($threadContent) . "\n", FILE_APPEND);
            
            // Update email content with threaded conversation
            if (!empty($threadContent)) {
                $this->injectThreadContent($event, $threadContent, $thread);
                error_log("EmailThreads: Injected thread content into email");
            } else {
                error_log("EmailThreads: No thread content to inject (first message)");
            }
            
        } catch (\Exception $e) {
            // Log error but don't break email sending
            error_log('EmailThreads error: ' . $e->getMessage());
        }
    }

    /**
     * Generate Gmail/Outlook style threaded content showing only previous messages
     */
    private function generateThreadContentWithPrevious(array $previousMessages, $currentEmail, $event): string
    {
        if (empty($previousMessages)) {
            return ''; // No previous messages to show
        }
        
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
     * Test method to verify email content injection works
     */
    private function injectTestContent(EmailSendEvent $event): void
    {
        $content = $event->getContent();
        if (!$content) {
            return;
        }

        $testFooter = '<div style="background: #ffeb3b; padding: 10px; margin-top: 20px; border-radius: 5px;"><strong>🧪 EmailThreads Plugin Active</strong> - This message shows the plugin is working. Quoted messages will appear here after the first email.</div>';
        
        $event->setContent($content . $testFooter);
        error_log("EmailThreads: Added test content to verify injection mechanism");
    }

    /**
     * Handle email display events (for tracking when emails are viewed)
     */
    public function onEmailDisplay($event): void
    {
        file_put_contents('/tmp/emailthreads_debug.log', date('Y-m-d H:i:s') . " - EmailSubscriber::onEmailDisplay called\n", FILE_APPEND);
        // This can be used to track email opens in threads
        // Implementation can be added later if needed
    }

    /**
     * Handle pre-send events (for additional processing before send)
     */
    public function onEmailPreSend($event): void
    {
        file_put_contents('/tmp/emailthreads_debug.log', date('Y-m-d H:i:s') . " - EmailSubscriber::onEmailPreSend called\n", FILE_APPEND);
        // This can be used for additional pre-processing
        // Implementation can be added later if needed
    }

    /**
     * Simple test method to verify email content injection works at the most basic level
     */
    private function injectSimpleTestContent(EmailSendEvent $event): void
    {
        try {
            $content = $event->getContent();
            if ($content) {
                $testFooter = '<div style="background: #ff5722; color: white; padding: 10px; margin-top: 20px; border-radius: 5px; text-align: center;"><strong>🔥 EMAILTHREADS PLUGIN IS ACTIVE</strong><br>This proves the event listener is working!</div>';
                $event->setContent($content . $testFooter);
                file_put_contents('/tmp/emailthreads_debug.log', date('Y-m-d H:i:s') . " - Simple test content injected successfully\n", FILE_APPEND);
            }
        } catch (\Exception $e) {
            file_put_contents('/tmp/emailthreads_debug.log', date('Y-m-d H:i:s') . " - Error injecting test content: " . $e->getMessage() . "\n", FILE_APPEND);
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
