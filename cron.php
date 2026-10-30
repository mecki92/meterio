<?php
declare(strict_types=1);

/**
 * meterio by MeckByte – Cronjob (Ablese-Erinnerungen + Aufräumen)
 *
 * netcup WSC -> Cronjobs:  php /pfad/zu/meterio/cron.php        (täglich, z. B. 07:30 Uhr)
 * oder per URL:            https://DEINE-DOMAIN/cron.php?token=CRON_TOKEN
 */
require __DIR__ . '/src/bootstrap.php';

if (PHP_SAPI !== 'cli') {
    header('Content-Type: text/plain; charset=utf-8');
    $token = (string) Config::get('cron_token', '');
    if ($token === '' || !hash_equals($token, (string) ($_GET['token'] ?? ''))) {
        http_response_code(403);
        exit("Forbidden\n");
    }
}

$log = static function (string $m): void {
    echo '[' . date('Y-m-d H:i:s') . '] ' . $m . "\n";
};

$today = new DateTimeImmutable('today');
$users = Db::all("SELECT * FROM users WHERE is_active = 1 AND twofa_ready = 1 AND reminder_mode <> 'off'");
$sent = 0;

foreach ($users as $u) {
    $uid = (int) $u['id'];
    $meters = Repo::meters($uid);
    if (!$meters) {
        continue;
    }
    $lastReminder = $u['last_reminder_at'] ? new DateTimeImmutable($u['last_reminder_at']) : null;
    $reason = null;

    if ($u['reminder_mode'] === 'monthly') {
        $monthStart = $today->modify('first day of this month');
        $due = (int) $today->format('j') >= (int) $u['reminder_day'];
        $alreadyThisMonth = $lastReminder && $lastReminder >= $monthStart;
        if ($due && !$alreadyThisMonth) {
            $reason = 'Monatliche Erinnerung zur Zählerablesung';
        }
    } elseif ($u['reminder_mode'] === 'contract_year') {
        foreach (Repo::contracts($uid) as $c) {
            if ($c['start_date'] > $today->format('Y-m-d') || ($c['end_date'] && $c['end_date'] < $today->format('Y-m-d'))) {
                continue;
            }
            $yr = Calc::contractYear($c, $today->getTimestamp());
            $daysLeft = (int) floor(($yr['end_ex'] - $today->getTimestamp()) / 86400);
            if ($daysLeft >= 0 && $daysLeft <= 14 && (!$lastReminder || $lastReminder < $today->modify('-20 days'))) {
                $reason = 'Dein Vertragsjahr endet in ' . $daysLeft . ' Tag(en) (' . $yr['label'] . ') – bitte lies die Zähler für die Abrechnung ab';
                break;
            }
        }
    }
    if (!$reason) {
        continue;
    }

    $rows = '';
    foreach ($meters as $m) {
        $last = Db::val('SELECT MAX(read_at) FROM readings WHERE meter_id = ?', [$m['id']]);
        $rows .= '<li><b>' . e($m['name']) . '</b> – letzte Ablesung: ' . ($last ? e(date('d.m.Y', strtotime($last))) : 'noch keine') . '</li>';
    }
    $html = Mailer::wrap('Zeit zum Ablesen ⚡', '<p>Hallo ' . e($u['name']) . ',</p><p>' . e($reason) . '.</p><ul>' . $rows . '</ul>'
        . Mailer::button(abs_url('dashboard'), 'Zählerstand erfassen')
        . '<p style="font-size:12px;color:#6b7785">Erinnerungen kannst du in deinem Profil anpassen oder abschalten.</p>');
    if (Mailer::send($u['email'], 'Erinnerung: Zählerstand ablesen', $html)) {
        Db::exec('UPDATE users SET last_reminder_at = CURDATE() WHERE id = ?', [$uid]);
        $sent++;
        $log("Erinnerung gesendet: user #$uid");
    } else {
        $log("FEHLER beim Senden: user #$uid");
    }
}

// Aufräumen
Db::exec('DELETE FROM tokens WHERE expires_at < NOW() OR used_at IS NOT NULL');
Db::exec('DELETE FROM otp_codes WHERE expires_at < NOW()');
Db::exec('DELETE FROM login_attempts WHERE created_at < (NOW() - INTERVAL 2 DAY)');
foreach (glob(ROOT . '/storage/sessions/sess_*') ?: [] as $f) {
    if (filemtime($f) < time() - 86400) {
        @unlink($f);
    }
}

$log("Fertig. Erinnerungen gesendet: $sent");
