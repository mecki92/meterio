<?php
declare(strict_types=1);

final class DashboardController
{
    public function home(): void
    {
        redirect(Auth::user() ? Auth::home() : 'login');
    }

    public function index(): void
    {
        if ((Auth::user()['role'] ?? '') === 'admin') {
            redirect('admin/users');
        }
        $u = Auth::requireUser();
        $meters = Repo::meters((int) $u['id']);
        $want = (int) ($_GET['meter'] ?? 0);
        $sel = null;
        foreach ($meters as $m) {
            if ($m['id'] === $want) {
                $sel = $m;
            }
        }
        $sel ??= $meters[0] ?? null;
        $cards = [];
        $years = [];
        if ($sel) {
            $readings = Repo::readings($sel['id']);
            $series = Repo::series($readings);
            foreach (Repo::contracts((int) $u['id']) as $c) {
                $today = date('Y-m-d');
                if ($c['meter_id'] !== $sel['id'] || $c['start_date'] > $today || ($c['end_date'] && $c['end_date'] < $today)) {
                    continue;   // nur aktive Verträge
                }
                $cards[] = ['c' => $c, 'sum' => Calc::summary($c, $sel['registers'], $series, Repo::payments($c['id']))];
                $years[$c['id']] = array_map(static fn($y) => ['index' => $y['index'], 'label' => $y['label']], Calc::contractYears($c, time()));
            }
            $last = $readings ? end($readings) : null;
        }
        // Aktiver Vertrag für Kostenchart: laufender bevorzugt
        $activeContract = null;
        foreach ($cards as $cd) {
            $c = $cd['c'];
            if ($c['start_date'] <= date('Y-m-d') && (!$c['end_date'] || $c['end_date'] >= date('Y-m-d'))) {
                $activeContract = $c;
                break;
            }
        }
        $activeContract ??= $cards[0]['c'] ?? null;

        view('dashboard/index', [
            'title' => 'Dashboard', 'active' => 'dashboard', 'meters' => $meters, 'sel' => $sel, 'cards' => $cards,
            'years' => $years, 'activeContract' => $activeContract, 'last' => $last ?? null,
            'expiring' => Repo::expiringShares((int) $u['id']),
        ]);
    }

    /** JSON für Chart.js: ?type=usage&meter=ID&range=month|year|contract[&ref=..&contract=ID&cy=N] oder ?type=cost&contract=ID[&cy=N] */
    public function chart(): void
    {
        $u = Auth::user() ?? json_response(['error' => 'unauthorized'], 401);
        $type = (string) ($_GET['type'] ?? 'usage');
        if ($type === 'cost') {
            json_response($this->cost($u));
        }
        json_response($this->usage($u));
    }

