<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace auth_eudiwallet\local;
defined('MOODLE_INTERNAL') || die();

final class transactions {
    private const COOKIE = '__Host-eudi_moodle';
    public static function browser(bool $create = false): string {
        $value = $_COOKIE[self::COOKIE] ?? '';
        if (is_string($value) && preg_match('/^[A-Za-z0-9_-]{43}$/D', $value)) { return $value; }
        if (!$create) { throw new \RuntimeException('state'); }
        $value = oidc::b64(random_bytes(32));
        setcookie(self::COOKIE, $value, ['expires' => 0, 'path' => '/', 'secure' => true,
            'httponly' => true, 'samesite' => 'Lax']);
        return $value;
    }
    public static function create(array $settings, int $userid = 0): array {
        global $DB;
        self::cleanup();
        $browser = hash('sha256', self::browser(true));
        $lock = self::lock('browser-' . $browser);
        try {
            if ($DB->count_records('auth_eudiwallet_tx', ['browserhash' => $browser]) >= 3) { throw new \RuntimeException('busy'); }
            $tx = ['state' => oidc::b64(random_bytes(32)), 'nonce' => oidc::b64(random_bytes(32)),
                'verifier' => oidc::b64(random_bytes(48)), 'created' => time(), 'expires' => time() + 900,
                'settings' => configuration::fingerprint($settings), 'link_user' => $userid,
                'session' => $userid ? hash('sha256', sesskey() . '|' . session_id()) : ''];
            $DB->insert_record('auth_eudiwallet_tx', (object)['statehash' => hash('sha256', $tx['state']),
                'browserhash' => $browser, 'userid' => $userid ?: null,
                'payload' => \core\encryption::encrypt(json_encode($tx, JSON_THROW_ON_ERROR)), 'expires' => $tx['expires']]);
            return $tx;
        } finally { $lock->release(); }
    }
    public static function lock(string $name): \core\lock\lock {
        $lock = \core\lock\lock_config::get_lock_factory('auth_eudiwallet')->get_lock($name, 5);
        if (!$lock) { throw new \RuntimeException('busy'); }
        return $lock;
    }
    public static function take(string $state): array {
        global $DB;
        if (!preg_match('/^[A-Za-z0-9_-]{43}$/D', $state)) { throw new \RuntimeException('state'); }
        $hash = hash('sha256', $state);
        $lock = self::lock('state-' . $hash);
        try {
            $row = $DB->get_record('auth_eudiwallet_tx', ['statehash' => $hash]);
            if (!$row || $row->expires <= time() || !hash_equals($row->browserhash, hash('sha256', self::browser()))) {
                throw new \RuntimeException('state');
            }
            $tx = json_decode(\core\encryption::decrypt($row->payload), true, 32, JSON_THROW_ON_ERROR);
            $DB->delete_records('auth_eudiwallet_tx', ['id' => $row->id]);
            return $tx;
        } finally { $lock->release(); }
    }
    public static function cleanup(): void {
        global $DB;
        $DB->delete_records_select('auth_eudiwallet_tx', 'expires <= ?', [time()]);
    }
}
