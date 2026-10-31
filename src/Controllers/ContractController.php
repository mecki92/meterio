<?php
declare(strict_types=1);

final class ContractController
{
    public function index(): void
    {
        $u = Auth::require();
        $meters = [];
        foreach (Repo::meters((int) $u['id'], true) as $m) {
            $meters[$m['id']] = $m;
        }
        $rows = [];
        foreach (Repo::contracts((int) $u['id']) as $c) {
            $m = $meters[$c['meter_id']] ?? null;
            $rows[] = ['c' => $c, 'meter' => $m, 'sum' => $m ? $this->summary($c, $m) : null];
        }
        view('contracts/index', ['title' => 'Verträge', 'active' => 'contracts', 'rows' => $rows]);
    }

    public function create(): void
    {
        $u = Auth::require();
        $meters = Repo::meters((int) $u['id']);
        if (!$meters) {
            flash('warn', 'Lege zuerst einen Zähler an.');
            redirect('meters/new');
        }
        view('contracts/form', ['title' => 'Neuer Vertrag', 'active' => 'contracts', 'meters' => $meters, 'c' => $this->blank((int) ($_GET['meter'] ?? 0) ?: $meters[0]['id'])]);
    }

    public function store(): void
    {
        $u = Auth::require();
        [$row, $err] = $this->input($u);
        if ($err) {
            flash('error', $err);
            view('contracts/form', ['title' => 'Neuer Vertrag', 'active' => 'contracts', 'meters' => Repo::meters((int) $u['id']), 'c' => $row + ['id' => 0]]);
            return;
        }
        $id = Db::insert('INSERT INTO contracts (user_id, meter_id, start_date, end_date, data_enc, created_at) VALUES (?,?,?,?,?,NOW())',
            [$u['id'], $row['meter_id'], $row['start_date'], $row['end_date'], Crypto::encJson($row['data'])]);
        flash('ok', 'Vertrag gespeichert.');
        redirect('contracts/' . $id);
    }

    public function show(int $id): void
    {
        $u = Auth::require();
        $c = $this->own($u, $id);
        $m = Repo::meter((int) $u['id'], $c['meter_id']);
        $payments = Repo::payments($id);
        $edit = isset($_GET['edit']) ? Repo::payment((int) $u['id'], (int) $_GET['edit']) : null;
        if ($edit && $edit['contract_id'] !== $id) {
            $edit = null;
        }
        view('contracts/show', [
            'title' => 'Vertrag ' . ($c['data']['provider'] ?: '#' . $id), 'active' => 'contracts', 'c' => $c, 'meter' => $m,
            'payments' => $payments, 'sum' => $m ? $this->summary($c, $m) : null, 'edit' => $edit,
        ]);
    }

    public function edit(int $id): void
    {
        $u = Auth::require();
        view('contracts/form', ['title' => 'Vertrag bearbeiten', 'active' => 'contracts', 'meters' => Repo::meters((int) $u['id']), 'c' => $this->own($u, $id)]);
    }

    public function update(int $id): void
    {
        $u = Auth::require();
        $this->own($u, $id);
        [$row, $err] = $this->input($u);
        if ($err) {
            flash('error', $err);
            view('contracts/form', ['title' => 'Vertrag bearbeiten', 'active' => 'contracts', 'meters' => Repo::meters((int) $u['id']), 'c' => $row + ['id' => $id]]);
            return;
        }
        Db::exec('UPDATE contracts SET meter_id=?, start_date=?, end_date=?, data_enc=? WHERE id=? AND user_id=?',
            [$row['meter_id'], $row['start_date'], $row['end_date'], Crypto::encJson($row['data']), $id, $u['id']]);
        flash('ok', 'Vertrag aktualisiert.');
        redirect('contracts/' . $id);
    }

    public function delete(int $id): void
    {
        $u = Auth::require();
        $this->own($u, $id);
        Db::exec('DELETE FROM contracts WHERE id=? AND user_id=?', [$id, $u['id']]);
        flash('ok', 'Vertrag samt Zahlungen gelöscht.');
        redirect('contracts');
    }

    // ---------- Zahlungen ----------
    public function paymentStore(int $contractId): void
    {
        $u = Auth::require();
        $this->own($u, $contractId);
        [$date, $data, $err] = $this->paymentInput();
        if ($err) {
            flash('error', $err);
            redirect('contracts/' . $contractId);
        }
        Db::insert('INSERT INTO payments (contract_id, paid_on, data_enc, created_at) VALUES (?,?,?,NOW())', [$contractId, $date, Crypto::encJson($data)]);
        flash('ok', 'Zahlung erfasst.');
        redirect('contracts/' . $contractId);
    }

    public function paymentUpdate(int $id): void
    {
        $u = Auth::require();
        $p = Repo::payment((int) $u['id'], $id) ?? abort(404);
        [$date, $data, $err] = $this->paymentInput();
        if ($err) {
            flash('error', $err);
            redirect('contracts/' . $p['contract_id'] . '?edit=' . $id);
        }
        Db::exec('UPDATE payments SET paid_on=?, data_enc=? WHERE id=?', [$date, Crypto::encJson($data), $id]);
        flash('ok', 'Zahlung aktualisiert.');
        redirect('contracts/' . $p['contract_id']);
    }

    public function paymentDelete(int $id): void
    {
        $u = Auth::require();
        $p = Repo::payment((int) $u['id'], $id) ?? abort(404);
        Db::exec('DELETE FROM payments WHERE id=?', [$id]);
        flash('ok', 'Zahlung gelöscht.');
        redirect('contracts/' . $p['contract_id']);
    }

