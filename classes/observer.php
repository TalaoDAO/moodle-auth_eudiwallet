<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace auth_eudiwallet;
defined('MOODLE_INTERNAL') || die();
final class observer {
    public static function user_deleted(\core\event\user_deleted $event): void {
        global $DB;
        $DB->delete_records('auth_eudiwallet_ids', ['userid' => $event->objectid]);
        $DB->delete_records('auth_eudiwallet_tx', ['userid' => $event->objectid]);
    }
}
