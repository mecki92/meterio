<?php
declare(strict_types=1);

/**
 * Versionsverlauf für die Benutzer (neueste zuerst).
 * Typen: new = Neu, imp = Verbessert, fix = Behoben
 */
return [
    [
        'version' => '1.4.0', 'date' => '2026-10-31', 'title' => 'Preisänderungen im Vertrag & Abrechnungszeitraum',
        'items' => [
            ['new', 'Preisänderungen im Vertragsverlauf: Arbeitspreise, Grundgebühr, Einspeisevergütung und Abschlag lassen sich ab einem Stichtag ändern. Leere Felder gelten als „bisher“.'],
            ['new', 'Abrechnungszeitraum wählbar (12, 6, 3 oder 1 Monat) – z. B. für monatlich kündbare Verträge mit unterjähriger Abrechnung. Boni, Fortschritt und Abrechnung richten sich danach.'],
            ['new', 'Erste Abrechnung am: Abrechnungsstichtag des Netzbetreibers/Anbieters eintragen – der erste Zeitraum endet dort, danach wird im gewählten Abrechnungszeitraum weiter abgerechnet (bis zum Vertragsende).'],
            ['new', 'Zahlungen sind nach Abrechnungszeitraum gruppiert (mit Summe je Zeitraum) und lassen sich manuell einem Zeitraum zuordnen, z. B. Raten für einen Rückstand.'],
            ['imp', 'Vertragsansicht: oben wird der Abrechnungszeitraum gewählt, darunter zeigen die Reiter „Zahlungen“ und „Abrechnung“ nur diesen Zeitraum. Neue Zahlungen werden dem gewählten Zeitraum zugeordnet.'],
            ['new', 'Zähler-Statistik: Verbrauch je Kalenderjahr (anteilig zwischen den Ablesungen, mit Hochrechnung, Ø pro Tag und Vorjahresvergleich, getrennt nach HT/NT und Einspeisung) sowie Monatsverlauf im Jahresvergleich. Aufrufbar über „Statistik“ am Zähler.'],
            ['new', 'Vertragsübersicht: Jeder Abrechnungszeitraum erscheint als eigene Kachel (aktueller oben, abgeschlossene darunter mit Guthaben/Nachzahlung) – auch bei laufenden Verträgen.'],
            ['fix', 'Bonus: Buchung am Ende des N-ten Monats ab Vertragsbeginn (z. B. Startguthaben „nach 1 Monat“ nach einem Monat) statt erst zum Ende des Abrechnungszeitraums.'],
            ['fix', 'Zahlungen mit unplausiblem Datum (z. B. Jahr 0003) werden abgelehnt.'],
            ['imp', 'Die Kosten werden je Preisabschnitt berechnet; die Abrechnung zeigt jeden Abschnitt mit eigenem Preis in einer Zeile.'],
            ['imp', 'Vertragsansicht zeigt die aktuell gültigen Preise und eine Liste aller Preisänderungen; das Soll-Ist-Diagramm berücksichtigt geänderte Abschläge.'],
        ],
    ],
    [
        'version' => '1.3.0', 'date' => '2026-10-31', 'title' => 'Bonuszahlungen, E-Mail-Benachrichtigungen & überarbeitete Formulare',
        'items' => [
            ['new', 'Bonuszahlungen: Neukundenboni werden als erwartete Zahlung am Ende des Vertragsjahres gebucht (z. B. zum Vertragsende) und erscheinen in den Zahlungen, im Soll-Ist-Diagramm und in der Jahresabrechnung. Endet der Vertrag früher, entfällt der Bonus.'],
            ['new', 'Neuer Zählerstand per E-Mail: auf Wunsch mit Fotos im Anhang und der Erinnerung, den Stand beim Netzbetreiber einzutragen. Zählernummer und Adresse sind in der Mail teilweise geschwärzt.'],
            ['new', 'Ablese-Erinnerung zum Monatsende (zusätzlich zu festem Tag im Monat und Vertragsjahresende).'],
            ['new', 'Impressum, Datenschutzerklärung und diese Versionsseite.'],
            ['new', 'App-Icon und Webmanifest: meterio lässt sich auf dem Startbildschirm installieren.'],
            ['imp', 'Vertragsformular im Stil der Zählerseiten neu gestaltet; Hinweise stehen als Info-Icons hinter den Feldbezeichnungen.'],
            ['imp', 'Profil neu gestaltet mit Auswahlkarten für die Erinnerung.'],
            ['imp', 'Euro-Beträge haben überall mindestens zwei Nachkommastellen, Arbeitspreise im Formular ebenfalls.'],
            ['imp', 'Jahresabrechnung nennt die Ablesearten mit vollem Namen (z. B. „A4 · Selbstablesung Kunde“).'],
            ['imp', 'Konditionen zeigen „brutto“ bzw. „netto“ direkt beim jeweiligen Preis.'],
            ['imp', 'Anmeldeseiten ohne unnötige Hinweise; Hinweis zu Backup-Codes übersichtlicher.'],
            ['fix', 'Menüpunkte in der Navigation berühren sich nicht mehr.'],
        ],
    ],
    [
        'version' => '1.2.0', 'date' => '2026-10-30', 'title' => 'Jahresabrechnung & Preise',
        'items' => [
            ['new', 'Abrechnungsübersicht für abgeschlossene Verträge je Vertragsjahr (Verbrauch je Ablesung, Arbeitspreis, Grundpreis, USt, Abschläge, Guthaben bzw. Nachzahlung; druckbar).'],
            ['new', 'Preise wahlweise brutto oder netto erfassen, mit festem Umsatzsteuersatz je Vertrag (19 %, 7 %, 0 %); Arbeitspreise mit bis zu 6 Nachkommastellen.'],
            ['new', 'Grundpreis wird je Kalenderjahr anteilig berechnet; Beträge werden wie auf der Rechnung gerundet.'],
            ['new', 'Neue Ableseanlässe: A2, A4, S1 und S2.'],
            ['new', 'Rückfrage per Dialog, wenn ein Zählerstand unplausibel erscheint.'],
            ['imp', 'Vertragsseite mit Reitern „Zahlungen“ und „Abrechnung“; zukünftige und abgeschlossene Verträge werden klar dargestellt.'],
            ['imp', 'Zählerübersicht, Zählerdetails und Zählerformular im Stil der Vertragsseiten; Ablesungen sind je Kalenderjahr gebündelt.'],
            ['imp', 'Dashboard: Verbrauchsdiagramm startet mit dem Vertragsjahr und zeigt Summe, Durchschnitt und Soll-Abweichung.'],
        ],
    ],
];
