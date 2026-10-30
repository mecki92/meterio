<?php
// Web-Aufruf des Cronjobs (nur mit gültigem Token). Alternativ: "php /pfad/zu/cron.php" per CLI.
define('CRON_VIA_WEB', true);
require dirname(__DIR__) . '/cron.php';
