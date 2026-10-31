<?php
declare(strict_types=1);

/** Datenzugriff inkl. Ver-/Entschlüsselung. Alle Methoden prüfen die Zugehörigkeit zum Benutzer. */
final class Repo
{
    // ---------- Zähler ----------
    public static function meters(int $uid, bool $includeArchived = false): array
    {
        $rows = Db::all('SELECT * FROM meters WHERE user_id = ?' . ($includeArchived ? '' : ' AND archived = 0') . ' ORDER BY id', [$uid]);
        return array_map(fn($r) => self::hydrateMeter($r), $rows);
    }

    public static function meter(int $uid, int $id): ?array
    {
        $r = Db::one('SELECT * FROM meters WHERE id = ? AND user_id = ?', [$id, $uid]);
        return $r ? self::hydrateMeter($r) : null;
    }

    /** Zähler, auf den der Benutzer Zugriff hat: eigener (access = owner) oder freigegebener (access = read|write). */
    public static function meterShared(int $uid, int $id): ?array
    {
        $own = self::meter($uid, $id);
        if ($own) {
            return $own + ['access' => 'owner'];
        }
        $r = Db::one('SELECT m.*, s.permission, u.name AS owner_name FROM meters m JOIN meter_shares s ON s.meter_id = m.id JOIN users u ON u.id = m.user_id WHERE m.id = ? AND s.user_id = ? AND (s.access_until IS NULL OR s.access_until > NOW())', [$id, $uid]);
        return $r ? self::hydrateMeter($r) + ['access' => $r['permission'], 'owner_name' => (string) $r['owner_name']] : null;
    }

    /** Für den Benutzer freigegebene Zähler (fremde). */
    public static function sharedMeters(int $uid): array
    {
        $rows = Db::all('SELECT m.*, s.permission, s.id AS share_id, u.name AS owner_name FROM meters m JOIN meter_shares s ON s.meter_id = m.id JOIN users u ON u.id = m.user_id WHERE s.user_id = ? AND (s.access_until IS NULL OR s.access_until > NOW()) AND m.archived = 0 ORDER BY m.id', [$uid]);
        return array_map(fn($r) => self::hydrateMeter($r) + ['access' => $r['permission'], 'share_id' => (int) $r['share_id'], 'owner_name' => (string) $r['owner_name']], $rows);
    }

    /** Freigaben eines Zählers (für den Eigentümer). */
    public static function shares(int $meterId): array
    {
        $rows = Db::all('SELECT s.*, u.name AS user_name FROM meter_shares s LEFT JOIN users u ON u.id = s.user_id WHERE s.meter_id = ? ORDER BY s.id', [$meterId]);
        return array_map(static fn($r) => [
            'id' => (int) $r['id'], 'permission' => $r['permission'], 'label' => (string) Crypto::dec($r['label_enc']),
            'user_name' => (string) ($r['user_name'] ?? ''), 'redeemed' => $r['user_id'] !== null, 'expires_at' => $r['expires_at'], 'created_at' => $r['created_at'],
            'expired' => $r['user_id'] === null && $r['expires_at'] !== null && strtotime($r['expires_at']) < time(),
            'access_until' => $r['access_until'], 'duration_days' => (int) ($r['duration_days'] ?: 365),
            'access_expired' => $r['user_id'] !== null && $r['access_until'] !== null && strtotime($r['access_until']) < time(),
            'days_left' => $r['access_until'] !== null ? (int) floor((strtotime($r['access_until']) - time()) / 86400) : null,
        ], $rows);
    }

