<?php
declare(strict_types=1);

final class ReadingController
{
    public function create(int $meterId): void
    {
        $u = Auth::requireUser();
        $m = $this->meter($u, $meterId, true);
        $all = Repo::readings($meterId);
        view('readings/form', [
            'title' => 'Ablesung erfassen', 'active' => 'meters', 'meter' => $m, 'reading' => null,
            'prev' => $all ? end($all) : null, 'form' => ['read_at' => input_date(), 'reason' => 'regular', 'note' => '', 'values' => [], 'notify' => (int) ($u['notify_reading'] ?? 1) === 1],
        ]);
    }

    public function store(int $meterId): void
    {
        $u = Auth::requireUser();
        $m = $this->meter($u, $meterId, true);
        [$readAt, $values, $note, $err] = $this->input($m, null);
        $reason = $this->reason();
        if ($err) {
            $this->formAgain($m, null, $err);
            return;
        }
        $rid = Db::tx(function () use ($meterId, $readAt, $reason, $note, $values, $u) {
            $rid = Db::insert('INSERT INTO readings (meter_id, read_at, reason, note_enc, created_by, created_at) VALUES (?,?,?,?,?,NOW())', [$meterId, $readAt, $reason, Crypto::enc($note), $u['id']]);
            foreach ($values as $regId => $v) {
                Db::insert('INSERT INTO reading_values (reading_id, register_id, value_enc) VALUES (?,?,?)', [$rid, $regId, Crypto::enc((string) $v)]);
            }
            return $rid;
        });
        $this->savePhotos((int) $m['user_id'], $rid);
        $msg = 'Ablesung gespeichert.';
        if ($m['access'] !== 'owner') {
            $this->notifyOwner($u, $m, $readAt, $values);
        }
        if (isset($_POST['notify'])) {
            $msg .= $this->notify($u, $m, $rid, $readAt, $reason, $note, $values) ? ' Eine Kopie wurde dir per E-Mail gesendet.' : ' Die E-Mail konnte leider nicht gesendet werden.';
        }
        flash('ok', $msg);
        redirect('meters/' . $meterId);
    }

    public function edit(int $id): void
    {
        $u = Auth::requireUser();
        $r = $this->reading($u, $id);
        $m = $this->editable($u, $r);
        view('readings/form', [
            'title' => 'Ablesung bearbeiten', 'active' => 'meters', 'meter' => $m, 'reading' => $r, 'prev' => null,
            'form' => ['read_at' => input_date($r['read_at']), 'reason' => $r['reason'], 'note' => $r['note'], 'values' => $r['values']],
        ]);
    }

    public function update(int $id): void
    {
        $u = Auth::requireUser();
        $r = $this->reading($u, $id);
        $m = $this->editable($u, $r);
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
        $this->savePhotos((int) $m['user_id'], $id);
        flash('ok', 'Ablesung aktualisiert.');
        redirect('meters/' . $m['id']);
    }

    public function delete(int $id): void
    {
        $u = Auth::requireUser();
        $r = $this->reading($u, $id);
        $m = $this->editable($u, $r);
        foreach (Db::all('SELECT file_name FROM reading_photos WHERE reading_id=?', [$id]) as $p) {
            ImageProcessor::delete((int) $m['user_id'], $p['file_name']);
        }
        Db::exec('DELETE FROM readings WHERE id=?', [$id]);
        flash('ok', 'Ablesung gelöscht.');
        redirect('meters/' . $r['meter_id']);
    }

    public function photo(int $id): void
    {
        $u = Auth::requireUser();
        $p = Db::one('SELECT p.*, m.user_id AS owner_id FROM reading_photos p JOIN readings r ON r.id=p.reading_id JOIN meters m ON m.id=r.meter_id WHERE p.id=? AND (m.user_id=? OR EXISTS (SELECT 1 FROM meter_shares s WHERE s.meter_id=m.id AND s.user_id=? AND (s.access_until IS NULL OR s.access_until > NOW())))', [$id, $u['id'], $u['id']]);
        $bytes = $p ? ImageProcessor::read((int) $p['owner_id'], $p['file_name']) : null;
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
        $u = Auth::requireUser();
        $p = Db::one('SELECT p.*, r.id AS rid FROM reading_photos p JOIN readings r ON r.id=p.reading_id JOIN meters m ON m.id=r.meter_id WHERE p.id=? AND (m.user_id=? OR EXISTS (SELECT 1 FROM meter_shares s WHERE s.meter_id=m.id AND s.user_id=? AND (s.access_until IS NULL OR s.access_until > NOW())))', [$id, $u['id'], $u['id']]);
        if (!$p) {
            abort(404);
        }
        $m = $this->editable($u, $this->reading($u, (int) $p['rid']));
        ImageProcessor::delete((int) $m['user_id'], $p['file_name']);
        Db::exec('DELETE FROM reading_photos WHERE id=?', [$id]);
        flash('ok', 'Foto gelöscht.');
        redirect('readings/' . $p['rid'] . '/edit');
    }

