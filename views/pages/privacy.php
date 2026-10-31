<?php /** @var array $legal */
$host = $legal['host'] ?: 'dem Hoster';
$secs = [
    ['bi-person-vcard', 'Verantwortlicher', '<p class="mb-0">' . e($legal['name'] ?: '[Name]') . ', ' . e($legal['street'] ?: '[Straße]') . ', ' . e($legal['city'] ?: '[PLZ Ort]') . '<br>E-Mail: ' . e($legal['email'] ?: '[E-Mail]') . '</p>'],
    ['bi-database-lock', 'Welche Daten verarbeitet werden', '<ul class="mb-0 ps-3">'
        . '<li><b>Konto:</b> Name, E-Mail-Adresse, Passwort (nur als Hash), Einstellungen zu Zwei-Faktor-Anmeldung und Erinnerungen.</li>'
        . '<li><b>Inhalte, die du erfasst:</b> Zähler, Zählerstände, Ablesefotos, Verträge, Preise und Zahlungen. Diese Angaben werden in der Datenbank bzw. im Dateispeicher verschlüsselt (AES-256-GCM) abgelegt.</li>'
        . '<li><b>Technische Daten:</b> IP-Adresse und Zeitpunkt von Anmeldeversuchen (zur Abwehr von Missbrauch, nur kurzzeitig gespeichert) sowie die üblichen Server-Logdateien des Hosters.</li>'
        . '<li><b>E-Mails:</b> Anmelde-Codes, Links zum Zurücksetzen des Passworts, Ablese-Erinnerungen und – wenn aktiviert – neu erfasste Zählerstände samt Fotos werden an deine hinterlegte Adresse gesendet.</li></ul>'],
    ['bi-bullseye', 'Zwecke und Rechtsgrundlagen', '<p class="mb-0">Die Verarbeitung dient dem Betrieb der Anwendung und der Erfüllung der Nutzung (Art. 6 Abs. 1 lit. b DSGVO) sowie der Sicherheit des Systems (Art. 6 Abs. 1 lit. f DSGVO, berechtigtes Interesse). Eine Auswertung zu Werbe- oder Trackingzwecken findet nicht statt.</p>'],
    ['bi-cookie', 'Cookies und lokale Speicherung', '<p class="mb-0">Es wird ein technisch notwendiges Sitzungs-Cookie für die Anmeldung verwendet. Die gewählte Darstellung (hell/dunkel) wird im Browser gespeichert. Es werden keine Analyse- oder Werbe-Cookies eingesetzt.</p>'],
    ['bi-hdd-network', 'Hosting und Empfänger', '<p class="mb-0">Die Anwendung wird bei ' . e($host) . ' betrieben. Mit dem Hoster wird ein Vertrag zur Auftragsverarbeitung (Art. 28 DSGVO) geschlossen. Der E-Mail-Versand erfolgt über den SMTP-Server des Hosters.</p>'],
    ['bi-cloud-arrow-down', 'Eingebundene Dienste (CDN)', '<p class="mb-0">Zur Darstellung werden Bootstrap, Bootstrap Icons und Chart.js über das Content-Delivery-Network <b>jsDelivr</b> (Anbieter: Prospect One, Polen/USA) geladen. Dabei wird deine IP-Adresse an den Anbieter übermittelt. Rechtsgrundlage ist Art. 6 Abs. 1 lit. f DSGVO (effiziente und sichere Bereitstellung der Anwendung).</p>'],
    ['bi-hourglass-split', 'Speicherdauer', '<p class="mb-0">Deine Daten werden gespeichert, solange dein Konto besteht. Beim Löschen des Kontos werden alle zugehörigen Daten und Fotos entfernt. Anmeldeversuche werden nach wenigen Tagen automatisch gelöscht.</p>'],
    ['bi-shield-check', 'Deine Rechte', '<p class="mb-0">Du hast das Recht auf Auskunft, Berichtigung, Löschung, Einschränkung der Verarbeitung, Datenübertragbarkeit und Widerspruch (Art. 15–21 DSGVO). Wende dich dazu an die oben genannte Adresse. Außerdem kannst du dich bei einer Datenschutz-Aufsichtsbehörde beschweren.</p>'],
];
?>
<div class="page-head">
    <div>
        <h1>Datenschutzerklärung</h1>
        <p class="sub">Wie meterio mit deinen Daten umgeht</p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= e(url('impressum')) ?>"><i class="bi bi-person-vcard"></i> Impressum</a>
</div>
<div class="row g-3">
    <?php foreach ($secs as $i => [$ic, $t, $html]): ?>
        <div class="<?= $i < 2 ? 'col-12' : 'col-lg-6' ?>"><div class="card h-100">
            <div class="card-header"><i class="bi <?= e($ic) ?>"></i> <?= ($i + 1) ?>. <?= e($t) ?></div>
            <div class="card-body"><?= $html ?></div>
        </div></div>
    <?php endforeach; ?>
</div>