    /** Freigaben, die in weniger als 30 Tagen ablaufen (oder abgelaufen sind): für den Eigentümer und für den Mitnutzer. */
    public static function expiringShares(int $uid): array
    {
        $limit = 'DATE_ADD(NOW(), INTERVAL 30 DAY)';
        $own = Db::all('SELECT s.id, s.meter_id, s.access_until, m.name_enc, u.name AS other FROM meter_shares s JOIN meters m ON m.id = s.meter_id JOIN users u ON u.id = s.user_id WHERE m.user_id = ? AND s.user_id IS NOT NULL AND s.access_until IS NOT NULL AND s.access_until < ' . $limit . ' ORDER BY s.access_until', [$uid]);
        $mine = Db::all('SELECT s.id, s.meter_id, s.access_until, m.name_enc, u.name AS other FROM meter_shares s JOIN meters m ON m.id = s.meter_id JOIN users u ON u.id = m.user_id WHERE s.user_id = ? AND s.access_until IS NOT NULL AND s.access_until > NOW() AND s.access_until < ' . $limit . ' ORDER BY s.access_until', [$uid]);
        $map = static fn(array $rows, string $role) => array_map(static fn($r) => [
            'role' => $role, 'meter_id' => (int) $r['meter_id'], 'meter' => (string) Crypto::dec($r['name_enc']), 'other' => (string) $r['other'],
            'until' => $r['access_until'], 'expired' => strtotime($r['access_until']) < time(),
            'days' => max(0, (int) ceil((strtotime($r['access_until']) - time()) / 86400)),
        ], $rows);
        return array_merge($map($own, 'owner'), $map($mine, 'sharer'));
    }

    private static function hydrateMeter(array $r): array
    {
        return [
            'id' => (int) $r['id'], 'user_id' => (int) $r['user_id'], 'kind' => $r['kind'], 'archived' => (int) $r['archived'],
            'name' => (string) Crypto::dec($r['name_enc']), 'location' => (string) Crypto::dec($r['location_enc']),
            'note' => (string) Crypto::dec($r['note_enc']),
            'registers' => self::registers((int) $r['id']),
        ];
    }

    public static function registers(int $meterId): array
    {
        $rows = Db::all('SELECT * FROM registers WHERE meter_id = ? ORDER BY sort_order, id', [$meterId]);
        return array_map(static fn($r) => [
            'id' => (int) $r['id'], 'obis' => (string) Crypto::dec($r['obis_enc']),
            'label' => (string) Crypto::dec($r['label_enc']), 'tariff' => $r['tariff'],
        ], $rows);
    }

    public static function registerTitle(array $reg): string
    {
        return $reg['label'] . ($reg['obis'] !== '' ? ' (' . $reg['obis'] . ')' : '');
    }

    // ---------- Ablesungen ----------
    /** Alle Ablesungen eines Zählers (aufsteigend) inkl. Werte & Fotos. */
    public static function readings(int $meterId): array
    {
        $rows = Db::all('SELECT * FROM readings WHERE meter_id = ? ORDER BY read_at, id', [$meterId]);
        if (!$rows) {
            return [];
        }
        $ids = array_map(static fn($r) => (int) $r['id'], $rows);
        $in = implode(',', $ids);
        $values = Db::all("SELECT * FROM reading_values WHERE reading_id IN ($in)");
        $photos = Db::all("SELECT id, reading_id, size_bytes FROM reading_photos WHERE reading_id IN ($in) ORDER BY id");
        $vBy = [];
        foreach ($values as $v) {
            $vBy[(int) $v['reading_id']][(int) $v['register_id']] = (float) Crypto::dec($v['value_enc']);
        }
        $pBy = [];
        foreach ($photos as $p) {
            $pBy[(int) $p['reading_id']][] = ['id' => (int) $p['id'], 'size' => (int) $p['size_bytes']];
        }
        $out = [];
        foreach ($rows as $r) {
            $id = (int) $r['id'];
            $out[] = [
                'id' => $id, 'meter_id' => (int) $r['meter_id'], 'read_at' => $r['read_at'], 'ts' => strtotime($r['read_at']), 'reason' => (string) ($r['reason'] ?? 'regular'),
                'note' => (string) Crypto::dec($r['note_enc']), 'values' => $vBy[$id] ?? [], 'photos' => $pBy[$id] ?? [], 'created_by' => isset($r['created_by']) ? (int) $r['created_by'] : null,
            ];
        }
        return $out;
    }