    // ---------- intern ----------
    public static function summaryFor(array $c, array $meter): array
    {
        $readings = Repo::readings($meter['id']);
        return Calc::summary($c, $meter['registers'], Repo::series($readings), Repo::payments($c['id']));
    }

    private function summary(array $c, array $m): array
    {
        return self::summaryFor($c, $m);
    }

    private function own(array $u, int $id): array
    {
        return Repo::contract((int) $u['id'], $id) ?? abort(404, 'Vertrag nicht gefunden.');
    }

    private function blank(int $meterId): array
    {
        return ['id' => 0, 'meter_id' => $meterId, 'start_date' => date('Y-m-d'), 'end_date' => null, 'data' => [
            'provider' => '', 'contract_no' => '', 'base_fee' => null, 'base_period' => 'month', 'price_et' => null, 'price_ht' => null,
            'price_nt' => null, 'feedin' => null, 'annual_kwh' => null, 'monthly_advance' => null, 'note' => '',
            'min_term_months' => null, 'credits' => []]];
    }

    /** @return array{0: array, 1: ?string} */
    private function input(array $u): array
    {
        $meterId = (int) ($_POST['meter_id'] ?? 0);
        $start = parse_date((string) ($_POST['start_date'] ?? ''));
        $endRaw = trim((string) ($_POST['end_date'] ?? ''));
        $end = $endRaw === '' ? null : parse_date($endRaw);
        $num = static fn(string $k) => parse_num($_POST[$k] ?? '');
        $data = [
            'provider' => mb_trim_len((string) ($_POST['provider'] ?? ''), 120),
            'contract_no' => mb_trim_len((string) ($_POST['contract_no'] ?? ''), 60),
            'base_fee' => $num('base_fee'), 'base_period' => ($_POST['base_period'] ?? '') === 'year' ? 'year' : 'month',
            'price_et' => $num('price_et'), 'price_ht' => $num('price_ht'), 'price_nt' => $num('price_nt'), 'feedin' => $num('feedin'),
            'annual_kwh' => $num('annual_kwh'), 'monthly_advance' => $num('monthly_advance'),
            'note' => mb_trim_len((string) ($_POST['note'] ?? ''), 1000),
            'min_term_months' => null, 'credits' => [],
        ];
        $mt = parse_num($_POST['min_term_months'] ?? '');
        $data['min_term_months'] = $mt !== null && $mt > 0 ? (int) round($mt) : null;
        $creditErr = null;
        foreach ((array) ($_POST['credit'] ?? []) as $cr) {
            if (!is_array($cr)) {
                continue;
            }
            $amt = parse_num($cr['amount'] ?? '');
            $mo = (int) ($cr['months'] ?? 0);
            $label = mb_trim_len((string) ($cr['label'] ?? ''), 80);
            if ($amt === null && $label === '') {
                continue; // leere Zeile
            }
            if ($amt === null || $amt <= 0 || $amt > 1000000 || $mo < 1 || $mo > 120) {
                $creditErr = 'Gutschrift: bitte die Monate (1–120) und einen positiven Betrag angeben.';
                continue;
            }
            $data['credits'][] = ['months' => $mo, 'amount' => $amt, 'label' => $label];
        }
        usort($data['credits'], static fn($a, $b) => $a['months'] <=> $b['months']);
        $row = ['meter_id' => $meterId, 'start_date' => $start ?? (string) ($_POST['start_date'] ?? ''), 'end_date' => $end, 'data' => $data];

        $err = null;
        if (!Repo::meter((int) $u['id'], $meterId)) {
            $err = 'Bitte einen gültigen Zähler wählen.';
        } elseif (!$start) {
            $err = 'Bitte ein gültiges Startdatum angeben.';
        } elseif ($endRaw !== '' && !$end) {
            $err = 'Das Enddatum ist ungültig.';
        } elseif ($end && $end < $start) {
            $err = 'Das Enddatum liegt vor dem Startdatum.';
        } elseif ($mt !== null && ($mt < 0 || $mt > 120)) {
            $err = 'Die Mindestvertragslaufzeit muss zwischen 1 und 120 Monaten liegen.';
        } elseif ($creditErr) {
            $err = $creditErr;
        } else {
            foreach (['base_fee', 'price_et', 'price_ht', 'price_nt', 'feedin', 'annual_kwh', 'monthly_advance'] as $k) {
                if ($data[$k] !== null && ($data[$k] < 0 || $data[$k] > 1000000)) {
                    $err = 'Ungültiger Zahlenwert im Feld „' . $k . '“.';
                }
            }
            if (!$err && $data['price_et'] === null && $data['price_ht'] === null && $data['price_nt'] === null) {
                $err = 'Bitte mindestens einen Arbeitspreis (Eintarif oder HT/NT) angeben.';
            }
        }
        return [$row, $err];
    }

    /** @return array{0: ?string, 1: array, 2: ?string} */
    private function paymentInput(): array
    {
        $date = parse_date((string) ($_POST['paid_on'] ?? ''));
        $amount = parse_num($_POST['amount'] ?? '');
        $status = in_array($_POST['status'] ?? '', ['paid', 'open'], true) ? $_POST['status'] : 'paid';
        $kind = ($_POST['kind'] ?? '') === 'special' ? 'special' : 'advance';
        $data = ['amount' => $amount, 'status' => $status, 'kind' => $kind, 'note' => mb_trim_len((string) ($_POST['note'] ?? ''), 200)];
        if (!$date) {
            return [null, $data, 'Bitte ein gültiges Datum angeben.'];
        }
        if ($amount === null || abs($amount) > 1000000) {
            return [null, $data, 'Bitte einen gültigen Betrag angeben (negativ = Gutschrift/Erstattung).'];
        }
        return [$date, $data, null];
    }
}
