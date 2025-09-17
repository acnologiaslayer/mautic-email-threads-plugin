-- EmailThreads Plugin Database Tables
-- Run this script to create the required tables for the EmailThreads plugin
-- 
-- NOTE: This script uses 'mt_' prefix by default. For custom prefixes,
-- use the install_mautic6.php script which auto-detects the prefix.

-- Create email_threads table
CREATE TABLE IF NOT EXISTS `mt_EmailThread` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `thread_id` varchar(255) NOT NULL,
    `lead_id` int(11) NOT NULL,
    `subject` varchar(500) DEFAULT NULL,
    `from_email` varchar(255) DEFAULT NULL,
    `from_name` varchar(255) DEFAULT NULL,
    `first_message_date` datetime DEFAULT NULL,
    `last_message_date` datetime DEFAULT NULL,
    `is_active` tinyint(1) NOT NULL DEFAULT 1,
    `date_added` datetime NOT NULL,
    `date_modified` datetime DEFAULT NULL,
    `created_by` int(11) DEFAULT NULL,
    `modified_by` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `unique_thread_id` (`thread_id`),
    KEY `thread_id_idx` (`thread_id`),
    KEY `thread_lead_idx` (`lead_id`),
    KEY `thread_active_idx` (`is_active`),
    KEY `thread_last_message_idx` (`last_message_date`),
    KEY `thread_subject_lead_idx` (`subject`, `lead_id`),
    KEY `date_added` (`date_added`),
    KEY `date_modified` (`date_modified`),
    KEY `created_by` (`created_by`),
    KEY `modified_by` (`modified_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Create email_thread_messages table
CREATE TABLE IF NOT EXISTS `mt_EmailThreadMessage` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `thread_id` int(11) NOT NULL,
    `email_stat_id` int(11) DEFAULT NULL,
    `subject` varchar(500) DEFAULT NULL,
    `content` longtext,
    `from_email` varchar(255) DEFAULT NULL,
    `from_name` varchar(255) DEFAULT NULL,
    `date_sent` datetime DEFAULT NULL,
    `email_type` varchar(50) DEFAULT NULL,
    `date_added` datetime NOT NULL,
    `date_modified` datetime DEFAULT NULL,
    `created_by` int(11) DEFAULT NULL,
    `modified_by` int(11) DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `message_thread_idx` (`thread_id`),
    KEY `message_stat_idx` (`email_stat_id`),
    KEY `message_date_sent_idx` (`date_sent`),
    KEY `message_email_type_idx` (`email_type`),
    KEY `date_added` (`date_added`),
    KEY `date_modified` (`date_modified`),
    KEY `created_by` (`created_by`),
    KEY `modified_by` (`modified_by`),
    CONSTRAINT `FK_EmailThreadMessage_thread` FOREIGN KEY (`thread_id`) REFERENCES `mt_EmailThread` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default configuration
INSERT IGNORE INTO `mt_config` (`param`, `value`) VALUES
('emailthreads_enabled', '1'),
('emailthreads_domain', ''),
('emailthreads_auto_thread', '1'),
('emailthreads_thread_lifetime', '30'),
('emailthreads_include_unsubscribe', '1'),
('emailthreads_inject_previous_messages', '1');
