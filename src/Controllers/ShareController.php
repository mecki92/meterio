<?php
declare(strict_types=1);

/** Zähler für andere Benutzer freigeben (per Code). Verträge werden nie geteilt. */
final class ShareController
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';   // ohne 0/O/1/I
    private const DAYS = 7;          // Gültigkeit des Codes zum Einlösen
    private const MAX_ACCESS = 365;    // maximale Zugriffsdauer in Tagen
    private const CHOICES = [30, 90, 180, 365];

    public function create(int $meterId): void
    {
        $u = Auth::requireUser();
        $m = Repo::meter((int) $u['id'], $meterId) ?? abort(404, 'Zähler nicht gefunden.');
        $perm = ($_POST['permission'] ?? '') === 'read' ? 'read' : 'write';
        $label = mb_trim_len((string) ($_POST['label'] ?? ''), 60);
        $dur = self::days($_POST['duration'] ?? 365);
        if (Db::val('SELECT COUNT(*) FROM meter_shares WHERE meter_id = ?', [$meterId]) >= 20) {
            flash('error', 'Es sind bereits sehr viele Freigaben vorhanden – bitte zuerst nicht mehr benötigte widerrufen.');
            redirect('meters/' . $meterId . '#freigabe');
        }
        $raw = '';
        for ($i = 0; $i < 16; $i++) {
            $raw .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }
        Db::insert('INSERT INTO meter_shares (meter_id, token_hash, permission, label_enc, duration_days, expires_at, created_at) VALUES (?,?,?,?,?,DATE_ADD(NOW(), INTERVAL ' . self::DAYS . ' DAY),NOW())',
            [$m['id'], self::hash($raw), $perm, Crypto::enc($label), $dur]);
        $_SESSION['new_share'] = ['meter' => $meterId, 'code' => implode('-', str_split($raw, 4)), 'perm' => $perm, 'days' => self::DAYS, 'access' => $dur];
        flash('ok', 'Freigabe-Code erzeugt.');
        redirect('meters/' . $meterId . '#freigabe');
    }

    public function revoke(int $id): void
    {
        $u = Auth::requireUser();
        $s = Db::one('SELECT s.id, s.meter_id FROM meter_shares s JOIN meters m ON m.id = s.meter_id WHERE s.id = ? AND m.user_id = ?', [$id, $u['id']]) ?? abort(404);
        Db::exec('DELETE FROM meter_shares WHERE id = ?', [$id]);
        flash('ok', 'Freigabe widerrufen.');
        redirect('meters/' . $s['meter_id'] . '#freigabe');
    }

    /** Zugriff verlängern: gilt ab heute für 30/90/180/365 Tage (maximal 365). */
    public function extend(int $id): void
    {
        $u = Auth::requireUser();
        $s = Db::one('SELECT s.id, s.meter_id FROM meter_shares s JOIN meters m ON m.id = s.meter_id WHERE s.id = ? AND m.user_id = ? AND s.user_id IS NOT NULL', [$id, $u['id']]) ?? abort(404);
        $d = self::days($_POST['duration'] ?? 365);
        Db::exec('UPDATE meter_shares SET access_until = DATE_ADD(NOW(), INTERVAL ? DAY), duration_days = ? WHERE id = ?', [$d, $d, $id]);
        flash('ok', 'Zugriff verlängert: ab heute ' . $d . ' Tage gültig.');
        redirect('meters/' . $s['meter_id'] . '#freigabe');
    }

    private static function days(mixed $v): int
    {
        $d = (int) $v;
        return in_array($d, self::CHOICES, true) ? $d : self::MAX_ACCESS;
    }

    /** Mitnutzer verlässt einen freigegebenen Zähler. */
    public function leave(int $meterId): void
    {
        $u = Auth::requireUser();
        Db::exec('DELETE FROM meter_shares WHERE meter_id = ? AND user_id = ?', [$meterId, $u['id']]);
        flash('ok', 'Du hast die Freigabe verlassen.');
        redirect('meters');
    }

    public function redeemForm(): void
    {
        Auth::requireUser();
        view('meters/redeem', ['title' => 'Freigabecode einlösen', 'active' => 'meters']);
    }

    public function redeem(): void
    {
        $u = Auth::requireUser();
        if ((int) ($_SESSION['redeem_fail'] ?? 0) >= 5 && time() - (int) ($_SESSION['redeem_t'] ?? 0) < 600) {
            flash('error', 'Zu viele Fehlversuche. Bitte warte ein paar Minuten.');
            redirect('shares/redeem');
        }
        $code = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) ($_POST['code'] ?? '')));
        $s = strlen($code) === 16 ? Db::one('SELECT * FROM meter_shares WHERE token_hash = ? AND user_id IS NULL AND (expires_at IS NULL OR expires_at > NOW())', [self::hash($code)]) : null;
        if (!$s) {
            $_SESSION['redeem_fail'] = (int) ($_SESSION['redeem_fail'] ?? 0) + 1;
            $_SESSION['redeem_t'] = time();
            flash('error', 'Dieser Code ist ungültig oder abgelaufen.');
            redirect('shares/redeem');
        }
        $meter = Db::one('SELECT id, user_id FROM meters WHERE id = ?', [$s['meter_id']]);
        if (!$meter || (int) $meter['user_id'] === (int) $u['id']) {
            flash('error', 'Das ist dein eigener Zähler – er muss nicht freigegeben werden.');
            redirect('shares/redeem');
        }
        if (Db::val('SELECT COUNT(*) FROM meter_shares WHERE meter_id = ? AND user_id = ?', [$s['meter_id'], $u['id']])) {
            Db::exec('DELETE FROM meter_shares WHERE id = ?', [$s['id']]);   // bereits Mitnutzer: Einladung verbrauchen
            flash('warn', 'Dieser Zähler ist bereits für dich freigegeben.');
            redirect('meters/' . $s['meter_id']);
        }
        Db::exec('UPDATE meter_shares SET user_id = ?, token_hash = NULL, expires_at = NULL, redeemed_at = NOW(), access_until = DATE_ADD(NOW(), INTERVAL ? DAY) WHERE id = ?', [$u['id'], (int) ($s['duration_days'] ?: self::MAX_ACCESS), $s['id']]);
        unset($_SESSION['redeem_fail'], $_SESSION['redeem_t']);
        flash('ok', 'Der Zähler wurde für dich freigegeben' . ($s['permission'] === 'write' ? ' – du kannst Zählerstände erfassen.' : ' (nur Ansicht).'));
        redirect('meters/' . $s['meter_id']);
    }

    private static function hash(string $code): string
    {
        return hash('sha256', strtoupper($code));
    }
}
