<?php
declare(strict_types=1);

final class ReadingController
{
    public function create(int $meterId): void
    {
        $u = Auth::require();
        $m = $this->meter($u, $meterId);
        $all = Repo::readings($meterId);
        view('readings/form', [
            'title' => 'Ablesung erfassen', 'active' => 'meters', 'meter' => $m, 'reading' => null,
            'prev' => $all ? end($all) : null, 'form' => ['read_at' => input_date(), 'reason' => 'regular', 'note' => '', 'values' => []],
        ]);
    }

    public function store(int $meterId): void
    {
        $u = Auth::require();
        $m = $this->meter($u, $meterId);
        [$readAt, $values, $note, $err] = $this->input($m, null);
        $reason = $this->reason();
        if ($err) {
            $this->formAgain($m, null, $err);
            return;
        }
        $rid = Db::tx(function () use ($meterId, $readAt, $reason, $note, $values) {
            $rid = Db::insert('INSERT INTO readings (meter_id, read_at, reason, note_enc, created_at) VALUES (?,?,?,?,NOW())', [$meterId, $readAt, $reason, Crypto::enc($note)]);
            foreach ($values as $regId => $v) {
                Db::insert('INSERT INTO reading_values (reading_id, register_id, value_enc) VALUES (?,?,?)', [$rid, $regId, Crypto::enc((string) $v)]);
            }
            return $rid;
        });
        $this->savePhotos((int) $u['id'], $rid);
        flash('ok', 'Ablesung gespeichert.');
        redirect('meters/' . $meterId);
    }

    public function edit(int $id): void
    {
        $u = Auth::require();
        $r = $this->reading($u, $id);
        $m = Repo::meter((int) $u['id'], $r['meter_id']);
        view('readings/form', [
            'title' => 'Ablesung bearbeiten', 'active' => 'meters', 'meter' => $m, 'reading' => $r, 'prev' => null,
            'form' => ['read_at' => input_date($r['read_at']), 'reason' => $r['reason'], 'note' => $r['note'], 'values' => $r['values']],
        ]);
    }

    public function update(int $id): void
    {
        $u = Auth::require();
        $r = $this->reading($u, $id);
        $m = Repo::meter((int) $u['id'], $r['meter_id']);
        [$readAt, $values, $note, $err] = $this->input($m, $id);
        $reason = $this->reason();
        if ($err) {
            $this->formAgain($m, $r, $err);
            return;
        }
        Db::tx(function () use ($id, $readAt, $reason, $note, $values) {
            Db::exec('UPDATE readings SET read_at=?, reason=?, note_enc=? WHERE id=?', [$readAt, $reason, Crypto::enc($note), $id]);
            Db::exec('DELETE FROM reading_values WHERE reading_id=?', [$id]);
            foreach ($values as $regId => $v) {
                Db::insert('INSERT INTO reading_values (reading_id, register_id, value_enc) VALUES (?,?,?)', [$id, $regId, Crypto::enc((string) $v)]);
            }
        });
        $this->savePhotos((int) $u['id'], $id);
        flash('ok', 'Ablesung aktualisiert.');
        redirect('meters/' . $m['id']);
    }

    public function delete(int $id): void
    {
        $u = Auth::require();
        $r = $this->reading($u, $id);
        foreach (Db::all('SELECT file_name FROM reading_photos WHERE reading_id=?', [$id]) as $p) {
            ImageProcessor::delete((int) $u['id'], $p['file_name']);
        }
        Db::exec('DELETE FROM readings WHERE id=?', [$id]);
        flash('ok', 'Ablesung gelöscht.');
        redirect('meters/' . $r['meter_id']);
    }

    public function photo(int $id): void
    {
        $u = Auth::require();
        $p = Db::one('SELECT p.* FROM reading_photos p JOIN readings r ON r.id=p.reading_id JOIN meters m ON m.id=r.meter_id WHERE p.id=? AND m.user_id=?', [$id, $u['id']]);
        $bytes = $p ? ImageProcessor::read((int) $u['id'], $p['file_name']) : null;
        if ($bytes === null) {
            abort(404, 'Foto nicht gefunden.');
        }
        header('Content-Type: image/jpeg');
        header('Content-Length: ' . strlen($bytes));
        header('Cache-Control: private, max-age=3600');
        header('Content-Disposition: inline; filename="zaehlerstand-' . $id . '.jpg"');
        echo $bytes;
        exit;
    }

    public function photoDelete(int $id): void
    {
        $u = Auth::require();
        $p = Db::one('SELECT p.*, r.id AS rid FROM reading_photos p JOIN readings r ON r.id=p.reading_id JOIN meters m ON m.id=r.meter_id WHERE p.id=? AND m.user_id=?', [$id, $u['id']]);
        if (!$p) {
            abort(404);
        }
        ImageProcessor::delete((int) $u['id'], $p['file_name']);
        Db::exec('DELETE FROM reading_photos WHERE id=?', [$id]);
        flash('ok', 'Foto gelöscht.');
        redirect('readings/' . $p['rid'] . '/edit');
    }

