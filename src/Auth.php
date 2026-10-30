<?php
declare(strict_types=1);

/**
 * Authentifizierung in zwei Stufen:
 *  1) Passwort ok  -> $_SESSION['pre'] (noch KEIN Zugriff auf die Anwendung)
 *  2) 2FA ok       -> $_SESSION['uid'] (Session-ID wird erneuert)
 */
final class Auth
{
    private static ?array $cached = null;
    private const MAX_ATTEMPTS = 8;
    private const LOCK_MINUTES = 15;

    // ---------- Benutzer ----------
    public static function hash(string $pw): string
    {
        return password_hash($pw, defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_BCRYPT);
    }

    public static function findById(int $id): ?array
    {
        return Db::one('SELECT * FROM users WHERE id = ?', [$id]);
    }

    public static function findByEmail(string $email): ?array
    {
        return Db::one('SELECT * FROM users WHERE email = ?', [mb_strtolower(trim($email))]);
    }

    public static function createUser(string $email, string $name, string $password, string $role = 'user', bool $forceChange = false): int
    {
        return Db::insert(
            'INSERT INTO users (email, name, password_hash, role, force_pw_change, created_at) VALUES (?,?,?,?,?,NOW())',
            [mb_strtolower(trim($email)), $name, self::hash($password), $role, $forceChange ? 1 : 0]
        );
    }

    public static function passwordError(string $pw): ?string
    {
        if (mb_strlen($pw) < 10) {
            return 'Das Passwort muss mindestens 10 Zeichen lang sein.';
        }
        if (!preg_match('/[A-Za-z]/', $pw) || !preg_match('/\d/', $pw)) {
            return 'Das Passwort muss Buchstaben und Ziffern enthalten.';
        }
        return null;
    }

    // ---------- Sitzung ----------
    public static function user(): ?array
    {
        if (self::$cached !== null) {
            return self::$cached;
        }
        $uid = $_SESSION['uid'] ?? null;
        if (!$uid) {
            return null;
        }
        $idle = (int) Config::get('app.idle_timeout', 7200);
        if (time() - (int) ($_SESSION['last'] ?? 0) > $idle) {
            self::logout();
            return null;
        }
        $u = self::findById((int) $uid);
        if (!$u || !$u['is_active']) {
            self::logout();
            return null;
        }
        $_SESSION['last'] = time();
        return self::$cached = $u;
    }

    public static function require(): array
    {
        $u = self::user();
        if (!$u) {
            redirect('login');
        }
        return $u;
    }

    public static function requireAdmin(): array
    {
        $u = self::require();
        if ($u['role'] !== 'admin') {
            abort(403, 'Dieser Bereich ist Administratoren vorbehalten.');
        }
        return $u;
    }

