<?php
declare(strict_types=1);

final class MeterController
{
    public function index(): void
    {
        $u = Auth::require();
        $meters = Repo::meters((int) $u['id'], true);
        foreach ($meters as &$m) {
            $last = Db::one('SELECT read_at FROM readings WHERE meter_id = ? ORDER BY read_at DESC LIMIT 1', [$m['id']]);
            $m['last_read'] = $last['read_at'] ?? null;
        }
        view('meters/index', ['title' => 'Zähler', 'active' => 'meters', 'meters' => $meters]);
    }

    public function create(): void
    {
        Auth::require();
        view('meters/form', [
            'title' => 'Neuer Zähler', 'active' => 'meters', 'meter' => ['id' => 0, 'name' => '', 'location' => '', 'note' => '', 'kind' => 'digital', 'archived' => 0, 'tariff_model' => 'single',
                'registers' => [['id' => 0, 'obis' => '1.8.0', 'label' => 'Bezug', 'tariff' => 'ET']]],
        ]);
    }

    public function store(): void
    {
        $u = Auth::require();
        [$data, $regs, $err] = $this->input([]);
        if ($err) {
            flash('error', $err);
            view('meters/form', ['title' => 'Neuer Zähler', 'active' => 'meters', 'meter' => $data + ['id' => 0, 'registers' => $regs]]);
            return;
        }
        $id = Db::tx(function () use ($u, $data, $regs) {
            $id = Db::insert(
                'INSERT INTO meters (user_id, name_enc, location_enc, note_enc, kind, created_at) VALUES (?,?,?,?,?,NOW())',
                [$u['id'], Crypto::enc($data['name']), Crypto::enc($data['location']), Crypto::enc($data['note']), $data['kind']]
            );
            foreach ($regs as $i => $r) {
                Db::insert('INSERT INTO registers (meter_id, obis_enc, label_enc, tariff, sort_order) VALUES (?,?,?,?,?)',
                    [$id, Crypto::enc($r['obis']), Crypto::enc($r['label']), $r['tariff'], $i]);
            }
            return $id;
        });
        flash('ok', 'Zähler angelegt.');
        redirect('meters/' . $id);
    }

    public function show(int $id): void
    {
        $u = Auth::require();
        $m = $this->own($u, $id);
        $readings = array_reverse(Repo::readings($id));
        view('meters/show', ['title' => $m['name'], 'active' => 'meters', 'meter' => $m, 'readings' => $readings]);
    }

    public function edit(int $id): void
    {
        $u = Auth::require();
        $m = $this->own($u, $id);
        $m['tariff_model'] = $this->modelOf($m['registers']);
        view('meters/form', ['title' => 'Zähler bearbeiten', 'active' => 'meters', 'meter' => $m]);
    }

    public function update(int $id): void
    {
        $u = Auth::require();
        $existing = $this->own($u, $id);
        [$data, $regs, $err] = $this->input($existing['registers']);
        if ($err) {
            flash('error', $err);
            view('meters/form', ['title' => 'Zähler bearbeiten', 'active' => 'meters', 'meter' => $data + ['id' => $id, 'registers' => $regs]]);
            return;
        }
        Db::tx(function () use ($id, $data, $regs) {
            Db::exec('UPDATE meters SET name_enc=?, location_enc=?, note_enc=?, kind=?, archived=? WHERE id=?',
                [Crypto::enc($data['name']), Crypto::enc($data['location']), Crypto::enc($data['note']), $data['kind'], $data['archived'], $id]);
            $existing = array_map('intval', array_column(Db::all('SELECT id FROM registers WHERE meter_id=?', [$id]), 'id'));
            $keep = [];
            foreach ($regs as $i => $r) {
                if ($r['id'] && in_array($r['id'], $existing, true)) {
                    Db::exec('UPDATE registers SET obis_enc=?, label_enc=?, tariff=?, sort_order=? WHERE id=? AND meter_id=?',
                        [Crypto::enc($r['obis']), Crypto::enc($r['label']), $r['tariff'], $i, $r['id'], $id]);
                    $keep[] = $r['id'];
                } else {
                    $keep[] = Db::insert('INSERT INTO registers (meter_id, obis_enc, label_enc, tariff, sort_order) VALUES (?,?,?,?,?)',
                        [$id, Crypto::enc($r['obis']), Crypto::enc($r['label']), $r['tariff'], $i]);
                }
            }
            foreach (array_diff($existing, $keep) as $gone) {
                Db::exec('DELETE FROM registers WHERE id=? AND meter_id=?', [$gone, $id]); // Werte werden per CASCADE entfernt
            }
        });
        flash('ok', 'Zähler gespeichert.');
        redirect('meters/' . $id);
    }

