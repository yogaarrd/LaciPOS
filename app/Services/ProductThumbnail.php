<?php

namespace App\Services;

class ProductThumbnail
{
    public function path(string $original): string
    {
        if (!function_exists('imagecreatefromstring')) {
            return $original;
        }

        $source = $thumbnail = null;
        $temporary = null;
        try {
            $directory = storage_path('framework/cache/product-thumbnails');
            $cached = $directory.'/'.hash('sha256', $original.'|'.filemtime($original).'|'.filesize($original)).'.jpg';
            if (is_file($cached)) {
                return $cached;
            }
            $info = @getimagesize($original);
            if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)
                || $info[0] * $info[1] > 16000000 || filesize($original) > 5 * 1024 * 1024) {
                return $original;
            }
            $source = @imagecreatefromstring(file_get_contents($original));
            if (!$source) {
                return $original;
            }
            $scale = min(1, 320 / max($info[0], $info[1]));
            $width = max(1, (int) round($info[0] * $scale));
            $height = max(1, (int) round($info[1] * $scale));
            $thumbnail = imagecreatetruecolor($width, $height);
            imagefill($thumbnail, 0, 0, imagecolorallocate($thumbnail, 255, 255, 255));
            imagecopyresampled($thumbnail, $source, 0, 0, 0, 0, $width, $height, $info[0], $info[1]);
            if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
                return $original;
            }
            $temporary = tempnam($directory, 'thumb-');
            if (!$temporary || !imagejpeg($thumbnail, $temporary, 80) || !rename($temporary, $cached)) {
                return $original;
            }
            return $cached;
        } catch (\Throwable $e) {
            return $original;
        } finally {
            if ($source) { imagedestroy($source); }
            if ($thumbnail) { imagedestroy($thumbnail); }
            if ($temporary && is_file($temporary)) { unlink($temporary); }
        }
    }
}
