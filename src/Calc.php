<?php
declare(strict_types=1);

/** Verbrauchs-, Kosten- und Prognoseberechnung. Zeiten sind Unix-Timestamps. */
final class Calc
{
    private const BILLED = ['ET' => 'price_et', 'HT' => 'price_ht', 'NT' => 'price_nt'];

    /**
     * Verbrauch einer Registerreihe im Zeitraum [from, to]. Zwischen zwei Ablesungen wird linear verteilt.
     * Rückgang des Zählerstands (Zählerwechsel) zählt als 0.
     * @return array{0: float, 1: int} [kWh, abgedeckte Sekunden]
     */
    public static function consumption(array $s, int $from, int $to): array
    {
        $n = count($s);
        if ($n < 2 || $to <= $from) {
            return [0.0, 0];
        }
        $a = max($from, $s[0][0]);
        $b = min($to, $s[$n - 1][0]);
        if ($b <= $a) {
            return [0.0, 0];
        }
        $sum = 0.0;
        for ($i = 1; $i < $n; $i++) {
            [$t0, $v0] = $s[$i - 1];
            [$t1, $v1] = $s[$i];
            $o0 = max($t0, $a);
            $o1 = min($t1, $b);
            if ($o1 > $o0 && $t1 > $t0 && $v1 > $v0) {
                $sum += ($v1 - $v0) * ($o1 - $o0) / ($t1 - $t0);
            }
        }
        return [$sum, $b - $a];
    }

    /** Aktuelles Vertragsjahr [start, end_ex) zum Zeitpunkt $now. */
    public static function contractYear(array $contract, int $now): array
    {
        $start = new DateTimeImmutable($contract['start_date']);
        $end   = $contract['end_date'] ? new DateTimeImmutable($contract['end_date']) : null;
        $ref = $now;
        if ($end && $ref >= $end->modify('+1 day')->getTimestamp()) {
            $ref = $end->getTimestamp();
        }
        $ref = max($ref, $start->getTimestamp());
        $n = 0;
        while ($n < 60 && $start->modify('+' . ($n + 1) . ' years')->getTimestamp() <= $ref) {
            $n++;
        }
        $ys = $start->modify("+$n years");
        $ye = $start->modify('+' . ($n + 1) . ' years');
        if ($end) {
            $endEx = $end->modify('+1 day');
            if ($endEx < $ye) {
                $ye = $endEx;
            }
        }
        return ['start' => $ys->getTimestamp(), 'end_ex' => $ye->getTimestamp(), 'index' => $n + 1,
                'label' => $ys->format('d.m.Y') . ' – ' . $ye->modify('-1 day')->format('d.m.Y')];
    }

    /** Alle Vertragsjahre als Liste (für Auswahl "Vertragsjahr"). */
    public static function contractYears(array $contract, int $now): array
    {
        $out = [];
        $cur = self::contractYear($contract, $now)['index'];
        for ($i = 1; $i <= $cur; $i++) {
            $ref = (new DateTimeImmutable($contract['start_date']))->modify('+' . ($i - 1) . ' years')->getTimestamp() + 86400;
            $out[] = self::contractYear($contract, $ref);
        }
        return $out;
    }

    /** Vertragsjahr (1 = erstes), in dessen Jahresabrechnung eine Gutschrift nach N Monaten verrechnet wird. */
    public static function creditYearIndex(array $credit): int
    {
        return max(1, (int) ceil(((int) $credit['months']) / 12));
    }

    /** Zeitpunkt, ab dem die Gutschrift gewertet wird (Vertragsbeginn + N Monate). */
    public static function creditTs(array $contract, array $credit): int
    {
        return (new DateTimeImmutable($contract['start_date']))->modify('+' . (int) $credit['months'] . ' months')->getTimestamp();
    }

