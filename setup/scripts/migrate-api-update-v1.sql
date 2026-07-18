-- Add staff_id and can_update_tickets columns to api_key table.
-- Run once against your osTicket database before using the API update/reply endpoints.
-- Replace %TABLE_PREFIX% with your actual table prefix (default is 'ost_').

ALTER TABLE `%TABLE_PREFIX%api_key`
  ADD COLUMN `can_update_tickets` TINYINT(1) UNSIGNED NOT NULL DEFAULT '1' AFTER `can_exec_cron`,
  ADD COLUMN `staff_id` int(10) unsigned NOT NULL DEFAULT '0' AFTER `can_update_tickets`;