    // ---------- intern ----------
    /** Zähler mit Zugriff (eigen oder freigegeben); $write = Ablesungen erfassen erforderlich. */
    private function meter(array $u, int $id, bool $write = false): array
    {
        $m = Repo::meterShared((int) $u['id'], $id);
        if (!$m) {
            abort(404, 'Zähler nicht gefunden.');
        }
        if ($write && $m['access'] === 'read') {
            abort(403, 'Dieser Zähler ist nur zum Ansehen für dich freigegeben.');
        }
        return $m;
    }

    /** Zähler einer Ablesung, die der Benutzer ändern darf: Eigentümer immer, Mitnutzer nur eigene Einträge. */
    private function editable(array $u, array $reading): array
    {
        $m = $this->meter($u, (int) $reading['meter_id'], true);
        if ($m['access'] !== 'owner' && (int) ($reading['created_by'] ?? 0) !== (int) $u['id']) {
            abort(403, 'Du kannst nur Ablesungen ändern, die du selbst erfasst hast.');
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
            'form' => ['read_at' => (string) ($_POST['read_at'] ?? input_date()), 'reason' => $this->reason(), 'note' => (string) ($_POST['note'] ?? ''), 'values' => $vals, 'raw' => $_POST['val'] ?? [], 'force' => !empty($_POST['force']), 'notify' => isset($_POST['notify'])],
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
            $warn = $this->plausibility($m, $readAt, $values, $ignoreReadingId);
            if ($warn) {
                return [null, [], $note, 'Plausibilität: ' . implode(' ', $warn) . ' Bitte prüfen oder die Speicherung bestätigen.'];
            }
        }
        return [$readAt, $values, $note, null];
    }

