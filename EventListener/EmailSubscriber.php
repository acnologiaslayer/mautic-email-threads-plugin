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
            // Convert lead data to Lead entity if needed
            if (is_array($leadData)) {
                // If we have lead data as array, we need to either skip or find the lead entity
                // For now, let's skip processing if we don't have a proper Lead entity
                return;
            }
            
            // Create or update thread
            $thread = $this->threadModel->findOrCreateThread($leadData, $email, $event);
            $threadUrl = $this->generateThreadUrl($thread->getThreadId());
            
            // Add thread link to email content
            $content = $event->getContent();
            if ($content) {
                $threadLink = sprintf(
                    '<div style="margin: 20px 0; padding: 10px; background: #f8f9fa; border-left: 4px solid #007bff; font-size: 12px;">
                        <p style="margin: 0; color: #6c757d;">
                            <a href="%s" style="color: #007bff; text-decoration: none;">View this conversation</a>
                        </p>
                    </div>',
                    $threadUrl
                );
                
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

    private function generateThreadUrl(string $threadId): string
    {
        return $this->router->generate(
            'mautic_emailthreads_public',
            ['threadId' => $threadId],
            UrlGeneratorInterface::ABSOLUTE_URL
        );
    }
}
