<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace auth_eudiwallet\local;
defined('MOODLE_INTERNAL') || die();

final class accounts {
    public static function active(int $id): \stdClass {
        global $CFG;
        // Web callbacks do not bootstrap authlib as CLI scripts do.
        require_once($CFG->libdir . '/authlib.php');
        $user = get_complete_user_data('id', $id);
        if (!$user || $user->deleted || $user->suspended || !$user->confirmed || isguestuser($user) ||
            $user->auth === 'nologin' || !is_enabled_auth($user->auth) || login_is_lockedout($user) ||
            is_mnet_remote_user($user)) { throw new \RuntimeException('account'); }
        return $user;
    }
    public static function resolve(array $claims, array $s, int $linkuser = 0): \stdClass {
        global $DB, $CFG;
        $hash = hash('sha256', json_encode([$s['issuer'], $claims['sub']], JSON_THROW_ON_ERROR));
        // Serialize cross-subject registrations too: Moodle may allow duplicate emails.
        $lock = transactions::lock('account-writes');
        try {
            $existing = $DB->get_record('auth_eudiwallet_ids', ['identityhash' => $hash]);
            if ($existing) {
                if ($linkuser && $existing->userid != $linkuser) { throw new \RuntimeException('account'); }
                return self::active((int)$existing->userid);
            }
            if ($linkuser) {
                $user = self::active($linkuser);
            } else {
                if (!$s['create_users'] || !empty($CFG->authpreventaccountcreation)) { throw new \RuntimeException('registration'); }
                if ($DB->record_exists('user', ['email' => $claims['email'], 'deleted' => 0])) { throw new \RuntimeException('linkrequired'); }
                require_once($CFG->dirroot . '/user/lib.php');
                $new = (object)['auth' => 'eudiwallet', 'username' => 'eudi_' . bin2hex(random_bytes(16)),
                    'password' => AUTH_PASSWORD_NOT_CACHED, 'mnethostid' => $CFG->mnet_localhost_id,
                    'firstname' => self::name($claims['given_name'] ?? ''),
                    'lastname' => self::name($claims['family_name'] ?? ''), 'email' => $claims['email'],
                    'confirmed' => 1, 'lang' => current_language()];
                if ($new->firstname === '' || $new->lastname === '') { throw new \RuntimeException('profile'); }
            }
            $tx = $DB->start_delegated_transaction();
            try {
                if (!$linkuser) { $user = self::active(user_create_user($new, false)); }
                $DB->insert_record('auth_eudiwallet_ids', (object)['identityhash' => $hash, 'userid' => $user->id, 'timecreated' => time()]);
                $tx->allow_commit();
            } catch (\Throwable $e) { $tx->rollback($e); }
            return $user;
        } finally { $lock->release(); }
    }
    private static function name($value): string {
        return is_string($value) ? trim(clean_param(\core_text::substr($value, 0, 100), PARAM_NOTAGS)) : '';
    }
}