    /**
     * Summe der einmaligen Gutschriften, die in der Jahresabrechnung des Vertragsjahres verrechnet werden.
     * Mit $upTo nur die bis dahin bereits gewerteten (Vertragsbeginn + N Monate <= $upTo), sonst alle erwarteten.
     */
    public static function creditsFor(array $contract, int $yearIndex, ?int $upTo = null): float
    {
        $sum = 0.0;
        foreach ($contract['data']['credits'] ?? [] as $cr) {
            if (self::creditYearIndex($cr) === $yearIndex && ($upTo === null || self::creditTs($contract, $cr) <= $upTo)) {
                $sum += (float) $cr['amount'];
            }
        }
        return $sum;
    }

    /** Frühestes Vertragsende laut Mindestvertragslaufzeit (letzter Tag) oder null. */
    public static function minTermEnd(array $contract): ?DateTimeImmutable
    {
        $m = $contract['data']['min_term_months'] ?? null;
        if (!$m) {
            return null;
        }
        return (new DateTimeImmutable($contract['start_date']))->modify('+' . (int) $m . ' months')->modify('-1 day');
    }

    /** Kennzahlen eines Zählers aus seinen Ablesungen (aufsteigend sortiert). */
    public static function meterStats(array $meter, array $readings): array
    {
        $n = count($readings);
        $last = $n ? $readings[$n - 1] : null;
        $prev = $n > 1 ? $readings[$n - 2] : null;
        $regs = [];
        $consumed = 0.0;
        $yearKwh = 0.0;
        $photos = 0;
        $withPhoto = 0;
        foreach ($readings as $rd) {
            $photos += count($rd['photos']);
            $withPhoto += $rd['photos'] ? 1 : 0;
        }
        foreach ($meter['registers'] as $r) {
            $v = $last['values'][$r['id']] ?? null;
            $pv = $prev['values'][$r['id']] ?? null;
            $total = 0.0;
            $p = null;
            foreach ($readings as $rd) {
                $x = $rd['values'][$r['id']] ?? null;
                if ($x === null) {
                    continue;
                }
                if ($p !== null && $x > $p) {
                    $total += $x - $p;
                }
                $p = $x;
            }
            if ($r['tariff'] !== 'EXPORT') {
                $consumed += $total;
            }
            if ($r['tariff'] !== 'EXPORT' && $last) {
                $ly = date('Y', $last['ts']);
                $p2 = null;
                foreach ($readings as $rd) {
                    $x = $rd['values'][$r['id']] ?? null;
                    if ($x === null) {
                        continue;
                    }
                    if ($p2 !== null && $x > $p2 && date('Y', $rd['ts']) === $ly) {
                        $yearKwh += $x - $p2;
                    }
                    $p2 = $x;
                }
            }
            $regs[] = ['reg' => $r, 'value' => $v, 'delta' => ($v !== null && $pv !== null && $v >= $pv) ? $v - $pv : null, 'total' => $total];
        }
        $span = $n > 1 ? ($last['ts'] - $readings[0]['ts']) / 86400 : 0;
        return ['year_kwh' => $yearKwh, 'year' => $last ? date('Y', $last['ts']) : null, 'with_photo' => $withPhoto, 'span_days' => $span, 'per_month' => $span >= 1 ? $consumed / ($span / 30.4375) : null,
                'count' => $n, 'first' => $n ? $readings[0] : null, 'last' => $last, 'regs' => $regs, 'consumed' => $consumed, 'photos' => $photos,
                'days_ago' => $last ? (int) floor((time() - $last['ts']) / 86400) : null];
    }

    /** Interpolierter Zählerstand zum Zeitpunkt $t (null außerhalb der Ablesungen). */
    public static function valueAt(array $s, int $t): ?float
    {
        $n = count($s);
        if ($n === 0 || $t < $s[0][0] || $t > $s[$n - 1][0]) {
            return null;
        }
        for ($i = 0; $i < $n; $i++) {
            if ($s[$i][0] === $t) {
                return (float) $s[$i][1];
            }
            if ($i > 0 && $s[$i - 1][0] < $t && $t < $s[$i][0]) {
                [$t0, $v0] = $s[$i - 1];
                [$t1, $v1] = $s[$i];
                return $v1 < $v0 ? (float) $v0 : $v0 + ($v1 - $v0) * ($t - $t0) / ($t1 - $t0);
            }
        }
        return null;
    }

