<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace auth_eudiwallet\local;
defined('MOODLE_INTERNAL') || die();

/** Thin web entry points. Never disclose provider responses or tokens. */
final class pages {
    public static function headers(): void {
        header('Cache-Control: no-store');
        header('Referrer-Policy: no-referrer');
        if (!is_enabled_auth('eudiwallet')) { throw new \moodle_exception('disabled', 'auth_eudiwallet'); }
    }
    public static function begin(int $linkuser = 0): void {
        global $CFG;
        self::headers();
        require_sesskey();
        try {
            if (!$linkuser && isloggedin() && !isguestuser()) { throw new \RuntimeException('account'); }
            $s = configuration::get();
            $oidc = new oidc($s);
            $metadata = $oidc->discovery();
            $tx = transactions::create($s, $linkuser);
            $url = $oidc->authorization($tx, $metadata, configuration::callback());
        } catch (\Throwable $e) { self::failure(); return; }
        // All provider endpoints are constrained to the configured HTTPS issuer.
        \core\session\manager::write_close();
        header('Location: ' . $url, true, 303);
        exit;
    }
    public static function parameter(string $name, int $max): string {
        $value = optional_param($name, '', PARAM_RAW);
        if (!is_string($value) || strlen($value) > $max || !preg_match('/^[\x20-\x7e]*$/D', $value)) {
            throw new \RuntimeException('state');
        }
        return $value;
    }
    public static function callback(): void {
        global $USER, $CFG;
        self::headers();
        $step = 'configuration';
        try {
            $s = configuration::get();
            $step = 'state';
            $tx = transactions::take(self::parameter('state', 128));
            if (!hash_equals($tx['settings'], configuration::fingerprint($s))) { throw new \RuntimeException('configuration'); }
            if (self::parameter('error', 128) !== '') { throw new \RuntimeException('cancelled'); }
            $link = (int)$tx['link_user'];
            if ($link && (!isloggedin() || isguestuser() || $USER->id != $link ||
                !hash_equals($tx['session'], hash('sha256', sesskey() . '|' . session_id())))) { throw new \RuntimeException('account'); }
            if (!$link && isloggedin() && !isguestuser()) { throw new \RuntimeException('account'); }
            $code = self::parameter('code', 4096);
            if ($code === '') { throw new \RuntimeException('state'); }
            $step = 'exchange';
            $claims = (new oidc($s))->exchange($code, $tx, configuration::callback());
            $step = 'account';
            $user = accounts::resolve($claims, $s, $link);
        } catch (\Throwable $e) {
            // Log only fixed diagnostic codes. Never log exceptions or provider values.
            $known = ['configuration', 'state', 'cancelled', 'account', 'registration', 'linkrequired',
                'busy', 'discovery', 'service', 'token', 'subject', 'attributes', 'email', 'signature', 'claims', 'profile'];
            $reason = in_array($e->getMessage(), $known, true) ? $e->getMessage() : 'unexpected';
            $category = $e instanceof \dml_exception ? 'database' :
                ($e instanceof \moodle_exception ? 'moodle' :
                ($e instanceof \RuntimeException ? 'runtime' : 'other'));
            error_log('[auth_eudiwallet] callback step=' . $step . ' reason=' . $reason . ' category=' . $category);
            self::failure($reason);
            return;
        }
        if ($link) { redirect(new \moodle_url('/user/preferences.php'), get_string('linked', 'auth_eudiwallet')); }
        complete_user_login($user);
        redirect(new \moodle_url('/my/'));
    }
    public static function link(): void {
        global $CFG, $PAGE, $OUTPUT, $USER;
        require_login();
        self::headers();
        if (isguestuser() || $USER->auth !== 'manual') { self::failure('linkmanual'); return; }
        $PAGE->set_url(new \moodle_url('/auth/eudiwallet/link.php'));
        $PAGE->set_context(\context_user::instance($USER->id));
        $PAGE->set_title(get_string('link', 'auth_eudiwallet'));
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require_sesskey();
            // Fresh web requests do not preload the authentication constants.
            require_once($CFG->libdir . '/authlib.php');
            $password = required_param('password', PARAM_RAW);
            $error = AUTH_LOGIN_FAILED;
            $checked = authenticate_user_login($USER->username, $password, false, $error, false);
            if (!$checked || $checked->id != $USER->id) { self::failure(); return; }
            self::begin((int)$USER->id);
        }
        echo $OUTPUT->header();
        echo $OUTPUT->heading(get_string('link', 'auth_eudiwallet'));
        echo \html_writer::tag('p', get_string('linkhelp', 'auth_eudiwallet'));
        echo \html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url->out(false)]);
        echo \html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
        echo \html_writer::tag('label', get_string('password'), ['for' => 'wallet-password']);
        echo \html_writer::empty_tag('input', ['id' => 'wallet-password', 'name' => 'password', 'type' => 'password',
            'required' => 'required', 'autocomplete' => 'current-password', 'class' => 'form-control']);
        echo \html_writer::tag('button', get_string('login', 'auth_eudiwallet'), ['type' => 'submit', 'class' => 'btn btn-primary mt-3']);
        echo \html_writer::end_tag('form');
        echo $OUTPUT->footer();
    }
    public static function failure(string $code = ''): void {
        global $PAGE, $OUTPUT;
        $key = in_array($code, ['cancelled', 'registration', 'linkrequired', 'linkmanual', 'busy'], true) ? $code : 'failed';
        $PAGE->set_context(\context_system::instance());
        $PAGE->set_url(new \moodle_url('/auth/eudiwallet/start.php'));
        $PAGE->set_title(get_string('pluginname', 'auth_eudiwallet'));
        http_response_code(400);
        echo $OUTPUT->header();
        echo $OUTPUT->notification(get_string($key, 'auth_eudiwallet'), 'error');
        echo \html_writer::link(new \moodle_url('/login/index.php'), get_string('returnlogin', 'auth_eudiwallet'));
        echo $OUTPUT->footer();
    }
}
