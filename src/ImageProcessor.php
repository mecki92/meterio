<?php
declare(strict_types=1);

/** Foto-Upload: validieren, drehen (EXIF), skalieren, als JPEG komprimieren, verschlüsselt speichern. */
final class ImageProcessor
{
    public static function dir(int $userId): string
    {
        $d = ROOT . '/storage/uploads/' . $userId;
        if (!is_dir($d)) {
            mkdir($d, 0750, true);
        }
        return $d;
    }

    /**
     * Verarbeitet $_FILES['photos'] und gibt eine Liste [file_name, size] zurück.
     * Fehler werden als Warnungen in $errors gesammelt.
     */
    public static function handleUploads(int $userId, array $files, array &$errors): array
    {
        $out = [];
        if (empty($files['name'])) {
            return $out;
        }
        $maxFiles = (int) Config::get('uploads.max_files', 8);
        $maxBytes = (int) Config::get('uploads.max_size_mb', 12) * 1048576;
        $count = is_array($files['name']) ? count($files['name']) : 0;

        for ($i = 0; $i < $count; $i++) {
            if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            if (count($out) >= $maxFiles) {
                $errors[] = "Maximal $maxFiles Fotos pro Speichervorgang – weitere wurden ignoriert.";
                break;
            }
            $name = (string) $files['name'][$i];
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                $errors[] = "„{$name}“: Upload fehlgeschlagen (Code {$files['error'][$i]}).";
                continue;
            }
            if ($files['size'][$i] > $maxBytes) {
                $errors[] = "„{$name}“ ist größer als " . (int) Config::get('uploads.max_size_mb', 12) . ' MB.';
                continue;
            }
            $tmp = $files['tmp_name'][$i];
            if (!is_uploaded_file($tmp)) {
                continue;
            }
            try {
                $jpeg = self::process($tmp);
                $fileName = bin2hex(random_bytes(16)) . '.bin';
                $fileName = substr($fileName, 0, 36);
                file_put_contents(self::dir($userId) . '/' . $fileName, Crypto::encBytes($jpeg), LOCK_EX);
                $out[] = [$fileName, strlen($jpeg)];
            } catch (Throwable $e) {
                $errors[] = "„{$name}“: " . $e->getMessage();
            }
        }
        return $out;
    }

    /** Liefert komprimierte JPEG-Bytes. */
    public static function process(string $path): string
    {
        @ini_set('memory_limit', '256M');
        $info = @getimagesize($path);
        if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP, IMAGETYPE_GIF], true)) {
            throw new RuntimeException('Nur JPG, PNG, WEBP oder GIF erlaubt.');
        }
        [$w, $h] = $info;
        if ($w * $h > 50_000_000) {
            throw new RuntimeException('Bild hat zu viele Pixel.');
        }
        $img = @imagecreatefromstring((string) file_get_contents($path));
        if (!$img) {
            throw new RuntimeException('Bild konnte nicht gelesen werden.');
        }

        if ($info[2] === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
            $exif = @exif_read_data($path);
            $rot = match ((int) ($exif['Orientation'] ?? 1)) {
                3 => 180, 6 => -90, 8 => 90, default => 0,
            };
            if ($rot !== 0) {
                $rotated = imagerotate($img, $rot, 0);
                if ($rotated) {
                    $img = $rotated;
                }
            }
        }

        $w = imagesx($img);
        $h = imagesy($img);
        $max = (int) Config::get('uploads.max_dim', 1600);
        $scale = min(1.0, $max / max($w, $h));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $dst = imagecreatetruecolor($nw, $nh);
        imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255)); // Transparenz -> weiss
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);

        ob_start();
        imagejpeg($dst, null, (int) Config::get('uploads.jpeg_quality', 78));
        return (string) ob_get_clean();
    }

    public static function read(int $userId, string $fileName): ?string
    {
        $f = self::dir($userId) . '/' . basename($fileName);
        return is_file($f) ? Crypto::decBytes((string) file_get_contents($f)) : null;
    }

    public static function delete(int $userId, string $fileName): void
    {
        $f = self::dir($userId) . '/' . basename($fileName);
        if (is_file($f)) {
            @unlink($f);
        }
    }

    public static function deleteUserDir(int $userId): void
    {
        $d = ROOT . '/storage/uploads/' . $userId;
        if (is_dir($d)) {
            foreach (glob($d . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($d);
        }
    }
}
