<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_login();
require_capability('moodle/site:config', context_system::instance());
$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/auth/eudiwallet/setup.php'));
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('setup', 'auth_eudiwallet'));
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_sesskey();
    try {
        $raw = optional_param('configuration', '', PARAM_RAW);
        if (strlen($raw) > 16384) { throw new RuntimeException('configuration'); }
        if ($raw !== '') {
            $data = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
            if (!is_array($data)) { throw new RuntimeException('configuration'); }
            $settings = \auth_eudiwallet\local\configuration::imported($data);
        } else { $settings = \auth_eudiwallet\local\configuration::get(); }
        \auth_eudiwallet\local\configuration::save($settings, optional_param('createusers', 0, PARAM_BOOL));
        redirect(new moodle_url('/auth/eudiwallet/setup.php'), get_string('saved', 'auth_eudiwallet'));
    } catch (Throwable $e) { $error = get_string('invalidconfig', 'auth_eudiwallet'); }
}
$PAGE->requires->js(new moodle_url('/auth/eudiwallet/assets/setup.js'));
echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'auth_eudiwallet'));
if ($error) { echo $OUTPUT->notification($error, 'error'); }
$saved = \auth_eudiwallet\local\configuration::saved();
$configured = !empty($saved->clientid);
if ($configured) {
    echo $OUTPUT->notification(get_string('configured', 'auth_eudiwallet'), 'success');
    $enabled = is_enabled_auth('eudiwallet');
    echo html_writer::tag('p', get_string($enabled ? 'authenabled' : 'authdisabled', 'auth_eudiwallet'));
    echo html_writer::tag('p', html_writer::link(new moodle_url('/admin/settings.php', ['section' => 'manageauths']),
        get_string('manageauth', 'auth_eudiwallet'), ['class' => 'btn btn-primary']));
    echo html_writer::tag('p', get_string('testhelp', 'auth_eudiwallet'));
    echo html_writer::tag('p', html_writer::link(new moodle_url('/login/index.php'),
        get_string('loginpage', 'auth_eudiwallet')));
} else {
    echo html_writer::tag('p', get_string('setuphelp', 'auth_eudiwallet'));
}
echo html_writer::tag('p', get_string('publisher', 'auth_eudiwallet') . ' ' .
    html_writer::link('https://talao.io', 'Web3 Digital Wallet (Talao)'));
echo html_writer::tag('p', html_writer::link('https://openid4vc-hub.com/api/docs/bridge.html#89-moodle-setup', get_string('documentation', 'auth_eudiwallet')) . ' · ' .
    html_writer::link('mailto:contact@talao.io', get_string('contact', 'auth_eudiwallet')));
if ($configured) {
    echo html_writer::start_tag('details', ['id' => 'wallet-registration']);
    echo html_writer::tag('summary', get_string('registrationdetails', 'auth_eudiwallet'));
}
echo html_writer::tag('h3', get_string('callback', 'auth_eudiwallet'));
echo html_writer::tag('code', s(\auth_eudiwallet\local\configuration::callback()));
echo html_writer::tag('p', html_writer::link('https://login.openid4vc-hub.com/register?purpose=pid', get_string('register', 'auth_eudiwallet'),
    ['target' => '_blank', 'rel' => 'noopener noreferrer', 'class' => 'btn btn-secondary']));
if ($configured) { echo html_writer::end_tag('details'); }
if ($configured) {
    echo html_writer::tag('h3', get_string('connection', 'auth_eudiwallet'));
    echo html_writer::tag('p', s($saved->issuer) . ' — ' . s($saved->clientid));
}
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false), 'id' => 'wallet-setup',
    'data-callback' => \auth_eudiwallet\local\configuration::callback(), 'data-configured' => !empty($saved->clientid) ? '1' : '0']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'configuration', 'id' => 'wallet-configuration', 'autocomplete' => 'off']);
if ($configured) {
    echo html_writer::start_tag('details', ['id' => 'wallet-replace']);
    echo html_writer::tag('summary', get_string('replaceconfig', 'auth_eudiwallet'));
    echo html_writer::tag('p', get_string('replacehelp', 'auth_eudiwallet'));
}
echo html_writer::tag('label', get_string('import', 'auth_eudiwallet'), ['for' => 'wallet-file']);
echo html_writer::empty_tag('input', ['type' => 'file', 'id' => 'wallet-file', 'accept' => '.json,application/json']);
echo html_writer::tag('p', '', ['id' => 'wallet-status', 'role' => 'status', 'data-success' => get_string('ready', 'auth_eudiwallet'), 'data-error' => get_string('invalidconfig', 'auth_eudiwallet')]);
echo html_writer::tag('pre', '', ['id' => 'wallet-review']);
if ($configured) { echo html_writer::end_tag('details'); }
echo html_writer::tag('h3', get_string('accountsettings', 'auth_eudiwallet'));
echo html_writer::start_tag('label');
echo html_writer::empty_tag('input', ['type' => 'checkbox', 'name' => 'createusers', 'value' => '1'] + (!empty($saved->createusers) ? ['checked' => 'checked'] : []));
echo ' ' . get_string('createusers', 'auth_eudiwallet');
echo html_writer::end_tag('label');
echo html_writer::tag('p', html_writer::tag('button', get_string('save'), ['type' => 'submit', 'id' => 'wallet-save', 'class' => 'btn btn-primary'] +
    (!empty($saved->clientid) ? [] : ['disabled' => 'disabled'])));
echo html_writer::end_tag('form');
echo $OUTPUT->footer();
