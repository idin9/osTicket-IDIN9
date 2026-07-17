/**
 * @version v1.18.4.01
 * @signature f6fd07ef27443f635275fe408f34c19d
 * @title Add staff_id and can_update_tickets to api_key table
 *
 */

ALTER TABLE `%TABLE_PREFIX%api_key`
    ADD COLUMN `can_update_tickets` TINYINT(1) UNSIGNED NOT NULL DEFAULT '1' AFTER `can_exec_cron`,
    ADD COLUMN `staff_id` int(10) unsigned NOT NULL DEFAULT '0' AFTER `can_update_tickets`;

UPDATE `%TABLE_PREFIX%config`
    SET `value` = 'f6fd07ef27443f635275fe408f34c19d', updated = NOW()
    WHERE `key` = 'schema_signature' AND `namespace` = 'core';
