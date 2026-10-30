<?php
// SPDX-License-Identifier: GPL-3.0-or-later
defined('MOODLE_INTERNAL') || die();
if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_heading('auth_eudiwallet/setup', '',
        html_writer::link(new moodle_url('/auth/eudiwallet/setup.php'), get_string('setup', 'auth_eudiwallet')) .
        html_writer::tag('p', get_string('publisher', 'auth_eudiwallet') . ' ' .
            html_writer::link('https://talao.io', 'Web3 Digital Wallet (Talao)')) .
        html_writer::tag('p', html_writer::link('https://openid4vc-hub.com/api/docs/bridge.html#89-moodle-setup',
            get_string('documentation', 'auth_eudiwallet')) . ' · ' .
            html_writer::link('mailto:contact@talao.io', get_string('contact', 'auth_eudiwallet')))));
}
