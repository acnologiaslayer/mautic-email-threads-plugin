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
            EmailEvents::EMAIL_POST_SEND   => ['onEmailPostSend', 0],
        ];
    }

    public function onEmailSend(EmailSendEvent $event): void
    {
        if (!$this->coreParametersHelper->get('emailthreads_enabled')) {
            return;
        }

        $lead = $event->getLead();
        $email = $event->getEmail();
        
        if (!$lead || !$email) {
            return;
        }

        // Add thread tracking to email content
        $content = $event->getContent();
        if ($content) {
            try {
                $threadId = $this->getOrCreateThreadId($lead, $email, $event);
                $threadUrl = $this->generateThreadUrl($threadId);
                
                // Add thread link to email content
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
            } catch (\Exception $e) {
                // Log error but don't break email sending
                error_log('Email Threads Plugin Error during send: ' . $e->getMessage());
            }
        }
    }

    public function onEmailPostSend(EmailSendEvent $event): void
    {
        if (!$this->coreParametersHelper->get('emailthreads_enabled')) {
            return;
        }

        $lead = $event->getLead();
        $email = $event->getEmail();
        $emailStat = $event->getStat();
        
        if (!$lead || !$email) {
            return;
        }

        try {
            // Create or update thread
            $thread = $this->threadModel->findOrCreateThread($lead, $email, $event);
            
            // Add message to thread
            $this->messageModel->addMessageToThread($thread, $email, $emailStat, $event);
            
        } catch (\Exception $e) {
            // Log error but don't break email sending
            error_log('Email Threads Plugin Error during post-send: ' . $e->getMessage());
        }
    }

    private function getOrCreateThreadId($lead, $email, EmailSendEvent $event): string
    {
        $thread = $this->threadModel->findOrCreateThread($lead, $email, $event);
        return $thread->getThreadId();
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
