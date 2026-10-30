<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace auth_eudiwallet\local;
defined('MOODLE_INTERNAL') || die();

/** Server-side transport: TLS, public DNS pinned for this request, no redirects. */
final class http {
    public static function addresses(string $host): array {
        $host = trim($host, '[]');
        if (filter_var($host, FILTER_VALIDATE_IP)) { $ips = [$host]; }
        else {
            $records = dns_get_record($host, DNS_A | DNS_AAAA);
            $ips = [];
            foreach ($records ?: [] as $record) {
                if (isset($record['ip'])) { $ips[] = $record['ip']; }
                if (isset($record['ipv6'])) { $ips[] = $record['ipv6']; }
            }
        }
        if (!$ips) { throw new \RuntimeException('service'); }
        foreach ($ips as $ip) {
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) ||
                str_starts_with(strtolower($ip), '::ffff:')) {
                throw new \RuntimeException('service');
            }
        }
        return array_values(array_unique($ips));
    }
    public static function request(string $url, array $args): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');
        $host = parse_url($url, PHP_URL_HOST);
        $ips = self::addresses($host);
        $curl = new \curl(['ignoresecurity' => false, 'proxy' => false]);
        $curl->setHeader(array_map(static fn($key, $value) => $key . ': ' . $value,
            array_keys($args['headers']), array_values($args['headers'])));
        $body = '';
        $curl->setopt(['CURLOPT_FOLLOWLOCATION' => false, 'CURLOPT_MAXREDIRS' => 0,
            'CURLOPT_TIMEOUT' => 15, 'CURLOPT_CONNECTTIMEOUT' => 5,
            'CURLOPT_SSL_VERIFYPEER' => true, 'CURLOPT_SSL_VERIFYHOST' => 2,
            'CURLOPT_PROXY' => '',
            'CURLOPT_WRITEFUNCTION' => static function ($handle, $chunk) use (&$body) {
                if (strlen($body) + strlen($chunk) > 524288) { return 0; }
                $body .= $chunk;
                return strlen($chunk);
            }]);
        $options = ['CURLOPT_RESOLVE' => [$host . ':443:' . implode(',', array_map(
            static fn($ip) => str_contains($ip, ':') ? '[' . $ip . ']' : $ip, $ips))]];
        if ($args['method'] === 'POST') {
            $curl->post($url, http_build_query($args['body'], '', '&', PHP_QUERY_RFC3986), $options);
        } else { $curl->get($url, [], $options); }
        if ($curl->get_errno()) { throw new \RuntimeException('service'); }
        return ['status' => (int)($curl->get_info()['http_code'] ?? 0), 'body' => $body];
    }
}
