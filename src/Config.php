<?php
declare(strict_types=1);

final class Config
{
    private static array $data = [];

    public static function load(): void
    {
        $f = ROOT . '/config.php';
        if (!is_file($f)) {
            throw new RuntimeException('config.php fehlt. Bitte config.example.php kopieren und anpassen.');
        }
        $cfg = require $f;
        self::$data = is_array($cfg) ? $cfg : [];
    }

    public static function get(string $path, mixed $default = null): mixed
    {
        $cur = self::$data;
        foreach (explode('.', $path) as $seg) {
            if (!is_array($cur) || !array_key_exists($seg, $cur)) {
                return $default;
            }
            $cur = $cur[$seg];
        }
        return $cur;
    }
}