    public static function reading(int $uid, int $id): ?array
    {
        $r = Db::one('SELECT r.* FROM readings r JOIN meters m ON m.id = r.meter_id WHERE r.id = ? AND (m.user_id = ? OR EXISTS (SELECT 1 FROM meter_shares s WHERE s.meter_id = m.id AND s.user_id = ? AND (s.access_until IS NULL OR s.access_until > NOW())))', [$id, $uid, $uid]);
        if (!$r) {
            return null;
        }
        foreach (self::readings((int) $r['meter_id']) as $x) {
            if ($x['id'] === $id) {
                return $x;
            }
        }
        return null;
    }

    /** register_id => [[ts, value], ...] aufsteigend. */
    public static function series(array $readings): array
    {
        $s = [];
        foreach ($readings as $r) {
            foreach ($r['values'] as $regId => $val) {
                $s[$regId][] = [$r['ts'], $val];
            }
        }
        return $s;
    }

    // ---------- Verträge & Zahlungen ----------
    public static function contracts(int $uid): array
    {
        $rows = Db::all('SELECT * FROM contracts WHERE user_id = ? ORDER BY start_date DESC, id DESC', [$uid]);
        return array_map(fn($r) => self::hydrateContract($r), $rows);
    }

    public static function contract(int $uid, int $id): ?array
    {
        $r = Db::one('SELECT * FROM contracts WHERE id = ? AND user_id = ?', [$id, $uid]);
        return $r ? self::hydrateContract($r) : null;
    }

    private static function hydrateContract(array $r): array
    {
        $d = Crypto::decJson($r['data_enc']);
        $d += ['provider' => '', 'contract_no' => '', 'base_fee' => 0.0, 'base_period' => 'month', 'price_et' => null, 'price_ht' => null,
               'price_nt' => null, 'feedin' => null, 'annual_kwh' => null, 'monthly_advance' => null, 'note' => '',
               'min_term_months' => null, 'credits' => [], 'price_basis' => 'gross', 'vat_rate' => 19.0, 'billing_months' => 12, 'first_billing' => null, 'price_changes' => []];
        foreach ($d['credits'] as &$cr) {   // Altformat: Vertragsjahr -> Monate
            if (!isset($cr['months'])) {
                $cr['months'] = max(1, (int) ($cr['year'] ?? 1)) * 12;
            }
        }
        unset($cr);
        return ['id' => (int) $r['id'], 'user_id' => (int) $r['user_id'], 'meter_id' => (int) $r['meter_id'],
                'start_date' => $r['start_date'], 'end_date' => $r['end_date'], 'data' => $d];
    }

    public static function payments(int $contractId): array
    {
        $rows = Db::all('SELECT * FROM payments WHERE contract_id = ? ORDER BY paid_on DESC, id DESC', [$contractId]);
        return array_map(static function ($r) {
            $d = Crypto::decJson($r['data_enc']) + ['amount' => 0.0, 'status' => 'paid', 'kind' => 'advance', 'note' => ''];
            return ['id' => (int) $r['id'], 'contract_id' => (int) $r['contract_id'], 'paid_on' => $r['paid_on'], 'data' => $d];
        }, $rows);
    }

    public static function payment(int $uid, int $id): ?array
    {
        $r = Db::one('SELECT p.* FROM payments p JOIN contracts c ON c.id = p.contract_id WHERE p.id = ? AND c.user_id = ?', [$id, $uid]);
        if (!$r) {
            return null;
        }
        $d = Crypto::decJson($r['data_enc']) + ['amount' => 0.0, 'status' => 'paid', 'kind' => 'advance', 'note' => ''];
        return ['id' => (int) $r['id'], 'contract_id' => (int) $r['contract_id'], 'paid_on' => $r['paid_on'], 'data' => $d];
    }

    /** Löscht alle Foto-Dateien zu den Ablesungen der angegebenen Zähler. */
    public static function deletePhotoFilesForMeter(int $uid, int $meterId): void
    {
        foreach (Db::all('SELECT p.file_name FROM reading_photos p JOIN readings r ON r.id = p.reading_id WHERE r.meter_id = ?', [$meterId]) as $p) {
            ImageProcessor::delete($uid, $p['file_name']);
        }
    }
}
