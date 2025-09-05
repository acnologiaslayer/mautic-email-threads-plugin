<?php

declare(strict_types=1);

return [
    'name'        => 'Email Threads',
    'description' => 'Display emails sent from Mautic as conversation threads on the recipient\'s end. Works with all email types including one-to-one, campaigns, and broadcasts.',
    'version'     => '1.0.0',
    'author'      => 'Mahir Musleh',
    'contact'     => 'arc.mahir@gmail.com',
    
    'routes' => [
        'main' => [
            'mautic_emailthreads_index' => [
                'path'       => '/emailthreads',
                'controller' => 'MauticPlugin\MauticEmailThreadsBundle\Controller\DefaultController::indexAction',
            ],
            'mautic_emailthreads_view' => [
                'path'       => '/emailthreads/view/{id}',
                'controller' => 'MauticPlugin\MauticEmailThreadsBundle\Controller\DefaultController::viewAction',
                'requirements' => ['id' => '\d+'],
            ],
            'mautic_emailthreads_config' => [
                'path'       => '/emailthreads/config',
                'controller' => 'MauticPlugin\MauticEmailThreadsBundle\Controller\DefaultController::configAction',
            ],
            'mautic_emailthreads_cleanup' => [
                'path'       => '/emailthreads/cleanup',
                'controller' => 'MauticPlugin\MauticEmailThreadsBundle\Controller\DefaultController::cleanupAction',
            ],
        ],
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
    
    'menu' => [
        'main' => [
            'items' => [
                'Email Threads' => [
                    'route'    => 'mautic_emailthreads_index',
                    'parent'   => 'mautic.core.channels',
                    'priority' => 65,
                ],
            ],
        ],
    ],
    
    'services' => [
        'events' => [
            'mautic.emailthreads.subscriber.email' => [
                'class'     => \MauticPlugin\MauticEmailThreadsBundle\EventListener\EmailSubscriber::class,
                'arguments' => [
                    'mautic.emailthreads.model.thread',
                    'mautic.emailthreads.model.message',
                    'mautic.helper.core_parameters',
                    'router',
                    'doctrine.orm.entity_manager',
                ],
                'tags' => ['kernel.event_subscriber'],
            ],
            'mautic.emailthreads.subscriber.install' => [
                'class'     => \MauticPlugin\MauticEmailThreadsBundle\EventListener\InstallSubscriber::class,
                'arguments' => [
                    'doctrine.orm.entity_manager',
                ],
                'tags' => ['kernel.event_subscriber'],
            ],
        ],
        'forms' => [
            'mautic.emailthreads.form.type.config' => [
                'class' => \MauticPlugin\MauticEmailThreadsBundle\Form\Type\ConfigType::class,
                'tags' => ['form.type'],
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
    
    'categories' => [
        'plugin:emailthreads' => 'mautic.emailthreads.permissions.emailthreads',
    ],
    
    'permissions' => [
        'plugin:emailthreads' => [
            'emailthreads:threads:view'   => 'mautic.core.permissions.view',
            'emailthreads:threads:create' => 'mautic.core.permissions.create',
            'emailthreads:threads:edit'   => 'mautic.core.permissions.edit',
            'emailthreads:threads:delete' => 'mautic.core.permissions.delete',
            'emailthreads:config:manage'  => 'mautic.emailthreads.permissions.config',
        ],
    ],
];
