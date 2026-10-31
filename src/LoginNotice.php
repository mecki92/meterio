<?php
declare(strict_types=1);

/**
 * Info-Mail bei jeder erfolgreichen Anmeldung (Zeitpunkt, Gerät, IP, ungefährer Ort). Es wird nichts gespeichert.
 */
final class LoginNotice
{
    public static function handle(array $user): void
    {
        try {
            if (!Config::get('security.login_notify', true)) {
                return;
            }
            $ip = client_ip();
            $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
            [$browser, $os] = self::parseUa($ua);
            $place = Config::get('security.geo_lookup', true) ? self::geo($ip) : null;
            $rows = [
                ['Zeitpunkt', date('d.m.Y, H:i') . ' Uhr'],
                ['Gerät', $browser . ' auf ' . $os],
                ['IP-Adresse', $ip],
            ];
            if ($place) {
                $rows[] = ['Ungefährer Ort', $place];
            }
            $tbl = '<table style="border-collapse:collapse;margin:12px 0">';
            foreach ($rows as [$k, $v]) {
                $tbl .= '<tr><td style="padding:4px 14px 4px 0;color:#667">' . e($k) . '</td><td style="padding:4px 0"><b>' . e($v) . '</b></td></tr>';
            }
            $tbl .= '</table>';
            $html = Mailer::wrap('Neue Anmeldung', '<p>Hallo ' . e($user['name']) . ',</p>'
                . '<p>in deinem Konto wurde eine Anmeldung registriert:</p>' . $tbl
                . '<p>Wenn du das warst, musst du nichts tun. Wenn nicht, ändere sofort dein Passwort und setze die Zwei-Faktor-Authentifizierung zurück.</p>'
                . Mailer::button(abs_url('profile'), 'Zum Profil'));
            Mailer::send($user['email'], 'Neue Anmeldung in deinem Konto', $html);
        } catch (Throwable $e) {
            error_log('meterio login notice: ' . $e->getMessage());
        }
    }

    /** @return array{0:string,1:string} */
    private static function parseUa(string $ua): array
    {
        $browser = 'Unbekannter Browser';
        foreach ([['Edg/', 'Edge'], ['OPR/', 'Opera'], ['SamsungBrowser', 'Samsung Internet'], ['Firefox/', 'Firefox'], ['FxiOS', 'Firefox'], ['CriOS', 'Chrome'], ['Chrome/', 'Chrome'], ['Safari/', 'Safari']] as [$n, $l]) {
            if (stripos($ua, $n) !== false) { $browser = $l; break; }
        }
        $os = 'unbekanntem System';
        foreach ([['Windows', 'Windows'], ['Android', 'Android'], ['iPhone', 'iOS'], ['iPad', 'iPadOS'], ['Mac OS X', 'macOS'], ['CrOS', 'ChromeOS'], ['Linux', 'Linux']] as [$n, $l]) {
            if (stripos($ua, $n) !== false) { $os = $l; break; }
        }
        return [$browser, $os];
    }

    private static function geo(string $ip): ?string
    {
        if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return null;
        }
        $ctx = stream_context_create(['http' => ['timeout' => 2, 'ignore_errors' => true, 'header' => "User-Agent: meterio\r\n"]]);
        $raw = @file_get_contents('https://ipwho.is/' . rawurlencode($ip) . '?fields=success,city,region,country', false, $ctx);
        $j = $raw ? json_decode($raw, true) : null;
        if (!is_array($j) || empty($j['success'])) {
            return null;
        }
        $parts = array_filter([(string) ($j['city'] ?? ''), (string) ($j['region'] ?? ''), (string) ($j['country'] ?? '')]);
        return $parts ? implode(', ', array_unique($parts)) : null;
    }
}
