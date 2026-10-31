<?php
declare(strict_types=1);

function e(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Datenschutz: alle Zeichen bis auf die letzten $keep durch Punkte ersetzen (Leerzeichen/Bindestriche bleiben). */
function mask_tail(string $s, int $keep = 4): string
{
    $c = preg_split('//u', $s, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $n = count($c);
    foreach ($c as $i => $ch) {
        if ($i < $n - $keep && trim($ch) !== '' && $ch !== '-') {
            $c[$i] = '•';
        }
    }
    return implode('', $c);
}

/** Datenschutz: je Wort nur den ersten Buchstaben zeigen. */
function mask_words(string $s): string
{
    return implode(' ', array_map(static function (string $w): string {
        $len = mb_strlen($w);
        return $len <= 1 ? $w : mb_substr($w, 0, 1) . str_repeat('•', $len - 1);
    }, preg_split('/\s+/u', trim($s), -1, PREG_SPLIT_NO_EMPTY) ?: []));
}

/** Bezeichnung des Abrechnungszeitraums: „Vertragsjahr“ bei 12 Monaten, sonst „Abrechnungszeitraum“. */
function period_word(array $contract): string
{
    return Calc::isYearly($contract) ? 'Vertragsjahr' : 'Abrechnungszeitraum';
}

/** Kleiner Info-Button (Popover) hinter einer Feldbezeichnung; $id optional, damit JS den Text anpassen kann. */
function info(string $text, string $id = ''): string
{
    return ' <button type="button" class="info-btn"' . ($id !== '' ? ' id="' . e($id) . '"' : '')
        . ' data-bs-toggle="popover" data-bs-trigger="hover focus" data-bs-placement="top" data-bs-content="' . e($text)
        . '" aria-label="Hinweis: ' . e($text) . '"><i class="bi bi-info-circle"></i></button>';
}

function base_path(): string
{
    static $p = null;
    if ($p === null) {
        $path = parse_url((string) Config::get('app.url', ''), PHP_URL_PATH);
        $p = rtrim((string) $path, '/');
    }
    return $p;
}

function url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

function abs_url(string $path = ''): string
{
    return rtrim((string) Config::get('app.url', ''), '/') . '/' . ltrim($path, '/');
}

function asset(string $path): string
{
    $f = ROOT . '/public/assets/' . $path;   // Änderungszeit als Cache-Buster
    return url('assets/' . $path) . '?v=' . APP_VERSION . '.' . (is_file($f) ? filemtime($f) : 0);
}

function redirect(string $path): never
{
    header('Location: ' . url($path));
    exit;
}

function abort(int $code, string $message = ''): never
{
    http_response_code($code);
    $titles = [403 => 'Zugriff verweigert', 404 => 'Nicht gefunden', 419 => 'Sitzung abgelaufen', 429 => 'Zu viele Anfragen', 500 => 'Serverfehler'];
    view('error', ['code' => $code, 'title' => $titles[$code] ?? 'Fehler', 'message' => $message, 'bare' => true]);
    exit;
}

function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = [$type, $msg];
}

function take_flash(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $ok = isset($_POST['_csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string) $_POST['_csrf']);
    if (!$ok) {
        abort(419, 'Das Formular ist abgelaufen oder ungültig. Bitte Seite neu laden und erneut versuchen.');
    }
}

function view(string $name, array $data = [], string $layout = 'layout'): void
{
    $data += ['title' => APP_BRAND, 'bare' => false, 'active' => ''];
    extract($data, EXTR_SKIP);
    ob_start();
    require ROOT . '/views/' . $name . '.php';
    $content = ob_get_clean();
    require ROOT . '/views/' . $layout . '.php';
}

function json_response(mixed $data, int $code = 200): never
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function client_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'), 0, 45);
}

/** Zahl aus Benutzereingabe parsen (deutsch "1.234,5" oder englisch "1234.5"). */
function parse_num(mixed $s): ?float
{
    $s = trim(str_replace([' ', "\u{00A0}"], '', (string) $s));
    if ($s === '') {
        return null;
    }
    if (str_contains($s, ',')) {
        $s = str_replace('.', '', $s);
        $s = str_replace(',', '.', $s);
    }
    return is_numeric($s) ? (float) $s : null;
}

/**
 * kWh-Eingabe -> ganze kWh. Punkte sind Tausendertrenner ("12.345"), ein Komma beginnt Nachkommastellen (werden gerundet).
 * Ein einzelner Punkt mit != 3 Folgeziffern ("12345.6") gilt als Dezimalpunkt.
 */
function parse_kwh(mixed $s): ?float
{
    $s = trim(str_replace([' ', "\u{00A0}", "'"], '', (string) $s));
    if ($s === '') {
        return null;
    }
    if (str_contains($s, ',')) {
        $s = str_replace(['.', ','], ['', '.'], $s);
    } elseif (preg_match('/^\d{1,3}(\.\d{3})+$/', $s)) {
        $s = str_replace('.', '', $s);
    }
    return is_numeric($s) ? round((float) $s) : null;
}

function nf(?float $v, int $dec = 2): string
{
    return $v === null ? '–' : number_format($v, $dec, ',', '.');
}

/** Preis mit beliebig vielen Nachkommastellen (mind. 2, max. 6), z. B. 24,915966 oder 30,90. */
function price(?float $v): string
{
    if ($v === null) {
        return '–';
    }
    $s = number_format($v, 6, ',', '.');
    $s = rtrim($s, '0');
    $dec = strlen($s) - strpos($s, ',') - 1;
    return $dec < 2 ? $s . str_repeat('0', 2 - $dec) : $s;
}

function eur(?float $v): string
{
    return $v === null ? '–' : number_format($v, 2, ',', '.') . ' €';
}

function fmt_date(?string $d, string $fmt = 'd.m.Y'): string
{
    if (!$d) {
        return '–';
    }
    $t = strtotime($d);
    return $t ? date($fmt, $t) : '–';
}

function input_dt(?string $mysql): string
{
    $t = $mysql ? strtotime($mysql) : time();
    return date('Y-m-d\TH:i', $t ?: time());
}

/** Datum für <input type="date"> (heute, wenn leer). */
function input_date(?string $mysql = null): string
{
    $t = $mysql ? strtotime($mysql) : time();
    return date('Y-m-d', $t ?: time());
}

function parse_dt(string $s): ?string
{
    $d = DateTime::createFromFormat('Y-m-d\TH:i', $s) ?: DateTime::createFromFormat('Y-m-d H:i', $s);
    return $d ? $d->format('Y-m-d H:i:s') : null;
}

function parse_date(string $s): ?string
{
    $d = DateTime::createFromFormat('!Y-m-d', $s);
    return ($d && $d->format('Y-m-d') === $s) ? $s : null;
}

/** Anlass einer Ablesung: key => [Label, Badge-Farbe]. */
function reading_reasons(): array
{
    return [
        'regular'        => ['Reguläre Ablesung', 'secondary'],
        'a4'             => ['A4 · Selbstablesung Kunde', 'primary', 'A4'],
        'a2'             => ['A2 · Ablesung Messstellenbetreiber', 'info', 'A2'],
        's1'             => ['S1 · Schätzung Lieferant', 'warning', 'S1'],
        's2'             => ['S2 · Schätzung Messstellenbetreiber', 'warning', 'S2'],
        'contract_start' => ['Vertragsbeginn / Einzug', 'success'],
        'interim'        => ['Zwischenablesung', 'info'],
        'year_end'       => ['Jahresablesung (Ende Vertragsjahr)', 'primary'],
        'contract_end'   => ['Vertragsende / Auszug', 'dark'],
        'provider_change' => ['Anbieterwechsel', 'warning'],
        'price_change'   => ['Preisänderung', 'warning'],
        'meter_change'   => ['Zählerwechsel', 'danger'],
        'grid_operator'  => ['Ablesung durch Netzbetreiber', 'info'],
        'estimated'      => ['Schätzwert', 'secondary'],
        'other'          => ['Sonstiges', 'secondary'],
    ];
}

function tariff_labels(): array
{
    return [
        'ET'     => 'Bezug (Eintarif)',
        'HT'     => 'Bezug Hochtarif (HT)',
        'NT'     => 'Bezug Niedertarif (NT)',
        'EXPORT' => 'Einspeisung',
        'OTHER'  => 'Sonstiges (nicht abgerechnet)',
    ];
}

function random_token(int $bytes = 32): string
{
    return bin2hex(random_bytes($bytes));
}

function mb_trim_len(string $s, int $max): string
{
    return mb_substr(trim($s), 0, $max);
}
