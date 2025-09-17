<?php

declare(strict_types=1);

return [
    'name'        => 'Email Threads',
    'description' => 'Display emails sent from Mautic as conversation threads on the recipient\'s end. Works with all email types including one-to-one, campaigns, and broadcasts.',
    'version'     => '1.0.0',
    'author'      => 'Mahir Musleh',
    'contact'     => 'arc.mahir@gmail.com',
    
    'routes' => [
        'public' => [
            'mautic_emailthreads_public' => [
                'path'       => '/email-thread/{threadId}',
                'controller' => 'MauticPlugin\MauticEmailThreadsBundle\Controller\PublicController::viewAction',
                'requirements' => ['threadId' => '[a-zA-Z0-9\-]+'],
            ],
            'mautic_emailthreads_embed' => [
                'path'       => '/email-thread/{threadId}/embed',
                'controller' => 'MauticPlugin\MauticEmailThreadsBundle\Controller\PublicController::embedAction',
                'requirements' => ['threadId' => '[a-zA-Z0-9\-]+'],
            ],
        ],
    ],
    
    
    'services' => [
        'events' => [
            'mautic.emailthreads.subscriber.email' => [
                'class'     => \MauticPlugin\MauticEmailThreadsBundle\EventListener\EmailSubscriberMinimal::class,
                'arguments' => ['doctrine.orm.entity_manager'],
                'tags' => ['kernel.event_subscriber'],
            ],
            'mautic.emailthreads.subscriber.install' => [
                'class'     => \MauticPlugin\MauticEmailThreadsBundle\EventListener\InstallSubscriber::class,
                'arguments' => ['doctrine.orm.entity_manager'],
                'tags' => ['kernel.event_subscriber'],
            ],
        ],
        'models' => [
            'mautic.emailthreads.model.thread' => [
                'class' => \MauticPlugin\MauticEmailThreadsBundle\Model\EmailThreadModel::class,
                'arguments' => [
                    'doctrine.orm.entity_manager',
                ],
            ],
            'mautic.emailthreads.model.message' => [
                'class' => \MauticPlugin\MauticEmailThreadsBundle\Model\EmailThreadMessageModel::class,
                'arguments' => [
                    'doctrine.orm.entity_manager',
                ],
            ],
        ],
        'repositories' => [
            'mautic.emailthreads.repository.thread' => [
                'class' => \MauticPlugin\MauticEmailThreadsBundle\Entity\EmailThreadRepository::class,
                'factory' => ['@doctrine.orm.entity_manager', 'getRepository'],
                'arguments' => [\MauticPlugin\MauticEmailThreadsBundle\Entity\EmailThread::class],
            ],
        ],
    ],
    
    'parameters' => [
        'emailthreads_enabled' => true,
        'emailthreads_domain' => '',
        'emailthreads_auto_thread' => true,
        'emailthreads_thread_lifetime' => 30, // days
        'emailthreads_include_unsubscribe' => true,
        'emailthreads_inject_previous_messages' => true, // Inject previous messages as quotes in emails
    ],
    
    'twig' => [
        'paths' => [
            '%kernel.project_dir%/plugins/MauticEmailThreadsBundle/Views' => 'MauticEmailThreadsBundle',
        ],
    ],
    
];
