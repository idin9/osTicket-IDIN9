/**
 * @version v1.18.4.07
 * @signature e7a8c9d0f1b2a3c4d5e6f708192a3b4c
 * @title Add enable_ip_filter column to api_key table
 *
 */

ALTER TABLE `%TABLE_PREFIX%api_key`
    ADD COLUMN `enable_ip_filter` TINYINT(1) UNSIGNED NOT NULL DEFAULT '1' AFTER `ipaddr`;

UPDATE `%TABLE_PREFIX%config`
    SET `value` = 'e7a8c9d0f1b2a3c4d5e6f708192a3b4c', updated = NOW()
    WHERE `key` = 'schema_signature' AND `namespace` = 'core';
