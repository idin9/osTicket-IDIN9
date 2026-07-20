-- Add can_read_tickets column to api_key table.
-- Run once against your osTicket database before using the ticket read/list API endpoints.
-- Replace %TABLE_PREFIX% with your actual table prefix (default is 'ost_').

ALTER TABLE `%TABLE_PREFIX%api_key`
  ADD COLUMN `can_read_tickets` TINYINT(1) UNSIGNED NOT NULL DEFAULT '1' AFTER `can_update_tickets`;