    private function usage(array $u): array
    {
        $m = Repo::meter((int) $u['id'], (int) ($_GET['meter'] ?? 0)) ?? json_response(['error' => 'meter'], 404);
        $series = Repo::series(Repo::readings($m['id']));
        $range = (string) ($_GET['range'] ?? 'year');
        $months = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
        $buckets = [];
        $title = '';

        if ($range === 'month') {
            $ref = preg_match('/^\d{4}-\d{2}$/', (string) ($_GET['ref'] ?? '')) ? $_GET['ref'] : date('Y-m');
            $first = new DateTimeImmutable($ref . '-01');
            $days = (int) $first->format('t');
            for ($d = 0; $d < $days; $d++) {
                $a = $first->modify("+$d days");
                $buckets[] = [$a->format('j.'), $a->getTimestamp(), $a->modify('+1 day')->getTimestamp()];
            }
            $title = $months[(int) $first->format('n') - 1] . ' ' . $first->format('Y') . ' (pro Tag)';
        } elseif ($range === 'contract') {
            $c = Repo::contract((int) $u['id'], (int) ($_GET['contract'] ?? 0)) ?? json_response(['error' => 'contract'], 404);
            $yearsList = Calc::contractYears($c, time());
            $cy = (int) ($_GET['cy'] ?? count($yearsList));
            $yr = null;
            foreach ($yearsList as $y) {
                if ($y['index'] === $cy) {
                    $yr = $y;
                }
            }
            $yr ??= end($yearsList);
            $s = new DateTimeImmutable('@' . $yr['start']);
            $s = $s->setTimezone(new DateTimeZone(date_default_timezone_get()));
            for ($i = 0; $s->modify("+$i months")->getTimestamp() < $yr['end_ex']; $i++) {
                $a = $s->modify("+$i months");
                $b = min($s->modify('+' . ($i + 1) . ' months')->getTimestamp(), $yr['end_ex']);
                $buckets[] = [$months[(int) $a->format('n') - 1] . ' ' . $a->format('y'), $a->getTimestamp(), $b];
            }
            $title = period_word($c) . ' ' . $yr['label'];
        } else {
            $year = (int) ($_GET['ref'] ?? date('Y'));
            $year = ($year >= 2000 && $year <= 2100) ? $year : (int) date('Y');
            for ($mo = 1; $mo <= 12; $mo++) {
                $a = new DateTimeImmutable(sprintf('%d-%02d-01', $year, $mo));
                $buckets[] = [$months[$mo - 1], $a->getTimestamp(), $a->modify('+1 month')->getTimestamp()];
            }
            $title = "Jahr $year (pro Monat)";
        }

        $datasets = [];
        foreach ($m['registers'] as $reg) {
            $data = [];
            $any = false;
            foreach ($buckets as [, $a, $b]) {
                [$k, $cov] = Calc::consumption($series[$reg['id']] ?? [], $a, $b);
                $data[] = $cov > 0 ? round($k, 2) : null;
                $any = $any || $cov > 0;
            }
            $datasets[] = ['label' => Repo::registerTitle($reg), 'tariff' => $reg['tariff'], 'data' => $data, 'any' => $any];
        }
        // Soll-Durchschnitt pro Monat aus dem vertraglichen Jahresverbrauch
        $target = null;
        $targetLabel = '';
        $contract = Repo::contract((int) $u['id'], (int) ($_GET['contract'] ?? 0));
        if (!$contract || $contract['meter_id'] !== $m['id']) {
            $contract = null;
            foreach (Repo::contracts((int) $u['id']) as $cc) {   // neuester Vertrag dieses Zählers
                if ($cc['meter_id'] === $m['id']) {
                    $contract = $cc;
                    break;
                }
            }
        }
        $annual = $contract['data']['annual_kwh'] ?? null;
        if ($annual && $range !== 'month') {
            $target = round((float) $annual / 12, 1);
            $targetLabel = 'Soll-Ø laut Vertrag (' . nf((float) $annual, 0) . ' kWh/Jahr ≈ ' . nf($target, 0) . ' kWh/Monat)';
        }
        $current = null;
        $now = time();
        foreach ($buckets as $i => [, $a, $b]) {
            if ($a <= $now && $now < $b) {
                $current = $i;
            }
        }
        return ['title' => $title, 'unit' => 'kWh', 'labels' => array_column($buckets, 0), 'datasets' => $datasets,
                'target' => $target, 'target_label' => $targetLabel, 'current' => $current];
    }

