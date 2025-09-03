<?php
$view->extend('MauticCoreBundle:Default:content.html.php');
$view['slots']->set('mauticContent', 'emailthreads');
$view['slots']->set('headerTitle', $view['translator']->trans('mautic.emailthreads.config'));
?>

<div class="row">
    <div class="col-md-8 col-md-offset-2">
        <div class="panel panel-default">
            <div class="panel-heading">
                <h3 class="panel-title"><?php echo $view['translator']->trans('mautic.emailthreads.config.title'); ?></h3>
            </div>
            <div class="panel-body">
                <?php echo $view['form']->start($form); ?>
                
                <div class="form-group">
                    <?php echo $view['form']->row($form['emailthreads_enabled']); ?>
                </div>
                
                <div class="form-group">
                    <?php echo $view['form']->row($form['emailthreads_domain']); ?>
                </div>
                
                <div class="form-group">
                    <?php echo $view['form']->row($form['emailthreads_auto_thread']); ?>
                </div>
                
                <div class="form-group">
                    <?php echo $view['form']->row($form['emailthreads_thread_lifetime']); ?>
                    <div class="help-block">
                        <?php echo $view['translator']->trans('mautic.emailthreads.config.thread_lifetime.help'); ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <?php echo $view['form']->row($form['emailthreads_include_unsubscribe']); ?>
                </div>
                
                <div class="form-group">
                    <?php echo $view['form']->widget($form['save']); ?>
                </div>
                
                <?php echo $view['form']->end($form); ?>
            </div>
        </div>
        
        <div class="panel panel-info">
            <div class="panel-heading">
                <h3 class="panel-title"><?php echo $view['translator']->trans('mautic.emailthreads.maintenance'); ?></h3>
            </div>
            <div class="panel-body">
                <p><?php echo $view['translator']->trans('mautic.emailthreads.maintenance.description'); ?></p>
                <button type="button" class="btn btn-warning" onclick="cleanupThreads()">
                    <i class="fa fa-trash"></i>
                    <?php echo $view['translator']->trans('mautic.emailthreads.cleanup_threads'); ?>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function cleanupThreads() {
    if (!confirm('<?php echo $view['translator']->trans('mautic.emailthreads.cleanup_confirm'); ?>')) {
        return;
    }
    
    fetch('<?php echo $view['router']->path('mautic_emailthreads_cleanup'); ?>', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        alert('Error: ' + error.message);
    });
}
</script>
