<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Thread (Embedded): <?php echo htmlspecialchars($thread->getSubject()); ?></title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            margin: 0;
            padding: 15px;
            background: #f8f9fa;
            font-size: 14px;
            line-height: 1.5;
        }
        .thread-container {
            max-width: 100%;
        }
        .thread-header {
            background: white;
            padding: 15px;
            border-radius: 6px;
            margin-bottom: 15px;
            border-left: 3px solid #007bff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .thread-title {
            margin: 0 0 8px 0;
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }
        .thread-meta {
            font-size: 12px;
            color: #666;
        }
        .message {
            background: white;
            border-radius: 6px;
            margin-bottom: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .message-header {
            background: #f8f9fa;
            padding: 12px 15px;
            border-bottom: 1px solid #e9ecef;
        }
        .message-title {
            margin: 0 0 4px 0;
            font-size: 14px;
            font-weight: 500;
            color: #333;
        }
        .message-meta {
            font-size: 11px;
            color: #666;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .message-content {
            padding: 15px;
            color: #333;
        }
        .message-content img {
            max-width: 100%;
            height: auto;
        }
        .message-content a {
            color: #007bff;
            text-decoration: none;
        }
        .message-content a:hover {
            text-decoration: underline;
        }
        .type-badge {
            background: #e9ecef;
            color: #495057;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 10px;
            text-transform: uppercase;
            font-weight: 500;
        }
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #666;
        }
        .footer {
            margin-top: 20px;
            padding: 10px;
            text-align: center;
            font-size: 10px;
            color: #999;
            border-top: 1px solid #e9ecef;
        }
    </style>
</head>
<body>
    <div class="thread-container">
        <div class="thread-header">
            <h2 class="thread-title"><?php echo htmlspecialchars($thread->getSubject()); ?></h2>
            <div class="thread-meta">
                <?php if ($thread->getLead()): ?>
                    Conversation with <?php echo htmlspecialchars($thread->getLead()->getName() ?: $thread->getLead()->getEmail()); ?>
                <?php endif; ?>
                | <?php echo count($messages); ?> message<?php echo count($messages) !== 1 ? 's' : ''; ?>
                | Started <?php echo $thread->getFirstMessageDate()->format('M j, Y'); ?>
            </div>
        </div>

        <?php if (empty($messages)): ?>
            <div class="empty-state">
                <p>No messages in this conversation yet.</p>
            </div>
        <?php else: ?>
            <?php foreach ($messages as $message): ?>
                <div class="message">
                    <div class="message-header">
                        <h3 class="message-title"><?php echo htmlspecialchars($message->getSubject()); ?></h3>
                        <div class="message-meta">
                            <span>
                                From: <strong><?php echo htmlspecialchars($message->getFromName() ?: $message->getFromEmail()); ?></strong>
                            </span>
                            <span>
                                <?php echo $message->getDateSent()->format('M j, g:i A'); ?>
                                <span class="type-badge"><?php echo htmlspecialchars($message->getEmailType()); ?></span>
                            </span>
                        </div>
                    </div>
                    <div class="message-content">
                        <?php echo $message->getContent(); ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <div class="footer">
            Powered by Mautic Email Threads | Thread: <?php echo htmlspecialchars($thread->getThreadId()); ?>
        </div>
    </div>
</body>
</html>
