<?php
declare(strict_types=1);

function e(mixed $v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
    return url('assets/' . $path) . '?v=' . APP_VERSION;
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