    public function delete(int $id): void
    {
        $u = Auth::require();
        $this->own($u, $id);
        Repo::deletePhotoFilesForMeter((int) $u['id'], $id);
        Db::exec('DELETE FROM meters WHERE id=? AND user_id=?', [$id, $u['id']]); // Ablesungen, Register, Verträge: CASCADE
        flash('ok', 'Zähler samt Ablesungen, Fotos und Verträgen gelöscht.');
        redirect('meters');
    }

    private function own(array $u, int $id): array
    {
        $m = Repo::meter((int) $u['id'], $id);
        if (!$m) {
            abort(404, 'Zähler nicht gefunden.');
        }
        return $m;
    }

    private function modelOf(array $registers): string
    {
        foreach ($registers as $r) {
            if (in_array($r['tariff'], ['HT', 'NT'], true)) {
                return 'multi';
            }
        }
        return 'single';
    }

    /**
     * Analog: Register ergeben sich aus der Tarifwahl (Eintarif / HT+NT), ohne OBIS.
     * Digital: freie OBIS-Register, jede Kennzahl genau einmal.
     * @param array $existing bisherige Register (zur Wiederverwendung der IDs beim Bearbeiten)
     * @return array{0: array, 1: array, 2: ?string}
     */
    private function input(array $existing): array
    {
        $data = [
            'name' => mb_trim_len((string) ($_POST['name'] ?? ''), 120),
            'location' => mb_trim_len((string) ($_POST['location'] ?? ''), 160),
            'note' => mb_trim_len((string) ($_POST['note'] ?? ''), 1000),
            'kind' => ($_POST['kind'] ?? '') === 'analog' ? 'analog' : 'digital',
            'archived' => empty($_POST['archived']) ? 0 : 1,
        ];
        $regs = [];
        $err = null;

        if ($data['kind'] === 'analog') {
            $model = ($_POST['tariff_model'] ?? '') === 'multi' ? 'multi' : 'single';
            $data['tariff_model'] = $model;
            $spec = $model === 'multi'
                ? [['HT', 'Hochtarif (HT)'], ['NT', 'Niedertarif (NT)']]
                : [['ET', 'Zählerstand']];
            foreach ($spec as [$tariff, $label]) {
                $id = 0;
                foreach ($existing as $e) {
                    if ($e['tariff'] === $tariff) {
                        $id = $e['id'];
                        break;
                    }
                }
                $regs[] = ['id' => $id, 'obis' => '', 'label' => $label, 'tariff' => $tariff];
            }
        } else {
            $tariffs = array_keys(tariff_labels());
            $seen = [];
            foreach ((array) ($_POST['reg'] ?? []) as $r) {
                $label = mb_trim_len((string) ($r['label'] ?? ''), 80);
                $obis = mb_trim_len((string) ($r['obis'] ?? ''), 30);
                if ($label === '' && $obis === '') {
                    continue;
                }
                $regs[] = ['id' => (int) ($r['id'] ?? 0), 'obis' => $obis, 'label' => $label !== '' ? $label : $obis,
                           'tariff' => in_array($r['tariff'] ?? '', $tariffs, true) ? $r['tariff'] : 'ET'];
            }
            foreach ($regs as $r) {
                $key = mb_strtolower($r['obis']);
                if ($r['obis'] === '') {
                    $err = 'Bei digitalen Zählern ist für jedes Register eine OBIS-Kennzahl erforderlich (z. B. 1.8.0).';
                } elseif (!preg_match('/^[0-9A-Za-z.\-:*&()]{1,30}$/', $r['obis'])) {
                    $err = 'Ungültige OBIS-Kennzahl: ' . $r['obis'];
                } elseif (isset($seen[$key])) {
                    $err = 'Die OBIS-Kennzahl „' . $r['obis'] . '“ ist mehrfach vorhanden – jede Kennzahl darf nur einmal vorkommen.';
                }
                $seen[$key] = true;
                if ($err) {
                    break;
                }
            }
            $data['tariff_model'] = 'single';
        }

        if ($data['name'] === '') {
            $err = 'Bitte einen Namen für den Zähler angeben.';
        } elseif (!$regs) {
            $err = 'Mindestens ein Register ist erforderlich.';
        }
        return [$data, $regs, $err];
    }
}
