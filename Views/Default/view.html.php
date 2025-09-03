<?php
$view->extend('MauticCoreBundle:Default:content.html.php');
$view['slots']->set('mauticContent', 'emailthreads');
$view['slots']->set('headerTitle', $view['translator']->trans('mautic.emailthreads.thread.view', ['%subject%' => $thread->getSubject()]));

$view['slots']->start('actions');
?>
<div class="btn-group">
    <a href="<?php echo $view['router']->path('mautic_emailthreads_index'); ?>" class="btn btn-default">
        <i class="fa fa-arrow-left"></i>
        <?php echo $view['translator']->trans('mautic.core.back'); ?>
    </a>
    <a href="<?php echo $view['router']->path('mautic_emailthreads_public', ['threadId' => $thread->getThreadId()]); ?>" 
       class="btn btn-primary" target="_blank">
        <i class="fa fa-external-link"></i>
        <?php echo $view['translator']->trans('mautic.emailthreads.view_public'); ?>
    </a>
</div>
<?php
$view['slots']->stop();
?>

<div class="row">
    <div class="col-md-8">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><?php echo $view['translator']->trans('mautic.emailthreads.messages'); ?></h3>
            </div>
            <div class="panel-body">
                <?php if ($thread->getMessages()->isEmpty()): ?>
                    <p class="text-muted"><?php echo $view['translator']->trans('mautic.emailthreads.no_messages'); ?></p>
                <?php else: ?>
                    <?php foreach ($thread->getMessages() as $message): ?>
                        <div class="email-message" style="border-left: 3px solid #007bff; padding: 15px; margin-bottom: 20px; background: #f8f9fa;">
                            <div class="message-header" style="margin-bottom: 10px;">
                                <h5 style="margin: 0; color: #333;">
                                    <?php echo $view->escape($message->getSubject()); ?>
                                </h5>
                                <small class="text-muted">
                                    From: <?php echo $view->escape($message->getFromName() ?: $message->getFromEmail()); ?>
                                    | <?php echo $view['date']->toText($message->getDateSent(), 'local', 'Y-m-d H:i:s'); ?>
                                    | Type: <?php echo ucfirst($message->getEmailType()); ?>
                                </small>
                            </div>
                            <div class="message-content">
                                <?php echo $message->getContent(); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-4">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><?php echo $view['translator']->trans('mautic.emailthreads.thread.details'); ?></h3>
            </div>
            <div class="panel-body">
                <dl>
                    <dt><?php echo $view['translator']->trans('mautic.emailthreads.thread.id'); ?></dt>
                    <dd><code><?php echo $view->escape($thread->getThreadId()); ?></code></dd>
                    
                    <dt><?php echo $view['translator']->trans('mautic.emailthreads.thread.lead'); ?></dt>
                    <dd>
                        <?php if ($thread->getLead()): ?>
                            <a href="<?php echo $view['router']->path('mautic_contact_action', ['objectAction' => 'view', 'objectId' => $thread->getLead()->getId()]); ?>">
                                <?php echo $view->escape($thread->getLead()->getName() ?: $thread->getLead()->getEmail()); ?>
                            </a>
                        <?php else: ?>
                            <em><?php echo $view['translator']->trans('mautic.emailthreads.unknown_lead'); ?></em>
                        <?php endif; ?>
                    </dd>
                    
                    <dt><?php echo $view['translator']->trans('mautic.emailthreads.thread.subject'); ?></dt>
                    <dd><?php echo $view->escape($thread->getSubject()); ?></dd>
                    
                    <dt><?php echo $view['translator']->trans('mautic.emailthreads.thread.message_count'); ?></dt>
                    <dd><?php echo $thread->getMessageCount(); ?></dd>
                    
                    <dt><?php echo $view['translator']->trans('mautic.emailthreads.thread.first_message'); ?></dt>
                    <dd><?php echo $view['date']->toText($thread->getFirstMessageDate(), 'local', 'Y-m-d H:i:s'); ?></dd>
                    
                    <dt><?php echo $view['translator']->trans('mautic.emailthreads.thread.last_message'); ?></dt>
                    <dd><?php echo $view['date']->toText($thread->getLastMessageDate(), 'local', 'Y-m-d H:i:s'); ?></dd>
                    
                    <dt><?php echo $view['translator']->trans('mautic.emailthreads.thread.status'); ?></dt>
                    <dd>
                        <span class="label label-<?php echo $thread->isActive() ? 'success' : 'default'; ?>">
                            <?php echo $view['translator']->trans($thread->isActive() ? 'mautic.emailthreads.active' : 'mautic.emailthreads.inactive'); ?>
                        </span>
                    </dd>
                </dl>
            </div>
        </div>
    </div>
</div>
