<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEmailThreadsBundle\Controller;

use Mautic\CoreBundle\Controller\AbstractFormController;
use MauticPlugin\MauticEmailThreadsBundle\Model\EmailThreadModel;
use MauticPlugin\MauticEmailThreadsBundle\Model\EmailThreadMessageModel;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PublicController extends AbstractFormController
{
    public function __construct(
        private EmailThreadModel $threadModel,
        private EmailThreadMessageModel $messageModel
    ) {
    }
    public function viewAction(Request $request, string $threadId): Response
    {
        $thread = $this->threadModel->findByThreadId($threadId);
        
        if (!$thread || !$thread->isActive()) {
            throw $this->createNotFoundException('Thread not found or inactive');
        }

        $messages = $this->messageModel->getMessagesByThread($thread);

        return $this->render('MauticEmailThreadsBundle:Public:thread.html.twig', [
            'thread' => $thread,
            'messages' => $messages,
        ]);
    }

    public function embedAction(Request $request, string $threadId): Response
    {
        $thread = $this->threadModel->findByThreadId($threadId);
        
        if (!$thread || !$thread->isActive()) {
            throw $this->createNotFoundException('Thread not found or inactive');
        }

        $messages = $this->messageModel->getMessagesByThread($thread);

        $response = $this->render('MauticEmailThreadsBundle:Public:thread_embed.html.twig', [
            'thread' => $thread,
            'messages' => $messages,
        ]);

        // Add headers for embedding
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Content-Security-Policy', "frame-ancestors 'self'");

        return $response;
    }
}
