<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEmailThreadsBundle\EventListener;

use Mautic\CoreBundle\Helper\CoreParametersHelper;
use Mautic\EmailBundle\EmailEvents;
use Mautic\EmailBundle\Event\EmailSendEvent;
use MauticPlugin\MauticEmailThreadsBundle\Model\EmailThreadModel;
use MauticPlugin\MauticEmailThreadsBundle\Model\EmailThreadMessageModel;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class EmailSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EmailThreadModel $threadModel,
        private EmailThreadMessageModel $messageModel,
        private CoreParametersHelper $coreParametersHelper,
        private UrlGeneratorInterface $router
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            EmailEvents::EMAIL_ON_SEND     => ['onEmailSend', 0],
            EmailEvents::EMAIL_ON_DISPLAY  => ['onEmailDisplay', 0],
            EmailEvents::EMAIL_PRE_SEND    => ['onEmailPreSend', 0],
        ];
    }

    public function onEmailSend(EmailSendEvent $event): void
    {
        if (!$this->coreParametersHelper->get('emailthreads_enabled')) {
            return;
        }

        $leadData = $event->getLead();
        $email = $event->getEmail();
        
        if (!$leadData || !$email) {
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
            
            // Add the message to the thread with proper threading
            $message = $this->messageModel->addMessageToThread($thread, $email, null, $event);
            
            // Generate thread content with quoted previous messages
            $threadContent = $this->generateThreadContent($thread, $message);
            
            // Update email content with threaded conversation
            $this->injectThreadContent($event, $threadContent, $thread);
            
        } catch (\Exception $e) {
            // Log error but don't break email sending
            error_log('EmailThreads error: ' . $e->getMessage());
        }
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
        // Remove HTML tags for cleaner quoting
        $textContent = strip_tags($content);
        
        // Split into lines and add > prefix
        $lines = explode("\n", $textContent);
        $quotedLines = array_map(function($line) {
            return '> ' . trim($line);
        }, $lines);
        
        // Limit to first 5 lines to avoid very long quotes
        if (count($quotedLines) > 5) {
            $quotedLines = array_slice($quotedLines, 0, 5);
            $quotedLines[] = '> [... message truncated ...]';
        }
        
        return implode("<br>", $quotedLines);
    }

    /**
     * Inject threaded content into the email
     */
    private function injectThreadContent(EmailSendEvent $event, string $threadContent, $thread): void
    {
        $content = $event->getContent();
        if (!$content) {
            return;
        }

        // Generate thread URL
        $threadUrl = $this->generateThreadUrl($thread->getThreadId());
        
        // Create thread footer with conversation history
        $threadFooter = sprintf(
            '<div class="email-thread-container" style="margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px;">
                <div class="thread-header" style="margin-bottom: 15px;">
                    <h3 style="margin: 0; font-size: 16px; color: #333;">Conversation History</h3>
                    <p style="margin: 5px 0 0 0; font-size: 12px; color: #666;">
                        <a href="%s" style="color: #007bff; text-decoration: none;">View complete conversation online</a>
                    </p>
                </div>
                <div class="thread-content">%s</div>
            </div>',
            $threadUrl,
            $threadContent
        );

        // Append to email content
        $event->setContent($content . $threadFooter);
    }
                
                $modifiedContent = $content . $threadLink;
                $event->setContent($modifiedContent);
            }
            
            // Add message to thread
            $emailStat = $event->getStat();
            $this->messageModel->addMessageToThread($thread, $email, $emailStat, $event);
            
        } catch (\Exception $e) {
            // Log error but don't break email sending
            error_log('Email Threads Plugin Error during send: ' . $e->getMessage());
        }
    }

    /**
     * Handle email display events (for tracking when emails are viewed)
     */
    public function onEmailDisplay($event): void
    {
        // This can be used to track email opens in threads
        // Implementation can be added later if needed
    }

    /**
     * Handle pre-send events (for additional processing before send)
     */
    public function onEmailPreSend($event): void
    {
        // This can be used for additional pre-processing
        // Implementation can be added later if needed
    }

    private function generateThreadUrl(string $threadId): string
    {
        return $this->router->generate(
            'mautic_emailthreads_public',
            ['threadId' => $threadId],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }
}