    /** Faktor Eingabepreis -> Bruttopreis (Preise netto erfasst: 1 + USt-Satz). */
    public static function grossFactor(array $d): float
    {
        return ($d['price_basis'] ?? 'gross') === 'net' ? 1 + self::vatRate($d) : 1.0;
    }

    /** USt-Satz als Anteil (0.19). */
    public static function vatRate(array $d): float
    {
        return (float) ($d['vat_rate'] ?? 19.0) / 100;
    }

    /**
     * Jahresabrechnung (Übersicht wie vom Versorger) für ein Vertragsjahr.
     * @param array $yr   Ergebnis von contractYear()
     * @param array $rtsReason  ts => Anlass-Schlüssel der Ablesungen
     */
    public static function settlement(array $contract, array $registers, array $series, array $payments, array $yr, array $rtsReason): array
    {
        $d = $contract['data'];
        $from = $yr['start'];
        $to = $yr['end_ex'];
        $usage = [];
        foreach ($registers as $r) {
            $s = $series[$r['id']] ?? [];
            $pts = [];
            foreach ($s as [$t, $v]) {
                if ($t > $from && $t < $to) {
                    $pts[] = [$t, (float) $v, false, $rtsReason[$t] ?? 'regular'];
                }
            }
            $vs = self::valueAt($s, $from);
            $ve = self::valueAt($s, $to);
            // Randpunkte: exakte Ablesung oder anteilig interpoliert
            $startExact = false;
            foreach ($s as [$t, $v]) {
                if ($t === $from) { $startExact = true; $vs = (float) $v; }
            }
            $endExact = false;
            foreach ($s as [$t, $v]) {
                if ($t === $to) { $endExact = true; $ve = (float) $v; }
            }
            if ($vs !== null) {
                array_unshift($pts, [$from, $vs, !$startExact, $startExact ? ($rtsReason[$from] ?? 'regular') : 'prorata']);
            }
            if ($ve !== null) {
                $pts[] = [$to, $ve, !$endExact, $endExact ? ($rtsReason[$to] ?? 'regular') : 'prorata'];
            }
            $rows = [];
            $sum = 0.0;
            for ($i = 1; $i < count($pts); $i++) {
                [$t0, $v0, $i0] = $pts[$i - 1];
                [$t1, $v1, $i1, $why] = $pts[$i];
                $kwh = $v1 > $v0 ? $v1 - $v0 : 0.0;
                $sum += $kwh;
                $rows[] = ['from' => $t0, 'to' => $t1, 'v0' => $v0, 'v1' => $v1, 'est0' => $i0, 'est1' => $i1, 'why' => $why, 'kwh' => $kwh];
            }
            $usage[] = ['reg' => $r, 'rows' => $rows, 'kwh' => $sum];
        }

        $cost = self::cost($contract, $registers, $series, $from, $to, $yr['index']);
        $lines = [];
        foreach (self::BILLED as $t => $key) {
            if ($d[$key] !== null && $cost['kwh'][$t] > 0) {
                $lines[] = ['tariff' => $t, 'kwh' => $cost['kwh'][$t], 'price' => (float) $d[$key], 'amount' => round($cost['kwh'][$t] * (float) $d[$key] / 100, 2)];
            }
        }
        $segs = self::baseSegments($d, $from, $to);
        foreach ($segs as &$sg) {
            $sg['amount'] = round($sg['amount'], 2);   // wie auf der Rechnung: Zeilen auf Cent gerundet
        }
        unset($sg);
        $base = ['segments' => $segs, 'amount' => array_sum(array_column($segs, 'amount'))];
        $feedin = $cost['kwh']['EXPORT'] > 0 && $d['feedin'] !== null
            ? ['kwh' => $cost['kwh']['EXPORT'], 'price' => (float) $d['feedin'], 'amount' => round($cost['credit'], 2)] : null;
        $credits = [];
        foreach ($d['credits'] ?? [] as $cr) {
            if (self::creditYearIndex($cr) === $yr['index']) {
                $credits[] = ['label' => (string) ($cr['label'] ?? ''), 'months' => (int) $cr['months'], 'amount' => (float) $cr['amount']];
            }
        }
        $f = self::grossFactor($d);
        $sumEntered = array_sum(array_column($lines, 'amount')) + $base['amount'];   // so wie erfasst (netto oder brutto)
        if ($f > 1) {   // netto erfasst: USt auf die Nettosumme, auf Cent gerundet
            $energyNet = round($sumEntered, 2);
            $energyGross = $energyNet + round($energyNet * self::vatRate($d), 2);
        } else {        // brutto erfasst: enthaltene USt aus der Bruttosumme
            $energyGross = round($sumEntered, 2);
            $energyNet = round($energyGross / (1 + self::vatRate($d)), 2);
        }
        $total = $energyGross - ($feedin['amount'] ?? 0.0) - array_sum(array_column($credits, 'amount'));
        $paid = 0.0;
        $payList = [];
        foreach ($payments as $p) {
            $t = strtotime($p['paid_on']);
            if ($p['data']['status'] === 'paid' && $t >= $from && $t < $to) {
                $paid += (float) $p['data']['amount'];
                $payList[] = $p;
            }
        }
        $covered = 0;
        foreach ($cost['regs'] as $info) {
            $covered = max($covered, $info['cov']);
        }
        return ['year' => $yr, 'usage' => $usage, 'lines' => $lines, 'base' => $base, 'feedin' => $feedin, 'credits' => $credits,
                'total' => $total, 'net_basis' => ($d['price_basis'] ?? 'gross') === 'net', 'vat_pct' => (float) ($d['vat_rate'] ?? 19.0),
                'sum_net' => $energyNet, 'vat' => $energyGross - $energyNet, 'energy_gross' => $energyGross, 'paid' => $paid, 'paid_count' => count($payList),
                'balance' => $paid - $total, 'complete' => $covered >= ($to - $from) - 86400, 'covered_days' => $covered / 86400];
    }