    private function cost(array $u): array
    {
        $c = Repo::contract((int) $u['id'], (int) ($_GET['contract'] ?? 0)) ?? json_response(['error' => 'contract'], 404);
        $m = Repo::meter((int) $u['id'], $c['meter_id']) ?? json_response(['error' => 'meter'], 404);
        $series = Repo::series(Repo::readings($m['id']));
        $payments = Repo::payments($c['id']);

        $yearsList = Calc::contractYears($c, time());
        $cy = (int) ($_GET['cy'] ?? count($yearsList));
        $yr = null;
        foreach ($yearsList as $y) {
            if ($y['index'] === $cy) {
                $yr = $y;
            }
        }
        $yr ??= end($yearsList);
        $refNow = min(time(), $yr['end_ex'] - 1);
        $sum = Calc::summary($c, $m['registers'], $series, $payments, $refNow);
        $now = time();
        $s = (new DateTimeImmutable('@' . $yr['start']))->setTimezone(new DateTimeZone(date_default_timezone_get()));

        $labels = [];
        $ts = [];
        for ($i = 0; $s->modify("+$i months")->getTimestamp() < $yr['end_ex']; $i++) {
            $t = $s->modify("+$i months")->getTimestamp();
            $ts[] = $t;
            $labels[] = date('d.m.y', $t);
        }
        $ts[] = $yr['end_ex'];
        $labels[] = date('d.m.y', $yr['end_ex']);

        $accrued = [];
        $paidCum = [];
        $expected = [];
        $forecast = [];
        $anchorIdx = null;
        $paidTotal = 0.0;
        $bonusList = Calc::bonusPayments($c, $yr['index']);
        $advance = Calc::advanceAt($c, time());
        foreach ($ts as $i => $t) {
            if ($sum['has_data'] && $t <= $sum['accrual_to']) {
                $accrued[$i] = round(Calc::cost($c, $m['registers'], $series, $yr['start'], $t, $yr['index'])['total'], 2);
                $anchorIdx = $i;
            } else {
                $accrued[$i] = null;
            }
            $p = 0.0;
            foreach ($payments as $pay) {
                if ($pay['data']['status'] === 'paid' && Calc::paymentPeriod($c, $pay) === $yr['index'] && max(strtotime($pay['paid_on']), $yr['start']) < $t + 86400) {
                    $p += (float) $pay['data']['amount'];
                }
            }
            foreach ($bonusList as $bp) {   // Bonuszahlung zählt ab ihrem Buchungstag
                if ($bp['ts'] < $t + 86400) {
                    $p += $bp['amount'];
                }
            }
            if ($t <= $now) {
                $paidCum[$i] = round($p, 2);
                $paidTotal = $p;
                $expected[$i] = null;
            } else {
                $paidCum[$i] = null;
                $expected[$i] = null;
            }
        }
        // Erwartete Zahlungen ab dem letzten bekannten Punkt
        $lastPaidIdx = null;
        foreach ($paidCum as $i => $v) {
            if ($v !== null) {
                $lastPaidIdx = $i;
            }
        }
        if ($advance !== null && $lastPaidIdx !== null) {
            $expected[$lastPaidIdx] = $paidCum[$lastPaidIdx];
            for ($i = $lastPaidIdx + 1; $i < count($ts); $i++) {
                $bonusAdd = 0.0;
                foreach ($bonusList as $bp) {   // noch nicht gebuchte Boni, die bis zu diesem Punkt fällig sind
                    if ($bp['ts'] < $ts[$i] + 86400 && $bp['ts'] >= $ts[$lastPaidIdx] + 86400) {
                        $bonusAdd += $bp['amount'];
                    }
                }
                $advCum = 0.0;
                for ($j = $lastPaidIdx + 1; $j <= $i; $j++) {   // Abschläge ggf. mit geändertem Betrag ab Stichtag
                    $advCum += Calc::advanceAt($c, $ts[$j]) ?? 0.0;
                }
                $expected[$i] = round($paidCum[$lastPaidIdx] + $advCum + $bonusAdd, 2);
            }
        }
        // Prognose-Linie
        for ($i = 0; $i < count($ts); $i++) {
            $forecast[$i] = null;
        }
        if ($anchorIdx !== null && $sum['forecast_cost'] !== null) {
            $t0 = $ts[$anchorIdx];
            $t1 = $yr['end_ex'];
            for ($i = $anchorIdx; $i < count($ts); $i++) {
                $forecast[$i] = round($accrued[$anchorIdx] + ($sum['forecast_cost'] - $accrued[$anchorIdx]) * (($ts[$i] - $t0) / max(1, $t1 - $t0)), 2);
            }
        }
        return [
            'title' => 'Kosten vs. Abschläge – ' . period_word($c) . ' ' . $yr['label'], 'unit' => '€', 'labels' => $labels,
            'datasets' => [
                ['label' => 'Aufgelaufene Realkosten', 'data' => $accrued],
                ['label' => 'Prognose Realkosten', 'data' => $forecast, 'dashed' => true],
                ['label' => 'Gezahlte Abschläge (kumuliert)', 'data' => $paidCum],
                ['label' => 'Erwartete Abschläge', 'data' => $expected, 'dashed' => true],
            ],
        ];
    }
}
