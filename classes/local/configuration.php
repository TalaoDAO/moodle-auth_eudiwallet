<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace auth_eudiwallet\local;
defined('MOODLE_INTERNAL') || die();

final class configuration {
    public static function callback(): string {
        global $CFG;
        return $CFG->wwwroot . '/auth/eudiwallet/callback.php';
    }
    public static function configured(): bool {
        $s = self::saved();
        return !empty($s->clientid) && !empty($s->secret) && !empty($s->issuer);
    }
    public static function saved(): \stdClass {
        $data = json_decode(get_config('auth_eudiwallet', 'connection') ?: '{}', true);
        return (object)(is_array($data) ? $data : []);
    }
    public static function get(): array {
        global $CFG;
        $s = self::saved();
        if (!self::configured() || strpos($CFG->wwwroot, 'https://') !== 0) { throw new \RuntimeException('configuration'); }
        return ['issuer' => oidc::issuer($s->issuer), 'client_id' => $s->clientid,
            'client_secret' => \core\encryption::decrypt($s->secret), 'auth_method' => $s->method,
            'create_users' => !empty($s->createusers)];
    }
    public static function fingerprint(array $s): string {
        return hash('sha256', json_encode($s, JSON_THROW_ON_ERROR));
    }
    /** Validate the exact exported registration contract; never infer a new callback. */
    public static function imported(array $data): array {
        $issuer = oidc::issuer($data['issuer'] ?? '');
        if (($data['verification_purpose'] ?? null) !== 'identity' ||
            ($data['redirect_uris'] ?? null) !== [self::callback()] ||
            ($data['response_type'] ?? null) !== 'code' || ($data['code_challenge_method'] ?? null) !== 'S256' ||
            !is_string($data['client_id'] ?? null) || !preg_match('/^[A-Za-z0-9._-]{1,128}$/D', $data['client_id']) ||
            !is_string($data['client_secret'] ?? null) || strlen($data['client_secret']) < 16 || strlen($data['client_secret']) > 4096 ||
            !in_array($data['token_endpoint_auth_method'] ?? '', ['client_secret_basic', 'client_secret_post'], true) ||
            !is_string($data['scope'] ?? null)) { throw new \RuntimeException('configuration'); }
        $scopes = preg_split('/\s+/', trim($data['scope']));
        sort($scopes);
        if ($scopes !== ['email', 'openid', 'profile']) { throw new \RuntimeException('configuration'); }
        return ['issuer' => $issuer, 'client_id' => $data['client_id'], 'client_secret' => $data['client_secret'],
            'auth_method' => $data['token_endpoint_auth_method']];
    }
    public static function save(array $s, bool $createusers): void {
        // One setting keeps both the database and Moodle's config cache atomic.
        $lock = transactions::lock('configuration');
        try {
            set_config('connection', json_encode(['issuer' => $s['issuer'], 'clientid' => $s['client_id'],
                'secret' => \core\encryption::encrypt($s['client_secret']), 'method' => $s['auth_method'],
                'createusers' => $createusers ? 1 : 0], JSON_THROW_ON_ERROR), 'auth_eudiwallet');
        } finally { $lock->release(); }
    }
}
