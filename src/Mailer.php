<?php
declare(strict_types=1);

/** Schlanker SMTP-Client (SSL/STARTTLS, AUTH LOGIN) ohne externe Abhängigkeiten – ausgelegt für netcup SMTP. */
final class Mailer
{
    /** @param list<array{0: string, 1: string, 2: string}> $attachments [Dateiname, MIME-Typ, Inhalt] */
    public static function send(string $to, string $subject, string $html, ?string $text = null, array $attachments = []): bool
    {
        $cfg  = (array) Config::get('mail', []);
        $to   = self::clean($to);
        $subj = '[' . APP_BRAND . '] ' . self::clean($subject);
        $text ??= trim(html_entity_decode(strip_tags(preg_replace('#<(br|/p|/h\d|/tr|/li)>#i', "\n", $html)), ENT_QUOTES, 'UTF-8'));

        $fromAddr = self::clean((string) ($cfg['from'] ?? 'noreply@localhost'));
        $fromName = self::clean((string) ($cfg['from_name'] ?? APP_BRAND));
        $boundary = 'mtr_' . bin2hex(random_bytes(12));
        $domain   = substr(strrchr($fromAddr, '@') ?: '@localhost', 1);

        $outer = $attachments ? 'mtm_' . bin2hex(random_bytes(12)) : null;
        $headers = [
            'Date: ' . date('r'),
            'From: ' . self::encodeHeader($fromName) . ' <' . $fromAddr . '>',
            'To: <' . $to . '>',
            'Subject: ' . self::encodeHeader($subj),
            'Message-ID: <' . bin2hex(random_bytes(10)) . '@' . $domain . '>',
            'MIME-Version: 1.0',
            'X-Mailer: ' . APP_BRAND,
            $outer ? 'Content-Type: multipart/mixed; boundary="' . $outer . '"' : 'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];
        $body = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($text))
            . "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($html))
            . "--$boundary--\r\n";
        if ($outer) {
            $parts = "--$outer\r\nContent-Type: multipart/alternative; boundary=\"$boundary\"\r\n\r\n" . $body;
            foreach ($attachments as [$fname, $mime, $bytes]) {
                $fname = self::clean($fname);
                $parts .= "--$outer\r\nContent-Type: $mime; name=\"$fname\"\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"$fname\"\r\n\r\n"
                    . chunk_split(base64_encode($bytes));
            }
            $body = $parts . "--$outer--\r\n";
        }
        $message = implode("\r\n", $headers) . "\r\n\r\n" . $body;

        if (($cfg['driver'] ?? 'smtp') === 'log') {
            @file_put_contents(ROOT . '/storage/logs/mail.log', "=== " . date('c') . " -> $to\nSubject: $subj\n$text\n\n", FILE_APPEND);
            return true;
        }

        try {
            self::smtp($cfg, $fromAddr, $to, $message);
            return true;
        } catch (Throwable $e) {
            error_log('[meterio mail] ' . $e->getMessage());
            return false;
        }
    }

    /** Einheitliches Branding für alle System-E-Mails. */
    public static function wrap(string $title, string $bodyHtml): string
    {
        $t = e($title);
        $brand = e(APP_BRAND);
        $home = e(abs_url());
        return <<<HTML
<!doctype html><html lang="de"><body style="margin:0;background:#f3f5f7;font-family:Arial,Helvetica,sans-serif;color:#1d2733">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px 12px">
<table role="presentation" width="560" cellpadding="0" cellspacing="0" style="max-width:560px;background:#fff;border-radius:10px;overflow:hidden">
<tr><td style="background:#0f4c81;color:#fff;padding:18px 24px;font-size:20px;font-weight:bold">⚡ {$brand}</td></tr>
<tr><td style="padding:24px"><h2 style="margin:0 0 14px;font-size:18px">{$t}</h2>{$bodyHtml}</td></tr>
<tr><td style="padding:14px 24px;background:#f3f5f7;color:#6b7785;font-size:12px">{$brand} · <a href="{$home}" style="color:#6b7785">{$home}</a><br>Diese Nachricht wurde automatisch erstellt.</td></tr>
</table></td></tr></table></body></html>
HTML;
    }

    public static function button(string $url, string $label): string
    {
        return '<p style="margin:22px 0"><a href="' . e($url) . '" style="background:#0f4c81;color:#fff;text-decoration:none;padding:11px 20px;border-radius:6px;display:inline-block">'
            . e($label) . '</a></p><p style="font-size:12px;color:#6b7785">Falls der Button nicht funktioniert: ' . e($url) . '</p>';
    }

    private static function clean(string $s): string
    {
        return trim(str_replace(["\r", "\n", "\0"], '', $s));
    }

    private static function encodeHeader(string $s): string
    {
        return preg_match('/^[\x20-\x7E]*$/', $s) ? $s : '=?UTF-8?B?' . base64_encode($s) . '?=';
    }

    private static function smtp(array $cfg, string $from, string $to, string $message): void
    {
        $host   = (string) ($cfg['host'] ?? '');
        $port   = (int) ($cfg['port'] ?? 465);
        $secure = (string) ($cfg['secure'] ?? 'ssl');
        if ($host === '') {
            throw new RuntimeException('SMTP-Host nicht konfiguriert.');
        }
        $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'peer_name' => $host]]);
        $fp  = @stream_socket_client(($secure === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
        if (!$fp) {
            throw new RuntimeException("SMTP-Verbindung fehlgeschlagen: $errstr ($errno)");
        }
        stream_set_timeout($fp, 20);

        $read = static function () use ($fp): array {
            $code = 0;
            $text = '';
            while (($line = fgets($fp, 1024)) !== false) {
                $text .= $line;
                $code = (int) substr($line, 0, 3);
                if (isset($line[3]) && $line[3] === ' ') {
                    break;
                }
            }
            return [$code, $text];
        };
        $cmd = static function (string $line, array|int $ok) use ($fp, $read): string {
            if ($line !== '') {
                fwrite($fp, $line . "\r\n");
            }
            [$code, $text] = $read();
            if (!in_array($code, (array) $ok, true)) {
                throw new RuntimeException('SMTP-Fehler bei "' . (str_starts_with($line, 'AUTH') || strlen($line) > 40 ? substr($line, 0, 12) . '…' : $line) . '": ' . trim($text));
            }
            return $text;
        };

        try {
            $cmd('', 220);
            $ehlo = 'EHLO ' . (parse_url(abs_url(), PHP_URL_HOST) ?: 'localhost');
            $cmd($ehlo, 250);
            if ($secure === 'tls') {
                $cmd('STARTTLS', 220);
                if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('STARTTLS fehlgeschlagen.');
                }
                $cmd($ehlo, 250);
            }
            if (!empty($cfg['user'])) {
                $cmd('AUTH LOGIN', 334);
                $cmd(base64_encode((string) $cfg['user']), 334);
                $cmd(base64_encode((string) ($cfg['pass'] ?? '')), 235);
            }
            $cmd("MAIL FROM:<$from>", 250);
            $cmd("RCPT TO:<$to>", [250, 251]);
            $cmd('DATA', 354);
            $data = preg_replace('/^\./m', '..', str_replace(["\r\n", "\r"], "\n", $message));
            fwrite($fp, str_replace("\n", "\r\n", $data) . "\r\n.\r\n");
            [$code, $text] = $read();
            if ($code !== 250) {
                throw new RuntimeException('SMTP DATA abgelehnt: ' . trim($text));
            }
            @fwrite($fp, "QUIT\r\n");
        } finally {
            fclose($fp);
        }
    }
}
