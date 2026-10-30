<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace auth_eudiwallet\privacy;
defined('MOODLE_INTERNAL') || die();

class provider implements \core_privacy\local\metadata\provider,
        \core_privacy\local\request\core_user_data_provider,
        \core_privacy\local\request\core_userlist_provider {
    public static function get_metadata(\core_privacy\local\metadata\collection $collection): \core_privacy\local\metadata\collection {
        $collection->add_database_table('auth_eudiwallet_ids', [
            'userid' => 'privacy:userid', 'identityhash' => 'privacy:identity', 'timecreated' => 'privacy:created'], 'privacy:bindings');
        $collection->add_database_table('auth_eudiwallet_tx', [
            'userid' => 'privacy:userid', 'statehash' => 'privacy:transaction', 'browserhash' => 'privacy:browser',
            'payload' => 'privacy:payload', 'expires' => 'privacy:expires'], 'privacy:transactions');
        $collection->add_external_location_link('bridge', [
            'client_id' => 'privacy:client', 'redirect_uri' => 'privacy:callback', 'scope' => 'privacy:scope',
            'protocol' => 'privacy:protocol'], 'privacy:bridge');
        return $collection;
    }
    public static function get_contexts_for_userid(int $userid): \core_privacy\local\request\contextlist {
        global $DB;
        $list = new \core_privacy\local\request\contextlist();
        if ($DB->record_exists('auth_eudiwallet_ids', ['userid' => $userid]) || $DB->record_exists('auth_eudiwallet_tx', ['userid' => $userid])) {
            $list->add_system_context();
        }
        return $list;
    }
    public static function export_user_data(\core_privacy\local\request\approved_contextlist $list) {
        global $DB;
        if (!in_array(\context_system::instance()->id, $list->get_contextids())) { return; }
        $id = $list->get_user()->id;
        $bindings = array_values($DB->get_records('auth_eudiwallet_ids', ['userid' => $id], '', 'identityhash,timecreated'));
        $pending = array_values($DB->get_records('auth_eudiwallet_tx', ['userid' => $id], '', 'id,expires'));
        \core_privacy\local\request\writer::with_context(\context_system::instance())->export_data(
            [get_string('pluginname', 'auth_eudiwallet')], (object)['bindings' => $bindings, 'pending' => $pending]);
    }
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;
        if ($context->contextlevel !== CONTEXT_SYSTEM) { return; }
        $DB->delete_records('auth_eudiwallet_ids');
        $DB->delete_records('auth_eudiwallet_tx');
    }
    public static function delete_data_for_user(\core_privacy\local\request\approved_contextlist $list) {
        global $DB;
        if (!in_array(\context_system::instance()->id, $list->get_contextids())) { return; }
        $DB->delete_records('auth_eudiwallet_ids', ['userid' => $list->get_user()->id]);
        $DB->delete_records('auth_eudiwallet_tx', ['userid' => $list->get_user()->id]);
    }
    public static function get_users_in_context(\core_privacy\local\request\userlist $list) {
        if ($list->get_context()->contextlevel !== CONTEXT_SYSTEM) { return; }
        $list->add_from_sql('userid', 'SELECT userid FROM {auth_eudiwallet_ids}', []);
        $list->add_from_sql('userid', 'SELECT userid FROM {auth_eudiwallet_tx} WHERE userid IS NOT NULL', []);
    }
    public static function delete_data_for_users(\core_privacy\local\request\approved_userlist $list) {
        global $DB;
        if ($list->get_context()->contextlevel !== CONTEXT_SYSTEM || !$list->get_userids()) { return; }
        $DB->delete_records_list('auth_eudiwallet_ids', 'userid', $list->get_userids());
        $DB->delete_records_list('auth_eudiwallet_tx', 'userid', $list->get_userids());
    }
}