    /**
     * Grundpreis-Abschnitte je Kalenderjahr (Tage / Tage im Jahr, Schaltjahr 366).
     * Beträge so, wie erfasst (netto oder brutto).
     * @return list<array{from:int,to:int,year:int,days:float,diy:int,per_year:float,amount:float}>
     */
    public static function baseSegments(array $d, int $from, int $to): array
    {
        $out = [];
        $perYear = self::baseFeeYear($d);
        $cur = $from;
        $tz = new DateTimeZone(date_default_timezone_get());
        while ($cur < $to) {
            $dt = (new DateTimeImmutable('@' . $cur))->setTimezone($tz);
            $y = (int) $dt->format('Y');
            $yEnd = (new DateTimeImmutable(($y + 1) . '-01-01 00:00:00', $tz))->getTimestamp();
            $end = min($yEnd, $to);
            $days = ($end - $cur) / 86400;
            if (abs($days - round($days)) < 0.05) {
                $days = (float) round($days);
            }
            $diy = (int) $dt->format('L') === 1 ? 366 : 365;
            $out[] = ['from' => $cur, 'to' => $end, 'year' => $y, 'days' => $days, 'diy' => $diy, 'per_year' => $perYear, 'amount' => $perYear * $days / $diy];
            $cur = $end;
        }
        return $out;
    }

    /** Anteiliger Grundpreis im Zeitraum (wie erfasst), je Kalenderjahr berechnet. */
    public static function baseFeeBetween(array $d, int $from, int $to): float
    {
        return $to > $from ? array_sum(array_column(self::baseSegments($d, $from, $to), 'amount')) : 0.0;
    }

