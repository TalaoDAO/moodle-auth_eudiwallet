<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace auth_eudiwallet\local;
defined('MOODLE_INTERNAL') || die();
require_once(dirname(__DIR__, 2) . '/vendor/autoload.php');

use auth_eudiwallet\vendor\jwt\JWK;
use auth_eudiwallet\vendor\jwt\JWT;
use RuntimeException;

/** OIDC relying party. No dependency on Hub or bridge runtime code. */
final class oidc
{
    public static function b64(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    public static function issuer(string $url): string
    {
        if (strlen($url) > 2048 || str_contains($url, '\\') || preg_match('/[\x00-\x20\x7f]/', $url)) { throw new RuntimeException('configuration'); }
        $parts = parse_url($url);
        if (!$parts || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) ||
            isset($parts['user'], $parts['pass']) || isset($parts['user']) || isset($parts['query']) || isset($parts['fragment']) ||
            !empty($parts['path']) || (isset($parts['port']) && $parts['port'] !== 443)) {
            throw new RuntimeException('configuration');
        }
        return $url;
    }

    public function __construct(private array $settings, private ?\Closure $transport = null) {}

    private function request(string $url, array $args = []): array
    {
        $issuer = self::issuer($this->settings['issuer']);
        // This bridge profile publishes endpoints under its own HTTPS origin.
        if (!str_starts_with($url, $issuer . '/')) { throw new RuntimeException('discovery'); }
        $args = array_replace(['method' => 'GET', 'headers' => ['Accept' => 'application/json']], $args);
        $response = $this->transport ? ($this->transport)($url, $args) : http::request($url, $args);
        if (($response['status'] ?? null) !== 200 || !is_string($response['body'] ?? null) || strlen($response['body']) > 524288) {
            throw new RuntimeException('service');
        }
        $result = json_decode($response['body'], true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($result) || array_is_list($result)) { throw new RuntimeException('service'); }
        return $result;
    }

    public function discovery(): array
    {
        $issuer = self::issuer($this->settings['issuer']);
        $data = $this->request($issuer . '/.well-known/openid-configuration');
        if (($data['issuer'] ?? null) !== $issuer ||
            !in_array('S256', $data['code_challenge_methods_supported'] ?? [], true) ||
            !in_array('RS256', $data['id_token_signing_alg_values_supported'] ?? [], true) ||
            !in_array('code', $data['response_types_supported'] ?? [], true) ||
            !in_array($this->settings['auth_method'], $data['token_endpoint_auth_methods_supported'] ?? [], true)) {
            throw new RuntimeException('discovery');
        }
        foreach (['authorization_endpoint', 'token_endpoint', 'userinfo_endpoint', 'jwks_uri'] as $key) {
            if (!is_string($data[$key] ?? null) || !str_starts_with($data[$key], $issuer . '/') ||
                str_contains($data[$key], '#') || str_contains($data[$key], '\\') ||
                preg_match('/[\x00-\x20\x7f]/', $data[$key])) {
                throw new RuntimeException('discovery');
            }
        }
        return $data;
    }

    public function authorization(array $tx, array $metadata, string $callback): string
    {
        return (new \moodle_url($metadata['authorization_endpoint'], [
            'client_id' => $this->settings['client_id'], 'redirect_uri' => $callback,
            'response_type' => 'code', 'response_mode' => 'query',
            'scope' => 'openid profile email',
            'state' => $tx['state'], 'nonce' => $tx['nonce'],
            'code_challenge' => self::b64(hash('sha256', $tx['verifier'], true)),
            'code_challenge_method' => 'S256', 'ui_locales' => str_replace('_', '-', current_language()),
        ]))->out(false);
    }

