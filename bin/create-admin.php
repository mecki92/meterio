<?php
declare(strict_types=1);

/**
 * Legt einen Admin-Account per Kommandozeile an (Alternative zur Web-Ersteinrichtung unter /setup).
 * Aufruf: php bin/create-admin.php "Name" mail@example.de
 */
if (PHP_SAPI !== 'cli') {
    exit('CLI only');
}
require dirname(__DIR__) . '/src/bootstrap.php';

[, $name, $email] = $argv + [null, null, null];
if (!$name || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Aufruf: php bin/create-admin.php \"Name\" mail@example.de\n");
    exit(1);
}
if (Auth::findByEmail($email)) {
    fwrite(STDERR, "E-Mail existiert bereits.\n");
    exit(1);
}
echo 'Passwort (min. 10 Zeichen, Buchstaben+Ziffern): ';
$pw = trim((string) fgets(STDIN));
if ($err = Auth::passwordError($pw)) {
    fwrite(STDERR, $err . "\n");
    exit(1);
}
Auth::createUser($email, $name, $pw, 'admin');
echo "Admin angelegt. Beim ersten Login wird die 2FA eingerichtet.\n";
