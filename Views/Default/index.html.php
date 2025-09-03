<?php
$view->extend('MauticCoreBundle:Default:content.html.php');
$view['slots']->set('mauticContent', 'emailthreads');
$view['slots']->set('headerTitle', $view['translator']->trans('mautic.emailthreads.threads'));

$view['slots']->start('actions');
?>
<div class="btn-group">
    <a href="<?php echo $view['router']->path('mautic_emailthreads_config'); ?>" class="btn btn-default">
        <i class="fa fa-cog"></i>
        <?php echo $view['translator']->trans('mautic.core.settings'); ?>
    </a>
</div>
<?php
$view['slots']->stop();
?>

<div class="panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title"><?php echo $view['translator']->trans('mautic.emailthreads.threads.list'); ?></h3>
    </div>
    <div class="panel-body">
        <?php if (empty($threads)): ?>
            <p class="text-muted"><?php echo $view['translator']->trans('mautic.emailthreads.no_threads'); ?></p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th><?php echo $view['translator']->trans('mautic.emailthreads.thread.subject'); ?></th>
                            <th><?php echo $view['translator']->trans('mautic.emailthreads.thread.lead'); ?></th>
                            <th><?php echo $view['translator']->trans('mautic.emailthreads.thread.messages'); ?></th>
                            <th><?php echo $view['translator']->trans('mautic.emailthreads.thread.last_message'); ?></th>
                            <th><?php echo $view['translator']->trans('mautic.core.actions'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($threads as $thread): ?>
                            <tr>
                                <td>
                                    <a href="<?php echo $view['router']->path('mautic_emailthreads_view', ['id' => $thread->getId()]); ?>">
                                        <?php echo $view->escape($thread->getSubject()); ?>
                                    </a>
                                </td>
                                <td>
                                    <?php if ($thread->getLead()): ?>
                                        <?php echo $view->escape($thread->getLead()->getName() ?: $thread->getLead()->getEmail()); ?>
                                    <?php else: ?>
                                        <em><?php echo $view['translator']->trans('mautic.emailthreads.unknown_lead'); ?></em>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge"><?php echo $thread->getMessageCount(); ?></span>
                                </td>
                                <td>
                                    <?php echo $view['date']->toText($thread->getLastMessageDate(), 'local', 'Y-m-d H:i'); ?>
                                </td>
                                <td>
                                    <a href="<?php echo $view['router']->path('mautic_emailthreads_view', ['id' => $thread->getId()]); ?>" 
                                       class="btn btn-xs btn-default">
                                        <i class="fa fa-eye"></i>
                                    </a>
                                    <a href="<?php echo $view['router']->path('mautic_emailthreads_public', ['threadId' => $thread->getThreadId()]); ?>" 
                                       class="btn btn-xs btn-primary" target="_blank">
                                        <i class="fa fa-external-link"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
