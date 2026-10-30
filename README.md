# ⚡ meterio by MeckByte

Webapplikation zur Verwaltung von Stromverbräuchen für Haushalte, Vermieter und WGs: Zählerstände (analog/digital, HT/NT, OBIS-Register) mit Foto-Beleg erfassen, Stromverträge und Abschläge pflegen und Realkosten gegen Abschläge in Echtzeit überwachen – inkl. Prognose bis zum Ende des Vertragsjahres.

**Stack:** PHP 8.x · MySQL/MariaDB · Vanilla JS · Pico.css & Chart.js via CDN · kein Node, kein npm, kein Composer, keine Build-Tools.
**Ziel:** netcup Webhosting 4000 (Shared Hosting, ohne Root/Docker) – Upload per FTP/SFTP genügt.

## Funktionen

- **Rollen:** Admin (Benutzer anlegen/deaktivieren/löschen, 2FA & Passwort zurücksetzen) und Standard-User (nur eigene Daten).
- **2FA verpflichtend:** TOTP (Google Authenticator, 1Password, Bitwarden …, QR-Einrichtung) oder 6-stelliger E-Mail-Code, Wahl im Profil; 10 Backup-Codes (einmalig sichtbar, einmal nutzbar); Replay-Schutz und Fehlversuch-Sperre.
- **Verschlüsselung:** Zählerstände, Zähler-/Registerbezeichnungen, Vertragsdaten, Zahlungen, Notizen und die Foto-Dateien werden vor dem Speichern mit **AES-256-GCM** (`openssl_encrypt`) verschlüsselt. Der Schlüssel liegt nur in `config.php` außerhalb des Webroots.
- **Zähler & Register:** freie OBIS-Register pro Zähler (z. B. 1.8.0 / 1.8.1 HT / 1.8.2 NT / 2.8.0 Einspeisung) mit selbst vergebener Bedeutung und Abrechnungs-Zuordnung.
- **Ablesungen:** Datum/Uhrzeit, Werte je Register, Plausibilitätsprüfung, mehrere Fotos (serverseitig per GD skaliert auf max. 1600 px und als JPEG komprimiert, EXIF-Drehung berücksichtigt).
- **Verträge & Zahlungen:** Laufzeit, Grundgebühr (€/Monat oder €/Jahr), Arbeitspreise ET/HT/NT, Einspeisevergütung, Jahresverbrauch, Monatsabschlag; Zahlungen mit Datum, Betrag, Art, Status.
- **Dashboard:** Verbrauch pro Monat/Jahr/Vertragsjahr (Chart.js), Soll-Ist-Vergleich, Hochrechnung der Jahreskosten und voraussichtliches Guthaben bzw. Nachzahlung.
- **E-Mail:** eigener SMTP-Client (netcup), Branding in allen System-Mails, Passwort-vergessen-Link (1 h gültig), Einladungs-Link, Ablese-Erinnerungen per `cron.php`.

## Projektstruktur

```
meterio/
├── public/            ← Webroot (index.php, .htaccess, assets/, cron.php-Wrapper)
├── src/               ← PHP-Klassen (Controller, Auth, Crypto, Mailer, Calc …)
├── views/             ← HTML-Templates
├── storage/           ← verschlüsselte Fotos, Logs, Sessions (nicht öffentlich)
├── bin/create-admin.php
├── cron.php           ← Erinnerungs-Mails + Aufräumen
├── schema.sql         ← Datenbankschema für phpMyAdmin
├── config.example.php ← Vorlage für config.php
└── .htaccess          ← Fallback-Schutz, falls Document-Root nicht auf public/ zeigt
```

## Einrichtung im netcup WSC

### 1. Dateien hochladen
Gesamtes Projekt per SFTP z. B. nach `/httpdocs/meterio/` hochladen (`config.php` kommt erst in Schritt 4 dazu).

### 2. Document-Root setzen (empfohlen)
WSC → **Domains** → Domain/Subdomain bearbeiten → **Document-Root** auf den Ordner `…/meterio/public` stellen. Damit liegen `config.php`, `src/`, `storage/` ausserhalb des Webroots.
*Geht das nicht*, greift die mitgelieferte Root-`.htaccess` als Fallback (sperrt alles ausser `public/`). Prüfe nach dem Setup, dass `https://DEINE-DOMAIN/config.php` und `/storage/` **403/404** liefern.

### 3. Datenbank anlegen
WSC → **Datenbanken** → neue MySQL/MariaDB-Datenbank + Benutzer anlegen → **phpMyAdmin** öffnen → Datenbank wählen → **Importieren** → `schema.sql` hochladen. Hostname, Datenbankname, Benutzer und Passwort notieren.

### 4. Konfiguration
`config.example.php` zu `config.php` kopieren und ausfüllen:

| Eintrag | Bedeutung |
|---|---|
| `app.url` | öffentliche URL ohne Slash am Ende, z. B. `https://strom.example.de` |
| `db.*` | Zugangsdaten aus Schritt 3 |
| `key` | Verschlüsselungsschlüssel (Base64, 32 Byte). Erzeugen: `php -r "echo base64_encode(random_bytes(32));"` – oder einfach `public/index.php` aufrufen, solange `key` leer ist; die Seite zeigt dann einen frischen Schlüssel an. |
| `mail.*` | SMTP-Daten |
| `cron_token` | langer Zufallsstring (nur für Cron per URL) |