    // ---------- intern ----------
    private function meter(array $u, int $id): array
    {
        $m = Repo::meter((int) $u['id'], $id);
        if (!$m) {
            abort(404, 'Zähler nicht gefunden.');
        }
        return $m;
    }

    private function reading(array $u, int $id): array
    {
        $r = Repo::reading((int) $u['id'], $id);
        if (!$r) {
            abort(404, 'Ablesung nicht gefunden.');
        }
        return $r;
    }

    private function formAgain(array $m, ?array $reading, string $err): void
    {
        flash('error', $err);
        $vals = [];
        foreach ($m['registers'] as $reg) {
            $v = parse_kwh($_POST['val'][$reg['id']] ?? '');
            if ($v !== null) {
                $vals[$reg['id']] = $v;
            }
        }
        view('readings/form', [
            'title' => $reading ? 'Ablesung bearbeiten' : 'Ablesung erfassen', 'active' => 'meters', 'meter' => $m, 'reading' => $reading, 'prev' => null,
            'form' => ['read_at' => (string) ($_POST['read_at'] ?? input_date()), 'reason' => $this->reason(), 'note' => (string) ($_POST['note'] ?? ''), 'values' => $vals, 'raw' => $_POST['val'] ?? [], 'force' => !empty($_POST['force'])],
        ]);
    }

    /** @return array{0: ?string, 1: array, 2: string, 3: ?string} */
    private function input(array $m, ?int $ignoreReadingId): array
    {
        $day = parse_date((string) ($_POST['read_at'] ?? ''));
        $readAt = $day ? $day . ' 00:00:00' : null;   // Ablesung gilt für den Tag (ohne Uhrzeit)
        $note = mb_trim_len((string) ($_POST['note'] ?? ''), 1000);
        if (!$readAt) {
            return [null, [], $note, 'Bitte das Datum der Ablesung angeben.'];
        }
        if ($day > date('Y-m-d')) {
            return [null, [], $note, 'Das Ablesedatum liegt in der Zukunft.'];
        }
        $values = [];
        foreach ($m['registers'] as $reg) {
            $raw = $_POST['val'][$reg['id']] ?? '';
            if (trim((string) $raw) === '') {
                continue;
            }
            $v = parse_kwh($raw);
            if ($v === null || $v < 0 || $v > 999999999) {
                return [null, [], $note, 'Ungültiger Wert bei „' . Repo::registerTitle($reg) . '“.'];
            }
            $values[$reg['id']] = $v;
        }
        if (!$values) {
            return [null, [], $note, 'Bitte mindestens einen Zählerstand eingeben.'];
        }
        if (empty($_POST['force']) && $this->reason() !== 'meter_change') {
            $ts = strtotime($readAt);
            $others = array_values(array_filter(Repo::readings($m['id']), static fn($o) => $o['id'] !== $ignoreReadingId));
            foreach ($values as $regId => $v) {
                $reg = array_values(array_filter($m['registers'], static fn($x) => $x['id'] === $regId))[0];
                $prev = null;
                $next = null;
                foreach ($others as $o) {   // aufsteigend: nächste Nachbarn je Register
                    if (!isset($o['values'][$regId])) {
                        continue;
                    }
                    if ($o['ts'] <= $ts) {
                        $prev = $o;
                    } elseif ($next === null) {
                        $next = $o;
                    }
                }
                if ($prev && $prev['values'][$regId] > $v) {
                    return [null, [], $note, 'Plausibilität: „' . Repo::registerTitle($reg) . '“ ist niedriger als bei der Ablesung vom ' . date('d.m.Y', $prev['ts'])
                        . ' (' . nf($prev['values'][$regId], 0) . '). Bei Zählerwechsel bitte den Anlass „Zählerwechsel“ wählen oder „Trotzdem speichern“ ankreuzen.'];
                }
                if ($next && $next['reason'] !== 'meter_change' && $next['values'][$regId] < $v) {
                    return [null, [], $note, 'Plausibilität: „' . Repo::registerTitle($reg) . '“ ist höher als bei der späteren Ablesung vom ' . date('d.m.Y', $next['ts'])
                        . ' (' . nf($next['values'][$regId], 0) . '). Bitte prüfen oder „Trotzdem speichern“ ankreuzen.'];
                }
            }
        }
        return [$readAt, $values, $note, null];
    }

    private function reason(): string
    {
        $r = (string) ($_POST['reason'] ?? 'regular');
        return isset(reading_reasons()[$r]) ? $r : 'regular';
    }

    private function savePhotos(int $uid, int $readingId): void
    {
        if (empty($_FILES['photos'])) {
            return;
        }
        $errors = [];
        foreach (ImageProcessor::handleUploads($uid, $_FILES['photos'], $errors) as [$name, $size]) {
            Db::insert('INSERT INTO reading_photos (reading_id, file_name, size_bytes, created_at) VALUES (?,?,?,NOW())', [$readingId, $name, $size]);
        }
        foreach ($errors as $e) {
            flash('warn', $e);
        }
    }
}
