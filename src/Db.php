<?php
declare(strict_types=1);

final class Db
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $c = Config::get('db', []);
            $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s', $c['host'] ?? 'localhost', $c['name'] ?? '', $c['charset'] ?? 'utf8mb4');
            self::$pdo = new PDO($dsn, (string) ($c['user'] ?? ''), (string) ($c['pass'] ?? ''), [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            self::$pdo->exec("SET time_zone = '" . date('P') . "'");
            self::migrate(self::$pdo);
        }
        return self::$pdo;
    }

    /** Einfache Auto-Migration für bestehende Installationen (einmalig je Änderung, Marker-Dateien in storage/). */
    private static function migrate(PDO $pdo): void
    {
        $steps = [
            'reading_reason' => ['readings', 'reason', "ALTER TABLE readings ADD COLUMN reason VARCHAR(20) NOT NULL DEFAULT 'regular' AFTER read_at"],
            'notify_reading' => ['users', 'notify_reading', "ALTER TABLE users ADD COLUMN notify_reading TINYINT(1) NOT NULL DEFAULT 1 AFTER last_reminder_at"],
            'reading_created_by' => ['readings', 'created_by', "ALTER TABLE readings ADD COLUMN created_by INT UNSIGNED NULL AFTER note_enc"],
            'reminder_month_end' => ['users', 'reminder_mode', "ALTER TABLE users MODIFY reminder_mode ENUM('off','monthly','month_end','contract_year') NOT NULL DEFAULT 'monthly'", 'month_end'],
        ];
        $mk = ROOT . '/storage/.migrated_meter_shares';
        if (!is_file($mk)) {   // Tabelle für Zähler-Freigaben
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS meter_shares (
                  id INT UNSIGNED NOT NULL AUTO_INCREMENT, meter_id INT UNSIGNED NOT NULL, user_id INT UNSIGNED NULL, token_hash CHAR(64) NULL,
                  permission ENUM('read','write') NOT NULL DEFAULT 'write', label_enc TEXT NULL, expires_at DATETIME NULL, redeemed_at DATETIME NULL, created_at DATETIME NOT NULL,
                  PRIMARY KEY (id), UNIQUE KEY ux_share_token (token_hash), KEY ix_share_meter (meter_id), KEY ix_share_user (user_id),
                  CONSTRAINT fk_share_meter FOREIGN KEY (meter_id) REFERENCES meters (id) ON DELETE CASCADE,
                  CONSTRAINT fk_share_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                @file_put_contents($mk, date('c'));
            } catch (Throwable $e) {
                error_log('meterio migrate meter_shares: ' . $e->getMessage());
            }
        }
        $mk = ROOT . '/storage/.migrated_share_access';
        if (!is_file($mk)) {   // Zugriffsdauer für Freigaben (max. 365 Tage)
            try {
                $has = $pdo->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'meter_shares' AND COLUMN_NAME = 'access_until'")->fetchColumn();
                if (!$has) {
                    $pdo->exec("ALTER TABLE meter_shares ADD COLUMN duration_days SMALLINT UNSIGNED NULL AFTER redeemed_at, ADD COLUMN access_until DATETIME NULL AFTER duration_days");
                    $pdo->exec("UPDATE meter_shares SET duration_days = 365, access_until = DATE_ADD(NOW(), INTERVAL 365 DAY) WHERE user_id IS NOT NULL");
                }
                @file_put_contents($mk, date('c'));
            } catch (Throwable $e) {
                error_log('meterio migrate share_access: ' . $e->getMessage());
            }
        }
        foreach ($steps as $name => $step) {
            [$table, $col, $sql] = $step;
            $needle = $step[3] ?? null;
            $marker = ROOT . '/storage/.migrated_' . $name;
            if (is_file($marker)) {
                continue;
            }
            try {
                $st = $pdo->prepare("SELECT COLUMN_TYPE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
                $st->execute([$table, $col]);
                $type = $st->fetchColumn();
                if ($type === false || ($needle !== null && !str_contains((string) $type, $needle))) {
                    $pdo->exec($sql);
                }
                @file_put_contents($marker, date('c'));
            } catch (Throwable $e) {
                error_log('meterio migrate ' . $name . ': ' . $e->getMessage());
            }
        }
    }

    public static function q(string $sql, array $params = []): PDOStatement
    {
        $st = self::pdo()->prepare($sql);
        $st->execute($params);
        return $st;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::q($sql, $params)->fetchAll();
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $r = self::q($sql, $params)->fetch();
        return $r === false ? null : $r;
    }

    public static function val(string $sql, array $params = []): mixed
    {
        $r = self::q($sql, $params)->fetchColumn();
        return $r === false ? null : $r;
    }

    /** Führt aus und gibt die zuletzt eingefügte ID zurück. */
    public static function insert(string $sql, array $params = []): int
    {
        self::q($sql, $params);
        return (int) self::pdo()->lastInsertId();
    }

    public static function exec(string $sql, array $params = []): int
    {
        return self::q($sql, $params)->rowCount();
    }

    public static function tx(callable $fn): mixed
    {
        $pdo = self::pdo();
        $pdo->beginTransaction();
        try {
            $r = $fn();
            $pdo->commit();
            return $r;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }
}
