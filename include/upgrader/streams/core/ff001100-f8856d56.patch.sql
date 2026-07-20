/**
 * @signature ff001100000000000000000000000001
 * @version v1.18.4.13
 * @title Add can_read_tickets column to api_key table
 *
 * This patch adds the `can_read_tickets` column to allow
 * separate read-only API key permissions (distinct from can_update_tickets).
 */

-- Add can_read_tickets column (after can_update_tickets, before staff_id)
SET @s = (SELECT IF(
    (SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE table_name = '%TABLE_PREFIX%api_key'
        AND table_schema = DATABASE()
        AND column_name = 'can_read_tickets'
    ) > 0,
    "SELECT 1",
    "ALTER TABLE `%TABLE_PREFIX%api_key`
        ADD COLUMN `can_read_tickets` TINYINT(1) UNSIGNED NOT NULL DEFAULT '1'
        AFTER `can_update_tickets`"
));
PREPARE stmt FROM @s;
EXECUTE stmt;

-- Finished with patch
UPDATE `%TABLE_PREFIX%config`
    SET `value` = 'ff001100000000000000000000000001'
    WHERE `key` = 'schema_signature' AND `namespace` = 'core';
