<?php
declare(strict_types=1);

/** TOTP nach RFC 6238 (SHA-1, 6 Stellen, 30 s) – kompatibel zu Google Authenticator, 1Password, Bitwarden. */
final class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    public static function base32Encode(string $bin): string
    {
        $bits = '';
        foreach (str_split($bin) as $ch) {
            $bits .= str_pad(decbin(ord($ch)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }
        return $out;
    }

    public static function base32Decode(string $b32): string
    {
        $b32 = strtoupper(preg_replace('/[^A-Za-z2-7]/', '', $b32));
        $bits = '';
        foreach (str_split($b32) as $ch) {
            $bits .= str_pad(decbin((int) strpos(self::ALPHABET, $ch)), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }
        return $out;
    }

    public static function codeForStep(string $secret, int $step): string
    {
        $key  = self::base32Decode($secret);
        $msg  = pack('N2', $step >> 32, $step & 0xFFFFFFFF);
        $hash = hash_hmac('sha1', $msg, $key, true);
        $off  = ord($hash[19]) & 0x0F;
        $num  = ((ord($hash[$off]) & 0x7F) << 24) | (ord($hash[$off + 1]) << 16) | (ord($hash[$off + 2]) << 8) | ord($hash[$off + 3]);
        return str_pad((string) ($num % 1000000), 6, '0', STR_PAD_LEFT);
    }

    /**
     * Prüft den Code (±1 Zeitfenster). Gibt den gültigen Zeitschritt zurück oder null.
     * $lastStep verhindert Wiederverwendung desselben Codes (Replay-Schutz).
     */
    public static function verify(string $secret, string $code, int $lastStep = 0, ?int $now = null): ?int
    {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== 6) {
            return null;
        }
        $cur = intdiv($now ?? time(), 30);
        for ($d = -1; $d <= 1; $d++) {
            $step = $cur + $d;
            if ($step > $lastStep && hash_equals(self::codeForStep($secret, $step), $code)) {
                return $step;
            }
        }
        return null;
    }

    public static function uri(string $secret, string $account, string $issuer = 'meterio by MeckByte'): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account)
            . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=30';
    }
}
