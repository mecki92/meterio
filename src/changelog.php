<?php
declare(strict_types=1);

/**
 * Versionsverlauf für die Benutzer (neueste zuerst).
 * Typen: new = Neu, imp = Verbessert, fix = Behoben
 */
return [
    [
        'version' => '1.0.1', 'date' => '2026-11-01', 'title' => 'Fotos an den Eigentümer, HT/NT-Kachel & Schlussstand',
        'items' => [
            ['imp', 'Ablesung erfassen: Das Datum lässt sich direkt eintippen oder einfügen (z. B. 01.11.2026) und weiterhin im Kalender wählen. Als Anlass stehen nur noch A1–A4 (Ablesung) und B1–B3 (Berechnung) zur Auswahl; bisherige Werte bleiben in alten Ablesungen sichtbar.'],
            ['fix', 'Ablesung erfassen: Der Tarif wird hinter der Registerbezeichnung nicht mehr doppelt angezeigt (z. B. „Niedertarif (NT) · Bezug Niedertarif (NT)“); das Icon bei „Zählerstand“ wurde ersetzt.'],
            ['imp', 'Geteilte Zähler: Liest ein Mitnutzer ab, bekommt der Eigentümer die E-Mail mit den Fotos und der Erinnerung für den Netzbetreiber. Der Mitnutzer erhält eine Info-Mail mit dem Zählerstand.'],
            ['imp', 'Zählerseite: Ablesetabellen aller Jahre haben gleiche Spaltenbreiten, der doppelte Button „Ablesung“ oben entfällt und der Hinweis zur Freigabe ist luftiger.'],
            ['imp', 'Die Kachel „Abschläge bezahlt“ zeigt keinen erwarteten Bonus mehr; er steht weiter in der Zahlungsliste des Vertrags.'],
            ['imp', 'Verbrauchsdiagramm: Die Soll-Linie läuft über die volle Breite und liegt über den Balken, die Werte stehen in den Balken.'],
            ['imp', 'HT/NT-Zähler: Die blaue Zählerkachel zeigt beide Zählwerke mit dem jeweiligen Verbrauch seit der letzten Ablesung.'],
            ['fix', 'Abrechnung: Eine Ablesung am letzten Liefertag gilt als Schlussstand zum Vertragsende. Der Rest des Tages wird nicht mehr aus einer späteren Ablesung hochgerechnet, die überflüssige „anteilig“-Zeile entfällt.'],
            ['fix', 'Zahlungen nach Vertragsende (z. B. Raten für eine Nachzahlung im Folgejahr) werden dem letzten Abrechnungszeitraum zugeordnet, angezeigt und mit der Schlussabrechnung verrechnet.'],
        ],
    ],
    [
        'version' => '1.0.0', 'date' => '2026-10-31', 'title' => 'Erste Version',
        'items' => [
            ['new', 'Zähler erfassen: analog oder digital, mit HT/NT-Mehrtarifregistern und OBIS-Codes, Bezug und Einspeisung.'],
            ['new', 'Ablesungen mit Belegfotos (direkt mit der Kamera oder aus der Galerie), Ableseanlass (u. a. A2, A4, S1, S2) und Plausibilitätsprüfung mit Rückfrage bei unplausiblen Zählerständen.'],
            ['new', 'Neuer Zählerstand per E-Mail: auf Wunsch mit Fotos im Anhang; Zählernummer und Adresse sind teilweise geschwärzt. Ablese-Erinnerungen am festen Tag, zum Monatsende oder zum Vertragsjahresende.'],
            ['new', 'Verträge mit brutto/netto-Preisen, festem Umsatzsteuersatz, Grundpreis (anteilig je Kalenderjahr), Abschlägen und Bonuszahlungen (Buchung am Ende des N-ten Monats ab Vertragsbeginn).'],
            ['new', 'Preisänderungen ab einem Stichtag (Arbeitspreise, Grundgebühr, Einspeisevergütung, Abschlag); die Kosten werden je Preisabschnitt berechnet.'],
            ['new', 'Abrechnungszeitraum wählbar (12, 6, 3 oder 1 Monat) und „Erste Abrechnung am“ für den Stichtag des Netzbetreibers. Jeder Abrechnungszeitraum erscheint als eigene Kachel.'],
            ['new', 'Zahlungen je Abrechnungszeitraum gruppiert und zuordenbar (z. B. Raten für einen Rückstand); Jahresabrechnung mit Verbrauch, Preisen, USt, Abschlägen und Guthaben bzw. Nachzahlung (druckbar).'],
            ['new', 'Dashboard mit Verbrauchs- und Kostendiagrammen (Abschläge vs. Realkosten, Soll-Ist, Prognose) und Zähler-Statistik mit Verbrauch je Kalenderjahr, Monatsverlauf und Jahresvergleich.'],
            ['new', 'Zähler teilen: Freigabe per Code (7 Tage zum Einlösen, einmal verwendbar), wahlweise nur ansehen oder auch ablesen. Verträge, Kosten und Zahlungen werden nie geteilt. Der Zugriff gilt 30 bis maximal 365 Tage, lässt sich verlängern oder löschen; läuft er in weniger als 30 Tagen ab, erscheint ein Hinweis im Dashboard. Der Eigentümer wird bei neuen Ablesungen per E-Mail informiert.'],
            ['new', 'Sicherheit: Zwei-Faktor-Authentifizierung (App oder E-Mail) mit Backup-Codes, verschlüsselte Datenhaltung und Info-Mail bei jeder Anmeldung (Zeitpunkt, Gerät, IP, ungefährer Ort).'],
            ['new', 'Benutzerverwaltung für Administratoren: Benutzer anlegen (Einladungs-Link oder temporäres Passwort), sperren, Passwort/2FA zurücksetzen und Rolle ändern. Administratoren haben keinen Zugriff auf Zähler und Verträge; ein Benutzer mit Zählern oder Verträgen kann nicht zum Administrator werden.'],
            ['new', 'Helles und dunkles Design, für Handy und Desktop; installierbar auf dem Startbildschirm. Impressum, Datenschutzerklärung und diese Versionsseite.'],
        ],
    ],
];