    public function exchange(string $code, array $tx, string $callback): array
    {
        $metadata = $this->discovery();
        $body = ['grant_type' => 'authorization_code', 'code' => $code,
            'redirect_uri' => $callback, 'code_verifier' => $tx['verifier']];
        $headers = ['Accept' => 'application/json'];
        if ($this->settings['auth_method'] === 'client_secret_basic') {
            $headers['Authorization'] = 'Basic ' . base64_encode(urlencode($this->settings['client_id']) . ':' . urlencode($this->settings['client_secret']));
        } else {
            $body['client_id'] = $this->settings['client_id'];
            if ($this->settings['auth_method'] === 'client_secret_post') {
                $body['client_secret'] = $this->settings['client_secret'];
            }
        }
        $tokens = $this->request($metadata['token_endpoint'], ['method' => 'POST', 'headers' => $headers, 'body' => $body]);
        if (!is_string($tokens['access_token'] ?? null) || strlen($tokens['access_token']) > 8192 ||
            !preg_match('/^[\x21-\x7E]+$/D', $tokens['access_token']) ||
            strcasecmp($tokens['token_type'] ?? '', 'Bearer') !== 0 || !is_string($tokens['id_token'] ?? null)) {
            throw new RuntimeException('token');
        }
        $claims = $this->validate($tokens['id_token'], $this->request($metadata['jwks_uri']), $tx, $tokens['access_token']);
        $userinfo = $this->request($metadata['userinfo_endpoint'], ['headers' => [
            'Accept' => 'application/json', 'Authorization' => 'Bearer ' . $tokens['access_token']]]);
        if (($userinfo['sub'] ?? null) !== $claims['sub']) { throw new RuntimeException('subject'); }
        // Only signed and authorized attributes enter the account projection.
        foreach (['given_name', 'family_name', 'email', 'email_verified', 'age_over_18'] as $field) {
            if (array_key_exists($field, $claims) && ($userinfo[$field] ?? null) !== $claims[$field]) {
                throw new RuntimeException('attributes');
            }
        }
        if (!is_string($claims['email'] ?? null) || !validate_email($claims['email']) ||
            ($claims['email_verified'] ?? null) !== true || isset($claims['age_over_18'])) {
            throw new RuntimeException('email');
        }
        return array_intersect_key($claims, array_flip(['sub', 'given_name', 'family_name', 'email', 'email_verified']));
    }

    public function validate(string $token, array $jwks, array $tx, string $access): array
    {
        if (strlen($token) > 65536) { throw new RuntimeException('token'); }
        // Explicit alg and key filtering prevents algorithm/key-type confusion.
        $keys = [];
        foreach ($jwks['keys'] ?? [] as $key) {
            if (is_array($key) && ($key['kty'] ?? '') === 'RSA' &&
                ($key['use'] ?? 'sig') === 'sig' && ($key['alg'] ?? 'RS256') === 'RS256' &&
                (!isset($key['key_ops']) || in_array('verify', $key['key_ops'], true)) && !isset($key['d'])) {
                $keys[] = $key;
            }
        }
        if (!$keys) { throw new RuntimeException('signature'); }
        JWT::$leeway = 30;
        try {
            $claims = (array) JWT::decode($token, JWK::parseKeySet(['keys' => $keys], 'RS256'));
        } finally {
            JWT::$leeway = 0;
        }
        $now = time();
        if (($claims['iss'] ?? null) !== $this->settings['issuer'] ||
            ($claims['aud'] ?? null) !== $this->settings['client_id'] ||
            (isset($claims['azp']) && $claims['azp'] !== $this->settings['client_id']) ||
            !is_string($claims['sub'] ?? null) || $claims['sub'] === '' || strlen($claims['sub']) > 255 ||
            !is_int($claims['exp'] ?? null) || $claims['exp'] < $now - 30 ||
            !is_int($claims['iat'] ?? null) || $claims['iat'] > $now + 30 ||
            $claims['iat'] < $tx['created'] - 30 || $claims['exp'] <= $claims['iat'] ||
            !is_string($claims['nonce'] ?? null) || !hash_equals($tx['nonce'], $claims['nonce'])) {
            throw new RuntimeException('claims');
        }
        if (!is_string($claims['at_hash'] ?? null) ||
            !hash_equals(self::b64(substr(hash('sha256', $access, true), 0, 16)), $claims['at_hash'])) {
            throw new RuntimeException('token');
        }
        return $claims;
    }
}
