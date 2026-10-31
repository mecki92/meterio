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

    /** Länge des Abrechnungszeitraums in Monaten (12 = Vertragsjahr; 1/3/6 z. B. bei monatlich kündbaren Verträgen). */
    public static function billingMonths(array $contract): int
    {
        $m = (int) ($contract['data']['billing_months'] ?? 12);
        return in_array($m, [1, 3, 6, 12], true) ? $m : 12;
    }

    /** Ende des ersten (verkürzten) Abrechnungszeitraums laut Netzbetreiber (exklusiv, Folgetag des Stichtags) oder null. */
    public static function firstBillingEx(array $contract): ?DateTimeImmutable
    {
        $f = $contract['data']['first_billing'] ?? null;
        if (!$f) {
            return null;
        }
        $d = (new DateTimeImmutable($f))->modify('+1 day');
        return $d > new DateTimeImmutable($contract['start_date']) ? $d : null;
    }

    /** true = klassisches Vertragsjahr (12 Monate ab Vertragsbeginn, ohne abweichenden Abrechnungsstichtag). */
    public static function isYearly(array $contract): bool
    {
        return self::billingMonths($contract) === 12 && self::firstBillingEx($contract) === null;
    }

    /** Beginn des k-ten Abrechnungszeitraums (k = 0: Vertragsbeginn; k = 1: Ende des 1. Zeitraums usw.). */
    public static function boundary(array $contract, int $k): DateTimeImmutable
    {
        $start = new DateTimeImmutable($contract['start_date']);
        if ($k <= 0) {
            return $start;
        }
        $fb = self::firstBillingEx($contract);
        $bm = self::billingMonths($contract);
        return $fb ? $fb->modify('+' . (($k - 1) * $bm) . ' months') : $start->modify('+' . ($k * $bm) . ' months');
    }

    /** Preisänderungen des Vertrags als Liste [Zeitstempel, Änderung], aufsteigend. */
    public static function priceChanges(array $contract): array
    {
        $out = [];
        foreach ($contract['data']['price_changes'] ?? [] as $pc) {
            if (!empty($pc['from'])) {
                $out[] = [(new DateTimeImmutable($pc['from']))->getTimestamp(), $pc];
            }
        }
        usort($out, static fn($a, $b) => $a[0] <=> $b[0]);
        return $out;
    }

    /** Vertragsdaten (Preise, Grundgebühr, Abschlag), wie sie zum Zeitpunkt $ts gelten. Leere Felder einer Änderung gelten „wie bisher“. */
    public static function dataAt(array $contract, int $ts): array
    {
        $d = $contract['data'];
        foreach (self::priceChanges($contract) as [$cts, $pc]) {
            if ($cts > $ts) {
                break;
            }
            foreach (['price_et', 'price_ht', 'price_nt', 'feedin', 'monthly_advance'] as $k) {
                if (isset($pc[$k]) && $pc[$k] !== '') {
                    $d[$k] = (float) $pc[$k];
                }
            }
            if (isset($pc['base_fee']) && $pc['base_fee'] !== '') {
                $d['base_fee'] = (float) $pc['base_fee'];
                $d['base_period'] = ($pc['base_period'] ?? $d['base_period']) === 'year' ? 'year' : 'month';
            }
        }
        return $d;
    }

    /** Monatlicher Abschlag, der zum Zeitpunkt $ts gilt (oder null). */
    public static function advanceAt(array $contract, int $ts): ?float
    {
        $a = self::dataAt($contract, $ts)['monthly_advance'] ?? null;
        return $a === null ? null : (float) $a;
    }

    /** Zerlegt [from, to) an den Tagen, an denen neue Preise gelten. @return list<array{0:int,1:int}> */
    public static function priceSegments(array $contract, int $from, int $to): array
    {
        $cuts = [$from];
        foreach (self::priceChanges($contract) as [$ts]) {
            if ($ts > $from && $ts < $to) {
                $cuts[] = $ts;
            }
        }
        $cuts[] = $to;
        $cuts = array_values(array_unique($cuts));
        $out = [];
        for ($i = 1; $i < count($cuts); $i++) {
            $out[] = [$cuts[$i - 1], $cuts[$i]];
        }
        return $out ?: [[$from, $to]];
    }

    /** Aktueller Abrechnungszeitraum [start, end_ex) zum Zeitpunkt $now (bei 12 Monaten: das Vertragsjahr). */
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
        while ($n < 720 && self::boundary($contract, $n + 1)->getTimestamp() <= $ref) {
            $n++;
        }
        $ys = self::boundary($contract, $n);
        $ye = self::boundary($contract, $n + 1);
        if ($end) {
            $endEx = $end->modify('+1 day');
            if ($endEx < $ye) {
                $ye = $endEx;
            }
        }
        return ['start' => $ys->getTimestamp(), 'end_ex' => $ye->getTimestamp(), 'index' => $n + 1,
                'label' => $ys->format('d.m.Y') . ' – ' . $ye->modify('-1 day')->format('d.m.Y')];
    }

    /** Abrechnungszeitraum mit Nummer $idx (1 = erster). */
    public static function periodInfo(array $contract, int $idx): array
    {
        return self::contractYear($contract, self::boundary($contract, max(0, $idx - 1))->getTimestamp());
    }

    /** Höchste gültige Nummer eines Abrechnungszeitraums (Vertragsende bzw. heute). */
    public static function maxPeriod(array $contract): int
    {
        $ref = !empty($contract['end_date']) ? strtotime($contract['end_date']) : time();
        return self::contractYear($contract, $ref)['index'];
    }

    /**
     * Abrechnungszeitraum einer Zahlung: manuell zugeordnet (data.period) oder nach Datum.
     * 0 = vor Vertragsbeginn, 9999 = nach Vertragsende.
     */
    public static function paymentPeriod(array $contract, array $payment): int
    {
        $per = (int) ($payment['data']['period'] ?? 0);
        if ($per >= 1 && $per <= self::maxPeriod($contract)) {
            return $per;
        }
        $t = strtotime($payment['paid_on']);
        if ($t < strtotime($contract['start_date'])) {
            return 0;
        }
        if (!empty($contract['end_date']) && $t >= strtotime($contract['end_date'] . ' +1 day')) {
            return 9999;
        }
        return self::contractYear($contract, $t)['index'];
    }

    /** Alle Vertragsjahre als Liste (für Auswahl "Vertragsjahr"). */
    public static function contractYears(array $contract, int $now): array
    {
        $out = [];
        $cur = self::contractYear($contract, $now)['index'];
        for ($i = 1; $i <= $cur; $i++) {
            $ref = self::boundary($contract, $i - 1)->getTimestamp() + 86400;
            $out[] = self::contractYear($contract, $ref);
        }
        return $out;
    }

    /** Vertragsjahr (1 = erstes), in dessen Jahresabrechnung eine Gutschrift nach N Monaten verrechnet wird. */
    public static function creditYearIndex(array $contract, array $credit): int
    {
        $ts = self::creditTs($contract, $credit);
        $k = 1;
        while ($k < 720 && self::boundary($contract, $k)->getTimestamp() < $ts) {
            $k++;
        }
        return $k;
    }

    /** Zeitpunkt, ab dem die Gutschrift gewertet wird (Vertragsbeginn + N Monate). */
    public static function creditTs(array $contract, array $credit): int
    {
        return (new DateTimeImmutable($contract['start_date']))->modify('+' . (int) $credit['months'] . ' months')->getTimestamp();
    }

    /** Buchungstag der Bonuszahlung: letzter Tag des N-ten Monats ab Vertragsbeginn (bei „nach 12 Monaten“ = letzter Tag des Vertragsjahres), höchstens das Vertragsende. */
    public static function bonusDueTs(array $contract, array $credit): int
    {
        $due = (new DateTimeImmutable($contract['start_date']))->modify('+' . (int) $credit['months'] . ' months')->modify('-1 day');
        if (!empty($contract['end_date'])) {
            $end = new DateTimeImmutable($contract['end_date']);
            if ($end < $due) {
                $due = $end;
            }
        }
        return $due->getTimestamp();
    }

    /**
     * Bonuszahlungen (einmalige Gutschriften) als erwartete Zahlungen, nach Datum sortiert.
     * Entfällt, wenn der Vertrag endet, bevor „N Monate“ erreicht sind.
     * @return list<array{ts:int,amount:float,label:string,months:int,year:int}>
     */
    public static function bonusPayments(array $contract, ?int $yearIndex = null): array
    {
        $out = [];
        $endEx = !empty($contract['end_date']) ? (new DateTimeImmutable($contract['end_date']))->modify('+1 day')->getTimestamp() : null;
        foreach ($contract['data']['credits'] ?? [] as $cr) {
            if ($yearIndex !== null && self::creditYearIndex($contract, $cr) !== $yearIndex) {
                continue;
            }
            if ($endEx !== null && self::creditTs($contract, $cr) > $endEx) {
                continue;
            }
            $out[] = ['ts' => self::bonusDueTs($contract, $cr), 'amount' => (float) $cr['amount'], 'label' => (string) ($cr['label'] ?? ''),
                      'months' => (int) $cr['months'], 'year' => self::creditYearIndex($contract, $cr)];
        }
        usort($out, static fn($a, $b) => $a['ts'] <=> $b['ts']);
        return $out;
    }

    /**
     * Summe der einmaligen Gutschriften, die in der Jahresabrechnung des Vertragsjahres verrechnet werden.
     * Mit $upTo nur die bis dahin bereits gewerteten (Vertragsbeginn + N Monate <= $upTo), sonst alle erwarteten.
     */
    public static function creditsFor(array $contract, int $yearIndex, ?int $upTo = null): float
    {
        $sum = 0.0;
        foreach ($contract['data']['credits'] ?? [] as $cr) {
            if (self::creditYearIndex($contract, $cr) === $yearIndex && ($upTo === null || self::creditTs($contract, $cr) <= $upTo)) {
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

    /**
     * Statistik eines Zählers: Verbrauch je Kalenderjahr und Monat, anteilig (linear zwischen den Ablesungen).
     * @return array{years: list<array>, months: array, total: float, span_days: float, has_data: bool}
     */
    public static function meterStatistics(array $meter, array $series): array
    {
        $first = null;
        $last = null;
        foreach ($meter['registers'] as $r) {
            $s = $series[$r['id']] ?? [];
            if (count($s) >= 2) {
                $first = $first === null ? $s[0][0] : min($first, $s[0][0]);
                $last = $last === null ? end($s)[0] : max($last, end($s)[0]);
            }
        }
        if ($first === null) {
            return ['years' => [], 'months' => [], 'total' => 0.0, 'span_days' => 0.0, 'has_data' => false];
        }
        $measure = static function (int $from, int $to) use ($meter, $series): array {
            $imp = 0.0; $exp = 0.0; $cov = 0; $by = [];
            foreach ($meter['registers'] as $r) {
                [$kwh, $c] = self::consumption($series[$r['id']] ?? [], $from, $to);
                $cov = max($cov, $c);
                if ($r['tariff'] === 'EXPORT') {
                    $exp += $kwh;
                } else {
                    $imp += $kwh;
                    $by[$r['tariff']] = ($by[$r['tariff']] ?? 0.0) + $kwh;
                }
            }
            return ['kwh' => $imp, 'export' => $exp, 'cov' => $cov, 'by' => $by];
        };
        $years = [];
        $months = [];
        $prev = null;
        $total = 0.0;
        for ($y = (int) date('Y', $first); $y <= (int) date('Y', $last); $y++) {
            $from = mktime(0, 0, 0, 1, 1, $y);
            $to = mktime(0, 0, 0, 1, 1, $y + 1);
            $m = $measure($from, $to);
            if ($m['cov'] <= 0) {
                continue;
            }
            $days = ($to - $from) / 86400;
            $covDays = $m['cov'] / 86400;
            $partial = $m['cov'] < ($to - $from) - 3600;
            $row = $m + ['y' => $y, 'days' => $days, 'cov_days' => $covDays, 'partial' => $partial,
                'per_day' => $m['kwh'] / max(1.0, $covDays),
                'proj' => ($partial && $covDays >= 30) ? $m['kwh'] / $covDays * $days : null,
                'delta' => null];
            $cmp = $row['proj'] ?? $row['kwh'];
            if ($prev !== null && $prev > 0 && $covDays >= 30) {
                $row['delta'] = ($cmp - $prev) / $prev * 100;
            }
            $prev = $cmp;
            $total += $m['kwh'];
            $years[] = $row;
            for ($mo = 1; $mo <= 12; $mo++) {
                $mf = mktime(0, 0, 0, $mo, 1, $y);
                $mt = mktime(0, 0, 0, $mo + 1, 1, $y);
                $mm = $measure($mf, $mt);
                if ($mm['cov'] > 0) {
                    $months[$y][$mo] = ['kwh' => $mm['kwh'], 'partial' => $mm['cov'] < ($mt - $mf) - 3600];
                }
            }
        }
        return ['years' => $years, 'months' => $months, 'total' => $total, 'span_days' => ($last - $first) / 86400, 'has_data' => (bool) $years];
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
        $lines = $cost['priced']['lines'];
        $base = ['segments' => $cost['priced']['base_segments'], 'amount' => $cost['priced']['base_entered']];
        $feedinPrice = self::dataAt($contract, $to - 1)['feedin'] ?? null;
        $feedin = $cost['kwh']['EXPORT'] > 0 && $feedinPrice !== null
            ? ['kwh' => $cost['kwh']['EXPORT'], 'price' => (float) $feedinPrice, 'amount' => round($cost['credit'], 2)] : null;
        $credits = self::bonusPayments($contract, $yr['index']);
        $f = self::grossFactor($d);
        $sumEntered = array_sum(array_column($lines, 'amount')) + $base['amount'];   // so wie erfasst (netto oder brutto)
        if ($f > 1) {   // netto erfasst: USt auf die Nettosumme, auf Cent gerundet
            $energyNet = round($sumEntered, 2);
            $energyGross = $energyNet + round($energyNet * self::vatRate($d), 2);
        } else {        // brutto erfasst: enthaltene USt aus der Bruttosumme
            $energyGross = round($sumEntered, 2);
            $energyNet = round($energyGross / (1 + self::vatRate($d)), 2);
        }
        $total = $energyGross - ($feedin['amount'] ?? 0.0);
        $bonus = array_sum(array_column($credits, 'amount'));
        $paid = 0.0;
        $payList = [];
        foreach ($payments as $p) {
            if ($p['data']['status'] === 'paid' && self::paymentPeriod($contract, $p) === $yr['index']) {
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
                'bonus' => $bonus, 'balance' => $paid + $bonus - $total, 'complete' => $covered >= ($to - $from) - 86400, 'covered_days' => $covered / 86400];
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
     * Preisbewertung wie auf der Rechnung: je Preisabschnitt Zeilen auf Cent gerundet, USt (bei Netto-Erfassung) auf die Summe.
     * @param list<array{0:int,1:int,2:array}> $segs [von, bis, kWh je ET/HT/NT/EXPORT] – jeweils innerhalb eines Preisabschnitts
     */
    public static function priced(array $contract, array $segs): array
    {
        $lines = [];
        $baseSegs = [];
        $energyE = 0.0;
        $baseE = 0.0;
        $credit = 0.0;
        foreach ($segs as [$a, $b, $kwh]) {
            $d = self::dataAt($contract, $a);
            foreach (self::BILLED as $t => $key) {
                $k = (float) ($kwh[$t] ?? 0.0);
                $amt = round($k * (float) ($d[$key] ?? 0) / 100, 2);
                $energyE += $amt;
                if ($d[$key] !== null && $k > 0) {
                    $lines[] = ['tariff' => $t, 'from' => $a, 'to' => $b, 'kwh' => $k, 'price' => (float) $d[$key], 'amount' => $amt];
                }
            }
            if ($b > $a) {
                foreach (self::baseSegments($d, $a, $b) as $sg) {
                    $sg['amount'] = round($sg['amount'], 2);
                    $baseE += $sg['amount'];
                    $baseSegs[] = $sg;
                }
            }
            $credit += round((float) ($kwh['EXPORT'] ?? 0.0) * (float) ($d['feedin'] ?? 0) / 100, 2);
        }
        $sum = round($energyE + $baseE, 2);
        $f = self::grossFactor($contract['data']);
        $gross = $f > 1 ? $sum + round($sum * self::vatRate($contract['data']), 2) : $sum;
        $baseG = $baseE * $f;
        return ['energy' => $gross - $baseG, 'base' => $baseG, 'credit' => $credit, 'lines' => $lines, 'base_segments' => $baseSegs,
                'base_entered' => $baseE, 'sum_entered' => $sum, 'gross' => $gross];
    }

    /** Jahresgrundgebühr in €. */
    public static function baseFeeYear(array $d): float
    {
        $f = (float) ($d['base_fee'] ?? 0);
        return ($d['base_period'] ?? 'month') === 'year' ? $f : $f * 12;
    }

    /**
     * Kosten im Zeitraum (mit Preisänderungen zum jeweiligen Stichtag).
     * @return array{kwh: array, regs: array, energy: float, credit: float, base: float, total: float, priced: array}
     */
    public static function cost(array $contract, array $registers, array $series, int $from, int $to, ?int $yearIndex = null): array
    {
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
        $segs = [];
        foreach (self::priceSegments($contract, $from, $to) as [$a, $b]) {
            $sk = ['ET' => 0.0, 'HT' => 0.0, 'NT' => 0.0, 'EXPORT' => 0.0];
            foreach ($registers as $r) {
                if (isset($sk[$r['tariff']])) {
                    $sk[$r['tariff']] += self::consumption($series[$r['id']] ?? [], $a, $b)[0];
                }
            }
            $segs[] = [$a, $b, $sk];
        }
        $p = self::priced($contract, $segs);
        $bonus = 0.0;   // Gutschriften sind Bonuszahlungen (Zahlungsseite), nicht Kostenminderung
        return ['kwh' => $kwh, 'regs' => $regs, 'energy' => $p['energy'], 'credit' => round($p['credit'], 2), 'base' => $p['base'], 'bonus' => $bonus,
                'total' => $p['energy'] + $p['base'] - round($p['credit'], 2) - $bonus, 'priced' => $p];
    }

    /**
     * Zusammenfassung für ein Vertragsjahr: bisher, Zahlungen, Prognose, Soll-Ist.
     */
    public static function summary(array $contract, array $registers, array $series, array $payments, ?int $now = null, ?int $periodIndex = null): array
    {
        $now ??= time();
        $d = $contract['data'];
        $yr = $periodIndex ? self::periodInfo($contract, $periodIndex) : self::contractYear($contract, $now);

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
            if ($p['data']['status'] === 'paid' && self::paymentPeriod($contract, $p) === $yr['index'] && $t <= $now + 86399) {
                $paid += (float) $p['data']['amount'];
                $paidCount++;
            }
        }

        // Bonuszahlungen (Gutschriften): am Ende des Vertragsjahres gebucht
        $bonusRecv = 0.0;
        $bonusPending = [];
        foreach (self::bonusPayments($contract, $yr['index']) as $bp) {
            if ($bp['ts'] <= $now + 86399) {
                $bonusRecv += $bp['amount'];
            } else {
                $bonusPending[] = $bp;
            }
        }

        // Verbleibende Abschläge bis Jahresende (volle Monate ab heute)
        $remainingMonths = 0;
        if ($now < $yr['end_ex']) {
            $diff = (new DateTimeImmutable('@' . $now))->diff(new DateTimeImmutable('@' . $yr['end_ex']));
            $remainingMonths = $diff->y * 12 + $diff->m;
        }
        $advance = self::advanceAt($contract, $now);
        $advFuture = 0.0;
        for ($k = 1; $k <= $remainingMonths; $k++) {   // ggf. mit geändertem Abschlag ab einem Stichtag
            $advFuture += self::advanceAt($contract, (new DateTimeImmutable('@' . $now))->modify("+$k months")->getTimestamp()) ?? 0.0;
        }
        $expectedPayments = $paid + $bonusRecv + $advFuture + array_sum(array_column($bonusPending, 'amount'));

        $res = [
            'year' => $yr, 'has_data' => $hasData, 'accrual_to' => $accrualTo, 'paid' => $paid, 'paid_count' => $paidCount,
            'remaining_months' => $remainingMonths, 'expected_payments' => $expectedPayments, 'warnings' => $warnings,
            'cost' => null, 'forecast_cost' => null, 'forecast_kwh' => null, 'balance_now' => null, 'balance_forecast' => null,
            'credits_pending' => $bonusPending, 'bonus_received' => $bonusRecv, 'days_covered' => 0.0, 'annual_kwh' => $d['annual_kwh'] !== null ? (float) $d['annual_kwh'] : null,
        ];
        if (!$hasData) {
            $res['warnings'][] = 'Für dieses Vertragsjahr liegen noch nicht genug Ablesungen vor (mindestens zwei, davon eine nach Vertragsbeginn).';
            return $res;
        }

        $c = self::cost($contract, $registers, $series, $yr['start'], $accrualTo, $yr['index']);
        $res['cost'] = $c;
        $res['balance_now'] = $paid + $bonusRecv - $c['total'];

        // Prognose: Tagesdurchschnitt je Register hochrechnen
        $remainingDays = max(0, ($yr['end_ex'] - $accrualTo) / 86400);
        $maxCov = 0;
        $fkwh = ['ET' => 0.0, 'HT' => 0.0, 'NT' => 0.0, 'EXPORT' => 0.0];
        $fsegs = [];
        foreach (self::priceSegments($contract, $yr['start'], $yr['end_ex']) as [$a, $b]) {
            $fsegs[] = [$a, $b, ['ET' => 0.0, 'HT' => 0.0, 'NT' => 0.0, 'EXPORT' => 0.0]];
        }
        $ok = true;
        foreach ($c['regs'] as $rid => $info) {
            $maxCov = max($maxCov, $info['cov']);
            if ($info['cov'] < 86400) {
                $ok = false;
                continue;
            }
            $perDay = $info['kwh'] / ($info['cov'] / 86400);
            $fkwh[$info['tariff']] += $info['kwh'] + $perDay * $remainingDays;
            foreach ($fsegs as &$fs) {   // Ist-Verbrauch je Preisabschnitt + Tagesdurchschnitt für die Resttage
                $act = self::consumption($series[$rid] ?? [], $fs[0], min($fs[1], $accrualTo))[0];
                $future = max(0, $fs[1] - max($fs[0], $accrualTo)) / 86400;
                $fs[2][$info['tariff']] += $act + $perDay * $future;
            }
            unset($fs);
        }
        $res['days_covered'] = $maxCov / 86400;
        if ($ok && $c['regs']) {
            $fp = self::priced($contract, $fsegs);
            $res['forecast_cost'] = $fp['energy'] + $fp['base'] - round($fp['credit'], 2);
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
