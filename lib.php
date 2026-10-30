<?php
// SPDX-License-Identifier: GPL-3.0-or-later
defined('MOODLE_INTERNAL') || die();
function auth_eudiwallet_extend_navigation_user_settings($navigation, $user, $usercontext, $course, $coursecontext) {
    global $USER;
    if (is_enabled_auth('eudiwallet') && $user->id == $USER->id && $USER->auth === 'manual') {
        $navigation->add(get_string('link', 'auth_eudiwallet'), new moodle_url('/auth/eudiwallet/link.php'),
            navigation_node::TYPE_SETTING, null, 'eudiwallet');
    }
}
