<?php
// SPDX-License-Identifier: GPL-3.0-or-later
defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/authlib.php');

/** Wallet authentication; local passwords never authenticate Wallet-only accounts. */
class auth_plugin_eudiwallet extends auth_plugin_base {
    public function __construct() {
        $this->authtype = 'eudiwallet';
        $this->config = get_config('auth_eudiwallet');
    }
    public function user_login($username, $password) { return false; }
    public function is_internal() { return false; }
    public function prevent_local_passwords() { return true; }
    public function can_change_password() { return false; }
    public function loginpage_idp_list($wantsurl) {
        if (!is_enabled_auth('eudiwallet') || !\auth_eudiwallet\local\configuration::configured()) { return []; }
        return [[
            'url' => new moodle_url('/auth/eudiwallet/start.php', ['sesskey' => sesskey()]),
            'name' => get_string('login', 'auth_eudiwallet'),
            'icon' => new pix_icon('wallet', '', 'auth_eudiwallet'),
        ]];
    }
}