> ⚠️ **Schlüssel sichern!** (Passwortmanager + Offline-Kopie). Ohne ihn sind alle verschlüsselten Daten unwiederbringlich verloren. Nach der ersten Nutzung nie mehr ändern. Auch Datenbank **und** `storage/uploads/` müssen gesichert werden.

### 5. E-Mail (netcup SMTP)
1. WSC/Kundenkontrollpanel → **E-Mail** → Postfach anlegen (z. B. `meterio@deine-domain.de`).
2. In `config.php` unter `mail`: `host` = der dem Postfach zugewiesene Mailserver (steht im Kundenkontrollpanel, Form `mx….netcup.net`), `port` `465` + `secure` `ssl` (oder `587` + `tls`), `user` = vollständige Postfach-Adresse, `pass`, `from` = dieselbe Adresse.
3. Test: Anmelden → Profil → 2FA „Code per E-Mail“ → „Testcode senden“. Kommt nichts an, steht der SMTP-Fehler in `storage/logs/error.log`. Zum Debuggen kann `driver` kurz auf `'log'` gestellt werden (Mails landen dann in `storage/logs/mail.log`).

### 6. Ersten Admin anlegen
`https://DEINE-DOMAIN/` aufrufen → du wirst zur **Ersteinrichtung** geleitet (nur solange noch kein Benutzer existiert). Alternativ per SSH: `php bin/create-admin.php "Name" mail@example.de`.
Beim ersten Login richtest du die 2FA ein und sicherst die Backup-Codes. Weitere Benutzer legst du unter **Benutzer** an (Einladungs-Link oder temporäres Passwort).

### 7. Cronjob für Ablese-Erinnerungen
WSC → **Cronjobs** → neuer Job, **täglich** (z. B. 07:30 Uhr):

- als PHP-Skript: `/pfad/zu/meterio/cron.php` (PHP-Version 8.x wählen), **oder**
- per URL: `https://DEINE-DOMAIN/cron.php?token=DEIN_CRON_TOKEN`

Der Job versendet je nach Profil-Einstellung (monatlich am gewählten Tag **oder** 14 Tage vor Ende des Vertragsjahres) Erinnerungen und räumt abgelaufene Tokens/Sessions auf. Ausgabe prüfen: `Fertig. Erinnerungen gesendet: N`.

### 8. Branding
„meterio by MeckByte“ steht in Header, Footer, Login-Screen und allen System-Mails. Zentral änderbar über die Konstante `APP_BRAND` in `src/bootstrap.php`; Farben in `public/assets/app.css` (`--mb-primary`, `--mb-accent`); Mail-Layout in `Mailer::wrap()` (`src/Mailer.php`).

## Hinweise zur Berechnung

- Verbrauch zwischen zwei Ablesungen wird **linear auf die Tage verteilt**; sinkt ein Zählerstand (Zählerwechsel), zählt dieses Intervall als 0 kWh.
- **Realkosten** = Σ (kWh × Arbeitspreis) + Grundgebühr anteilig (Tage/365) − Einspeisevergütung, jeweils ab Beginn des aktuellen Vertragsjahres bis zur letzten Ablesung.
- **Prognose** = bisheriger Verbrauch + Tagesdurchschnitt × Resttage des Vertragsjahres (Warnhinweis bei < 30 Tagen Datenbasis).
- **Guthaben/Nachzahlung** = bezahlte Abschläge + Monatsabschlag × verbleibende volle Monate − prognostizierte Jahreskosten. Zahlungen mit Status „offen“ zählen nicht.
- Preise sind **Brutto-Preise** (inkl. MwSt.). Das Ergebnis ist eine Orientierung, keine Jahresabrechnung.
- Zeiträume ohne zwei umschließende Ablesungen liefern keinen Verbrauch (statt zu schätzen).

## Sicherheit

Argon2id-Passwort-Hashing (Fallback bcrypt) · CSRF-Token auf allen POST-Formularen · Session-ID-Wechsel nach Passwort- und 2FA-Schritt, HttpOnly/SameSite-Cookies, Idle-Timeout (Standard 2 h) · Rate-Limit für Login/2FA/Passwort-Reset · Prepared Statements · Ausgabe-Escaping · CSP-Header · Foto-Auslieferung nur über authentifizierte, verschlüsselte Route · Mandantentrennung in jeder Abfrage.
Die Verschlüsselung schützt vor Datenbank-Leaks/-Backups; sie ersetzt keine Absicherung des Servers selbst. Datumsangaben (Ablesezeitpunkt, Vertragslaufzeit, Zahlungsdatum) bleiben unverschlüsselt, damit Sortierung und Cron funktionieren.

## Entwicklung & Git

Das Repository ist privat auf GitHub. **Nie committen:** `config.php`, `.env`, Inhalte von `storage/` (steht in `.gitignore`).

```bash
git clone https://github.com/mecki92/meterio.git
cp config.example.php config.php        # anpassen
php -S 127.0.0.1:8080 -t public          # lokaler Test (Pfad-Routing nur mit Router-Skript, siehe unten)
```

Der PHP-Entwicklungsserver braucht für statische Dateien ein kleines Router-Skript, auf Apache (netcup) übernimmt das die `.htaccess`:

```php
<?php // dev-router.php
$f = __DIR__ . '/public' . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (is_file($f) && !str_ends_with($f, '.php')) { return false; }
require __DIR__ . '/public/index.php';
```
`php -S 127.0.0.1:8080 -t public dev-router.php`

Deployment: Änderungen pushen, dann per SFTP hochladen (`config.php` und `storage/` auf dem Server unangetastet lassen).

## Lizenz / Copyright
© MeckByte – alle Rechte vorbehalten.
