<?php

declare(strict_types=1);

namespace MauticPlugin\MauticEmailThreadsBundle\EventListener;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Mautic\PluginBundle\Event\PluginInstallEvent;
use Mautic\PluginBundle\Event\PluginUpdateEvent;
use Mautic\PluginBundle\PluginEvents;
use MauticPlugin\MauticEmailThreadsBundle\Entity\EmailThread;
use MauticPlugin\MauticEmailThreadsBundle\Entity\EmailThreadMessage;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class InstallSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PluginEvents::ON_PLUGIN_INSTALL => ['onPluginInstall', 0],
            PluginEvents::ON_PLUGIN_UPDATE  => ['onPluginUpdate', 0],
        ];
    }

    public function onPluginInstall(PluginInstallEvent $event): void
    {
        $plugin = $event->getPlugin();
        
        if ($plugin->getName() === 'MauticEmailThreadsBundle') {
            $this->createDatabaseSchema();
        }
    }

    public function onPluginUpdate(PluginUpdateEvent $event): void
    {
        $plugin = $event->getPlugin();
        
        if ($plugin->getName() === 'MauticEmailThreadsBundle') {
            $this->updateDatabaseSchema();
        }
    }

    private function createDatabaseSchema(): void
    {
        try {
            $metadata = [
                $this->entityManager->getClassMetadata(EmailThread::class),
                $this->entityManager->getClassMetadata(EmailThreadMessage::class),
            ];
            
            $schemaTool = new SchemaTool($this->entityManager);
            $schemaTool->createSchema($metadata);
            
        } catch (\Exception $e) {
            // Log the error but don't fail installation
            error_log('Email Threads Plugin installation error: ' . $e->getMessage());
        }
    }

    private function updateDatabaseSchema(): void
    {
        try {
            $metadata = [
                $this->entityManager->getClassMetadata(EmailThread::class),
                $this->entityManager->getClassMetadata(EmailThreadMessage::class),
            ];
            
            $schemaTool = new SchemaTool($this->entityManager);
            $schemaTool->updateSchema($metadata, true);
            
        } catch (\Exception $e) {
            error_log('Email Threads Plugin update error: ' . $e->getMessage());
        }
    }
}
