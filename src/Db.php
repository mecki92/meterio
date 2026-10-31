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
            'reminder_month_end' => ['users', 'reminder_mode', "ALTER TABLE users MODIFY reminder_mode ENUM('off','monthly','month_end','contract_year') NOT NULL DEFAULT 'monthly'", 'month_end'],
        ];
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