    /**
     * Brutto-Energie- und Grundpreiskosten wie auf der Rechnung gerundet:
     * Zeilen auf Cent, USt (bei Netto-Erfassung) auf die Summe.
     * @return array{0: float, 1: float} [Energie brutto, Grundpreis brutto]
     */
    public static function grossCosts(array $d, array $kwh, int $from, int $to): array
    {
        $energyE = 0.0;
        foreach (self::BILLED as $t => $key) {
            $energyE += round(($kwh[$t] ?? 0.0) * (float) ($d[$key] ?? 0) / 100, 2);
        }
        $baseE = 0.0;
        if ($to > $from) {
            foreach (self::baseSegments($d, $from, $to) as $sg) {
                $baseE += round($sg['amount'], 2);
            }
        }
        $sum = round($energyE + $baseE, 2);
        $f = self::grossFactor($d);
        $gross = $f > 1 ? $sum + round($sum * self::vatRate($d), 2) : $sum;
        $baseG = $baseE * $f;
        return [$gross - $baseG, $baseG];
    }

    /** Jahresgrundgebühr in €. */
    public static function baseFeeYear(array $d): float
    {
        $f = (float) ($d['base_fee'] ?? 0);
        return ($d['base_period'] ?? 'month') === 'year' ? $f : $f * 12;
    }

    /**
     * Kosten im Zeitraum.
     * @return array{kwh: array, regs: array, energy: float, credit: float, base: float, total: float}
     */
    public static function cost(array $contract, array $registers, array $series, int $from, int $to, ?int $yearIndex = null): array
    {
        $d = $contract['data'];
        $kwh = ['ET' => 0.0, 'HT' => 0.0, 'NT' => 0.0, 'EXPORT' => 0.0];
        $regs = [];
        foreach ($registers as $r) {
            if (!isset($kwh[$r['tariff']])) {
                continue;
            }
            [$k, $cov] = self::consumption($series[$r['id']] ?? [], $from, $to);
            $kwh[$r['tariff']] += $k;
            $regs[$r['id']] = ['tariff' => $r['tariff'], 'kwh' => $k, 'cov' => $cov];
        }
        [$energy, $base] = self::grossCosts($d, $kwh, $from, $to);
        $credit = round($kwh['EXPORT'] * (float) ($d['feedin'] ?? 0) / 100, 2);
        $bonus = $yearIndex !== null ? self::creditsFor($contract, $yearIndex, $to) : 0.0;
        return ['kwh' => $kwh, 'regs' => $regs, 'energy' => $energy, 'credit' => $credit, 'base' => $base, 'bonus' => $bonus,
                'total' => $energy + $base - $credit - $bonus];
    }

