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

    /** Summe der einmaligen Gutschriften (z. B. Neukundenbonus) für ein Vertragsjahr (1 = erstes). */
    public static function creditsFor(array $contract, int $yearIndex): float
    {
        $sum = 0.0;
        foreach ($contract['data']['credits'] ?? [] as $cr) {
            if ((int) $cr['year'] === $yearIndex) {
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
        $energy = 0.0;
        foreach (self::BILLED as $t => $key) {
            $energy += $kwh[$t] * (float) ($d[$key] ?? 0) / 100;
        }
        $credit = $kwh['EXPORT'] * (float) ($d['feedin'] ?? 0) / 100;
        $base = $to > $from ? self::baseFeeYear($d) * (($to - $from) / 86400) / 365 : 0.0;
        $bonus = $yearIndex !== null ? self::creditsFor($contract, $yearIndex) : 0.0;
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
            'days_covered' => 0.0, 'annual_kwh' => $d['annual_kwh'] !== null ? (float) $d['annual_kwh'] : null,
        ];
        if (!$hasData) {
            $res['warnings'][] = 'Für dieses Vertragsjahr liegen noch nicht genug Ablesungen vor (mindestens zwei, davon eine nach Vertragsbeginn).';
            return $res;
        }

        $c = self::cost($contract, $registers, $series, $yr['start'], $accrualTo, $yr['index']);
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
            $energy = 0.0;
            foreach (self::BILLED as $t => $key) {
                $energy += $fkwh[$t] * (float) ($d[$key] ?? 0) / 100;
            }
            $credit = $fkwh['EXPORT'] * (float) ($d['feedin'] ?? 0) / 100;
            $base = self::baseFeeYear($d) * (($yr['end_ex'] - $yr['start']) / 86400) / 365;
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
