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
                'note' => (string) Crypto::dec($r['note_enc']), 'values' => $vBy[$id] ?? [], 'photos' => $pBy[$id] ?? [],
            ];
        }
        return $out;
    }

    public static function reading(int $uid, int $id): ?array
    {
        $r = Db::one('SELECT r.* FROM readings r JOIN meters m ON m.id = r.meter_id WHERE r.id = ? AND m.user_id = ?', [$id, $uid]);
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
               'min_term_months' => null, 'credits' => [], 'price_basis' => 'gross', 'vat_rate' => 19.0];
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
