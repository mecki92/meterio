<?php
declare(strict_types=1);

/**
 * Anwendungsseitige Verschlüsselung (AES-256-GCM, authentifiziert).
 * Format: "v1:" . base64( IV(12) | TAG(16) | CIPHERTEXT )
 */
final class Crypto
{
    private const CIPHER = 'aes-256-gcm';
    private static ?string $key = null;

    public static function key(): string
    {
        if (self::$key === null) {
            $raw = base64_decode((string) Config::get('key', ''), true);
            if ($raw === false || strlen($raw) !== 32) {
                throw new RuntimeException('Ungültiger Verschlüsselungsschlüssel: config.php -> "key" muss 32 Byte (Base64) enthalten.');
            }
            self::$key = $raw;
        }
        return self::$key;
    }

    public static function encBytes(string $plain): string
    {
        $iv  = random_bytes(12);
        $tag = '';
        $ct  = openssl_encrypt($plain, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($ct === false) {
            throw new RuntimeException('Verschlüsselung fehlgeschlagen.');
        }
        return $iv . $tag . $ct;
    }

    public static function decBytes(string $blob): string
    {
        if (strlen($blob) < 28) {
            throw new RuntimeException('Ungültige verschlüsselte Daten.');
        }
        $iv  = substr($blob, 0, 12);
        $tag = substr($blob, 12, 16);
        $ct  = substr($blob, 28);
        $pt  = openssl_decrypt($ct, self::CIPHER, self::key(), OPENSSL_RAW_DATA, $iv, $tag);
        if ($pt === false) {
            throw new RuntimeException('Entschlüsselung fehlgeschlagen (falscher Schlüssel oder manipulierte Daten).');
        }
        return $pt;
    }

    public static function enc(?string $plain): ?string
    {
        return $plain === null ? null : 'v1:' . base64_encode(self::encBytes($plain));
    }

    public static function dec(?string $stored): ?string
    {
        if ($stored === null || $stored === '') {
            return null;
        }
        if (!str_starts_with($stored, 'v1:')) {
            throw new RuntimeException('Unbekanntes Verschlüsselungsformat.');
        }
        $raw = base64_decode(substr($stored, 3), true);
        if ($raw === false) {
            throw new RuntimeException('Ungültige Base64-Daten.');
        }
        return self::decBytes($raw);
    }

    public static function encJson(array $data): string
    {
        return (string) self::enc(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public static function decJson(?string $stored): array
    {
        $s = self::dec($stored);
        if ($s === null) {
            return [];
        }
        $d = json_decode($s, true);
        return is_array($d) ? $d : [];
    }

    /** Schlüssel-abhängiger Hash (Tokens, OTP, Backup-Codes, Rate-Limits). */
    public static function hmac(string $value): string
    {
        return hash_hmac('sha256', $value, self::key());
    }

    public static function generateKey(): string
    {
        return base64_encode(random_bytes(32));
    }
}
