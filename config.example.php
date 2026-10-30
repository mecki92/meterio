<?php
/**
 * meterio by MeckByte – Konfiguration
 *
 * 1. Diese Datei nach "config.php" kopieren (gleicher Ordner, ausserhalb von /public).
 * 2. Werte anpassen. config.php NIEMALS ins Git-Repository einchecken (steht in .gitignore).
 */
return [
    'app' => [
        'name'         => 'meterio by MeckByte',
        // Öffentliche URL der Anwendung OHNE abschliessenden Slash (mit Unterordner, falls vorhanden)
        'url'          => 'https://strom.example.de',
        'timezone'     => 'Europe/Berlin',
        'debug'        => false,      // true nur zur Fehlersuche!
        'idle_timeout' => 7200,       // Sekunden Inaktivität bis zum Auto-Logout
    ],

    'db' => [
        'host'    => 'localhost',     // netcup: Hostname aus dem WSC (z. B. mysql12.netcup.net)
        'name'    => 'k12345_meterio',
        'user'    => 'k12345_meterio',
        'pass'    => 'CHANGE_ME',
        'charset' => 'utf8mb4',
    ],

    // Schlüssel für AES-256-GCM (32 Zufallsbytes, Base64). Erzeugen mit:
    //   php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
    // ACHTUNG: Schlüssel sichern! Ohne ihn sind alle verschlüsselten Daten unwiederbringlich verloren.
    // Nach dem Start niemals ändern.
    'key' => '',

    // E-Mail / SMTP (netcup: Daten aus dem Kundenkontrollpanel -> E-Mail -> Postfach)
    'mail' => [
        'driver'    => 'smtp',        // 'smtp' oder 'log' (schreibt nur nach storage/logs/mail.log)
        'host'      => 'mx1234.netcup.net',
        'port'      => 465,           // 465 = SSL, 587 = STARTTLS
        'secure'    => 'ssl',         // 'ssl', 'tls' (STARTTLS) oder '' (unverschlüsselt, nicht empfohlen)
        'user'      => 'meterio@example.de',
        'pass'      => 'CHANGE_ME',
        'from'      => 'meterio@example.de',
        'from_name' => 'meterio by MeckByte',
    ],

    // Geheimes Token, falls cron.php per URL (statt per PHP-CLI) aufgerufen wird:
    //   https://strom.example.de/cron.php?token=DEIN_TOKEN
    'cron_token' => '',

    // Foto-Upload
    'uploads' => [
        'max_files'    => 8,          // pro Speichervorgang
        'max_size_mb'  => 12,         // pro Originalfoto
        'max_dim'      => 1600,       // längste Kante nach Skalierung (px)
        'jpeg_quality' => 78,
    ],
];
