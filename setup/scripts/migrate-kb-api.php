<?php
/*********************************************************************
    migrate-kb-api.php

    One-time migration to add KB API permissions to existing
    api_key table. Safe to run multiple times (idempotent).

    Run from the project root:
        php setup/scripts/migrate-kb-api.php
**********************************************************************/
declare(strict_types=1);

chdir(dirname(__FILE__) . '/../..');
require 'bootstrap.php';

$columns = array(
    'can_read_faq'   => "TINYINT(1) UNSIGNED NOT NULL DEFAULT '0'",
    'can_manage_faq' => "TINYINT(1) UNSIGNED NOT NULL DEFAULT '0'",
);

foreach ($columns as $col => $def) {
    $check = db_query(
        "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
         WHERE table_name = '" . API_KEY_TABLE . "'
         AND table_schema = DATABASE()
         AND column_name = '" . $col . "'"
    );
    list($exists) = db_fetch_row($check);

    if (!$exists) {
        echo "Adding column `$col` to `" . API_KEY_TABLE . "`...\n";
        $sql = "ALTER TABLE `" . API_KEY_TABLE . "`
                ADD COLUMN `$col` $def
                AFTER `can_read_tickets`";
        if (db_query($sql)) {
            echo "  Done.\n";
        } else {
            echo "  ERROR: Failed to add column.\n";
        }
    } else {
        echo "Column `$col` already exists in `" . API_KEY_TABLE . "`. Skipping.\n";
    }
}

echo "\nKB API migration complete.\n";