    public static function complete(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION = ['uid' => (int) $user['id'], 'last' => time(), 'csrf' => bin2hex(random_bytes(32))];
        Db::exec('UPDATE users SET last_login_at = NOW() WHERE id = ?', [$user['id']]);
        self::$cached = null;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'secure' => $p['secure'], 'httponly' => true, 'samesite' => 'Lax']);
        }
        @session_destroy();
        session_start();
        session_regenerate_id(true);
        self::$cached = null;
    }

    /** Zwischenzustand nach korrektem Passwort. */
    public static function pre(): ?array
    {
        $p = $_SESSION['pre'] ?? null;
        if (!$p || time() - (int) $p['t'] > 900) {
            unset($_SESSION['pre']);
            return null;
        }
        $u = self::findById((int) $p['uid']);
        return ($u && $u['is_active']) ? $u : null;
    }

    public static function startPre(array $user): void
    {
        session_regenerate_id(true);
        $_SESSION['pre'] = ['uid' => (int) $user['id'], 't' => time(), 'tries' => 0];
        unset($_SESSION['uid']);
    }

    /** Benutzer für das 2FA-Setup: voll angemeldet ODER im Zwischenzustand. */
    public static function setupUser(): ?array
    {
        return self::user() ?? self::pre();
    }

    // ---------- Rate-Limit ----------
    public static function tooMany(string $kind, string $ident): bool
    {
        $since = date('Y-m-d H:i:s', time() - self::LOCK_MINUTES * 60);
        $byIp = (int) Db::val('SELECT COUNT(*) FROM login_attempts WHERE kind=? AND ip=? AND created_at>=?', [$kind, client_ip(), $since]);
        $byId = (int) Db::val('SELECT COUNT(*) FROM login_attempts WHERE kind=? AND ident_hash=? AND created_at>=?', [$kind, Crypto::hmac(mb_strtolower($ident)), $since]);
        return $byIp >= self::MAX_ATTEMPTS * 2 || $byId >= self::MAX_ATTEMPTS;
    }

    public static function fail(string $kind, string $ident): void
    {
        Db::exec('INSERT INTO login_attempts (kind, ip, ident_hash, created_at) VALUES (?,?,?,NOW())', [$kind, client_ip(), Crypto::hmac(mb_strtolower($ident))]);
    }

    public static function clearFails(string $kind, string $ident): void
    {
        Db::exec('DELETE FROM login_attempts WHERE kind=? AND ident_hash=?', [$kind, Crypto::hmac(mb_strtolower($ident))]);
    }

    // ---------- E-Mail-OTP ----------
    public static function sendEmailOtp(array $user): bool
    {
        Db::exec('DELETE FROM otp_codes WHERE user_id = ?', [$user['id']]);
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        Db::exec(
            'INSERT INTO otp_codes (user_id, code_hash, expires_at, created_at) VALUES (?,?,?,NOW())',
            [$user['id'], Crypto::hmac($user['id'] . ':' . $code), date('Y-m-d H:i:s', time() + 600)]
        );
        $html = Mailer::wrap('Dein Anmeldecode', '<p>Hallo ' . e($user['name']) . ',</p>'
            . '<p>dein Einmalcode lautet:</p><p style="font-size:30px;letter-spacing:6px;font-weight:bold;margin:16px 0">' . e($code) . '</p>'
            . '<p>Der Code ist 10 Minuten gültig. Falls du dich nicht anmelden wolltest, ignoriere diese E-Mail und ändere dein Passwort.</p>');
        return Mailer::send($user['email'], 'Dein Anmeldecode', $html);
    }

    public static function verifyEmailOtp(int $userId, string $code): bool
    {
        $code = preg_replace('/\D/', '', $code);
        $row = Db::one('SELECT * FROM otp_codes WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$userId]);
        if (!$row || strtotime($row['expires_at']) < time()) {
            return false;
        }
        if ((int) $row['attempts'] >= 5) {
            Db::exec('DELETE FROM otp_codes WHERE user_id = ?', [$userId]);
            return false;
        }
        Db::exec('UPDATE otp_codes SET attempts = attempts + 1 WHERE id = ?', [$row['id']]);
        if (strlen($code) === 6 && hash_equals($row['code_hash'], Crypto::hmac($userId . ':' . $code))) {
            Db::exec('DELETE FROM otp_codes WHERE user_id = ?', [$userId]);
            return true;
        }
        return false;
    }

    // ---------- TOTP ----------
    public static function verifyTotp(array $user, string $code): bool
    {
        $secret = Crypto::dec($user['totp_secret_enc']);
        if (!$secret) {
            return false;
        }
        $step = Totp::verify($secret, $code, (int) $user['totp_last_step']);
        if ($step === null) {
            return false;
        }
        Db::exec('UPDATE users SET totp_last_step = ? WHERE id = ?', [$step, $user['id']]);
        return true;
    }

    // ---------- Backup-Codes ----------
    public static function generateBackupCodes(int $userId, int $n = 10): array
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        Db::exec('DELETE FROM backup_codes WHERE user_id = ?', [$userId]);
        $codes = [];
        for ($i = 0; $i < $n; $i++) {
            $raw = '';
            for ($j = 0; $j < 10; $j++) {
                $raw .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $codes[] = substr($raw, 0, 5) . '-' . substr($raw, 5);
            Db::exec('INSERT INTO backup_codes (user_id, code_hash) VALUES (?,?)', [$userId, Crypto::hmac('bk:' . $userId . ':' . $raw)]);
        }
        return $codes;
    }

    public static function useBackupCode(int $userId, string $input): bool
    {
        $raw = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $input));
        if (strlen($raw) !== 10) {
            return false;
        }
        $hash = Crypto::hmac('bk:' . $userId . ':' . $raw);
        return Db::exec('UPDATE backup_codes SET used_at = NOW() WHERE user_id = ? AND code_hash = ? AND used_at IS NULL', [$userId, $hash]) === 1;
    }

    public static function unusedBackupCodes(int $userId): int
    {
        return (int) Db::val('SELECT COUNT(*) FROM backup_codes WHERE user_id = ? AND used_at IS NULL', [$userId]);
    }

    // ---------- Tokens (Passwort-Reset / Einladung) ----------
    public static function createToken(int $userId, string $type, int $ttlSeconds): string
    {
        Db::exec('DELETE FROM tokens WHERE user_id = ? AND type = ?', [$userId, $type]);
        $token = random_token(24);
        Db::exec(
            'INSERT INTO tokens (user_id, type, token_hash, expires_at) VALUES (?,?,?,?)',
            [$userId, $type, hash('sha256', $token), date('Y-m-d H:i:s', time() + $ttlSeconds)]
        );
        return $token;
    }

    public static function findToken(string $token): ?array
    {
        return Db::one(
            'SELECT t.*, u.email, u.name, u.is_active FROM tokens t JOIN users u ON u.id = t.user_id
             WHERE t.token_hash = ? AND t.used_at IS NULL AND t.expires_at > NOW()',
            [hash('sha256', $token)]
        );
    }

    public static function sendResetMail(array $user, bool $invite = false): bool
    {
        $token = self::createToken((int) $user['id'], $invite ? 'invite' : 'reset', $invite ? 7 * 86400 : 3600);
        $link = abs_url(($invite ? 'invite/' : 'reset/') . $token);
        if ($invite) {
            $html = Mailer::wrap('Willkommen bei ' . APP_BRAND, '<p>Hallo ' . e($user['name']) . ',</p><p>für dich wurde ein Konto angelegt. Lege über den folgenden Link dein Passwort fest (7 Tage gültig). Bei der ersten Anmeldung richtest du außerdem die Zwei-Faktor-Authentifizierung ein.</p>'
                . Mailer::button($link, 'Passwort festlegen'));
            return Mailer::send($user['email'], 'Einladung', $html);
        }
        $html = Mailer::wrap('Passwort zurücksetzen', '<p>Hallo ' . e($user['name']) . ',</p><p>über den folgenden Link kannst du ein neues Passwort setzen. Er ist <b>1 Stunde</b> gültig. Wenn du das nicht angefordert hast, kannst du diese E-Mail ignorieren.</p>'
            . Mailer::button($link, 'Neues Passwort setzen'));
        return Mailer::send($user['email'], 'Passwort zurücksetzen', $html);
    }
}