    /**
     * Zusammenfassung für ein Vertragsjahr: bisher, Zahlungen, Prognose, Soll-Ist.
     */
    public static function summary(array $contract, array $registers, array $series, array $payments, ?int $now = null): array
    {
        $now ??= time();
        $d = $contract['data'];
        $yr = self::contractYear($contract, $now);

        $lastTs = 0;
        foreach ($registers as $r) {
            if (isset(self::BILLED[$r['tariff']]) || $r['tariff'] === 'EXPORT') {
                $s = $series[$r['id']] ?? [];
                if ($s) {
                    $lastTs = max($lastTs, end($s)[0]);
                }
            }
        }
        $accrualTo = min($now, $lastTs, $yr['end_ex']);
        $warnings = [];
        $hasData = $accrualTo > $yr['start'];

        // Zahlungen im Vertragsjahr (nur bezahlte, bis heute)
        $paid = 0.0;
        $paidCount = 0;
        foreach ($payments as $p) {
            $t = strtotime($p['paid_on']);
            if ($p['data']['status'] === 'paid' && $t >= $yr['start'] && $t < $yr['end_ex'] && $t <= $now + 86399) {
                $paid += (float) $p['data']['amount'];
                $paidCount++;
            }
        }

        // Verbleibende Abschläge bis Jahresende (volle Monate ab heute)
        $remainingMonths = 0;
        if ($now < $yr['end_ex']) {
            $diff = (new DateTimeImmutable('@' . $now))->diff(new DateTimeImmutable('@' . $yr['end_ex']));
            $remainingMonths = $diff->y * 12 + $diff->m;
        }
        $advance = $d['monthly_advance'] !== null ? (float) $d['monthly_advance'] : null;
        $expectedPayments = $paid + ($advance !== null ? $advance * $remainingMonths : 0.0);

        $res = [
            'year' => $yr, 'has_data' => $hasData, 'accrual_to' => $accrualTo, 'paid' => $paid, 'paid_count' => $paidCount,
            'remaining_months' => $remainingMonths, 'expected_payments' => $expectedPayments, 'warnings' => $warnings,
            'cost' => null, 'forecast_cost' => null, 'forecast_kwh' => null, 'balance_now' => null, 'balance_forecast' => null,
            'credits_pending' => [], 'days_covered' => 0.0, 'annual_kwh' => $d['annual_kwh'] !== null ? (float) $d['annual_kwh'] : null,
        ];
        if (!$hasData) {
            $res['warnings'][] = 'Für dieses Vertragsjahr liegen noch nicht genug Ablesungen vor (mindestens zwei, davon eine nach Vertragsbeginn).';
            return $res;
        }

        $c = self::cost($contract, $registers, $series, $yr['start'], $accrualTo, $yr['index']);
        foreach ($contract['data']['credits'] ?? [] as $cr) {
            if (self::creditYearIndex($cr) === $yr['index'] && self::creditTs($contract, $cr) > $accrualTo) {
                $res['credits_pending'][] = ['amount' => (float) $cr['amount'], 'ts' => self::creditTs($contract, $cr), 'label' => (string) ($cr['label'] ?? '')];
            }
        }
        $res['cost'] = $c;
        $res['balance_now'] = $paid - $c['total'];

        // Prognose: Tagesdurchschnitt je Register hochrechnen
        $remainingDays = max(0, ($yr['end_ex'] - $accrualTo) / 86400);
        $maxCov = 0;
        $fkwh = ['ET' => 0.0, 'HT' => 0.0, 'NT' => 0.0, 'EXPORT' => 0.0];
        $ok = true;
        foreach ($c['regs'] as $info) {
            $maxCov = max($maxCov, $info['cov']);
            if ($info['cov'] < 86400) {
                $ok = false;
                continue;
            }
            $perDay = $info['kwh'] / ($info['cov'] / 86400);
            $fkwh[$info['tariff']] += $info['kwh'] + $perDay * $remainingDays;
        }
        $res['days_covered'] = $maxCov / 86400;
        if ($ok && $c['regs']) {
            [$energy, $base] = self::grossCosts($d, $fkwh, $yr['start'], $yr['end_ex']);
            $credit = round($fkwh['EXPORT'] * (float) ($d['feedin'] ?? 0) / 100, 2);
            $res['forecast_cost'] = $energy + $base - $credit - self::creditsFor($contract, $yr['index']);
            $res['forecast_kwh'] = $fkwh['ET'] + $fkwh['HT'] + $fkwh['NT'];
            $res['forecast_export_kwh'] = $fkwh['EXPORT'];
            $res['balance_forecast'] = $expectedPayments - $res['forecast_cost'];
            if ($maxCov < 30 * 86400) {
                $res['warnings'][] = 'Prognose basiert auf weniger als 30 Tagen Ablesedaten und ist daher ungenau.';
            }
            if ($advance === null) {
                $res['warnings'][] = 'Kein monatlicher Abschlag im Vertrag hinterlegt – künftige Abschläge sind in der Prognose nicht berücksichtigt.';
            }
        } else {
            $res['warnings'][] = 'Für die Prognose braucht jedes abgerechnete Register mindestens einen Tag Ablesedaten.';
        }
        return $res;
    }
}