    /** Unplausible Werte im Vergleich zu den Nachbar-Ablesungen (Zählerstand darf nicht sinken). @return list<string> */
    private function plausibility(array $m, string $readAt, array $values, ?int $ignoreReadingId): array
    {
        $out = [];
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
                $out[] = '„' . Repo::registerTitle($reg) . '“ (' . nf($v, 0) . ' kWh) ist niedriger als bei der Ablesung vom ' . date('d.m.Y', $prev['ts'])
                    . ' (' . nf($prev['values'][$regId], 0) . ' kWh).';
            }
            if ($next && $next['reason'] !== 'meter_change' && $next['values'][$regId] < $v) {
                $out[] = '„' . Repo::registerTitle($reg) . '“ (' . nf($v, 0) . ' kWh) ist höher als bei der späteren Ablesung vom ' . date('d.m.Y', $next['ts'])
                    . ' (' . nf($next['values'][$regId], 0) . ' kWh).';
            }
        }
        return $out;
    }

    /** AJAX-Vorabprüfung: liefert Plausibilitätswarnungen, ohne zu speichern. */
    public function check(int $meterId): void
    {
        $u = Auth::requireUser();
        $m = $this->meter($u, $meterId, true);
        $ignore = (int) ($_POST['ignore'] ?? 0);
        $ignore = $ignore > 0 && Repo::reading((int) $u['id'], $ignore) ? $ignore : null;
        $day = parse_date((string) ($_POST['read_at'] ?? ''));
        $values = [];
        foreach ($m['registers'] as $reg) {
            $v = parse_kwh($_POST['val'][$reg['id']] ?? '');
            if ($v !== null && $v >= 0) {
                $values[$reg['id']] = $v;
            }
        }
        $warnings = [];
        if ($day && $values && $this->reason() !== 'meter_change') {
            $warnings = $this->plausibility($m, $day . ' 00:00:00', $values, $ignore);
        }
        json_response(['warnings' => $warnings]);
    }

    private function reason(): string
    {
        $r = (string) ($_POST['reason'] ?? 'regular');
        return isset(reading_reasons()[$r]) ? $r : 'regular';
    }

    /** Schickt dem Benutzer den neuen Zählerstand samt Fotos als Erinnerung, ihn beim Netzbetreiber einzutragen. */
    private function notify(array $u, array $m, int $rid, string $readAt, string $reason, string $note, array $values): bool
    {
        try {
            $label = reading_reasons()[$reason][0] ?? 'Ablesung';
            $mName = mask_tail($m['name'], 4);      // Datenschutz: Zählernummer / Adresse in der Mail teilweise geschwärzt
            $mLoc = mask_words((string) $m['location']);
            $date = fmt_date($readAt);
            $rows = '';
            foreach ($m['registers'] as $reg) {
                if (!isset($values[$reg['id']])) {
                    continue;
                }
                $v = nf((float) $values[$reg['id']], 0);
                $t = Repo::registerTitle($reg);
                $rows .= '<li>' . e($t) . ': <b>' . e($v) . ' kWh</b></li>';
            }
            $attach = [];
            $total = 0;
            foreach (Db::all('SELECT id, file_name FROM reading_photos WHERE reading_id=? ORDER BY id', [$rid]) as $i => $p) {
                $bytes = ImageProcessor::read((int) $m['user_id'], $p['file_name']);
                if ($bytes === null || $total + strlen($bytes) > 15 * 1024 * 1024) {
                    continue;
                }
                $total += strlen($bytes);
                $attach[] = ['zaehlerstand-' . $readAt . '-' . ($i + 1) . '.jpg', 'image/jpeg', $bytes];
            }
            $body = '<p>Hallo ' . e($u['name']) . ',</p>'
                . '<p>du hast soeben einen neuen Zählerstand erfasst. Bitte trage ihn zusätzlich bei deinem <b>Netzbetreiber</b> ein bzw. melde ihn dort'
                . ($attach ? ' – die Fotos des Zählers findest du im Anhang.' : '.') . '</p>'
                . '<p style="margin:0 0 4px"><b>' . e($mName) . '</b>' . ($mLoc !== '' ? ' · ' . e($mLoc) : '') . '<br>'
                . 'Ablesung vom ' . e($date) . ' · ' . e($label) . '</p>'
                . '<ul>' . $rows . '</ul>'
                . ($note !== '' ? '<p style="color:#6b7785">Notiz: ' . e($note) . '</p>' : '')
                . Mailer::button(abs_url('meters/' . $m['id']), 'Zähler in meterio öffnen')
                . '<p style="font-size:12px;color:#6b7785">Die Voreinstellung für diese E-Mail kannst du in deinem Profil ändern.</p>';
            $html = Mailer::wrap('Neuer Zählerstand ⚡', $body);
            return Mailer::send($u['email'], 'Neuer Zählerstand ' . $mName . ' (' . $date . ')', $html, null, $attach);
        } catch (Throwable $e) {
            error_log('[meterio notify] ' . $e->getMessage());
            return false;
        }
    }

    /** Teilt dem Eigentümer mit, dass jemand mit Freigabe einen Zählerstand erfasst hat. */
    private function notifyOwner(array $u, array $m, string $readAt, array $values): void
    {
        try {
            $owner = Auth::findById((int) $m['user_id']);
            if (!$owner || empty($owner['email'])) {
                return;
            }
            $rows = '';
            foreach ($m['registers'] as $reg) {
                if (isset($values[$reg['id']])) {
                    $rows .= '<li>' . e(Repo::registerTitle($reg)) . ': <b>' . e(nf((float) $values[$reg['id']], 0)) . ' kWh</b></li>';
                }
            }
            $mName = mask_tail($m['name'], 4);
            $body = '<p>Hallo ' . e($owner['name']) . ',</p><p><b>' . e($u['name']) . '</b> hat für deinen Zähler <b>' . e($mName) . '</b> einen neuen Zählerstand erfasst (Ablesung vom ' . e(fmt_date($readAt)) . '):</p>'
                . '<ul>' . $rows . '</ul>' . Mailer::button(abs_url('meters/' . $m['id']), 'Zähler in meterio öffnen');
            Mailer::send($owner['email'], 'Neuer Zählerstand von ' . $u['name'], Mailer::wrap('Neuer Zählerstand ⚡', $body));
        } catch (Throwable $e) {
            error_log('[meterio notifyOwner] ' . $e->getMessage());
        }
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
