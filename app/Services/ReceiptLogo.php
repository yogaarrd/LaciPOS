<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ReceiptLogo
{
    public function dataUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $source = $target = null;
        try {
            $file = Storage::disk('public')->path($path);
            if (!is_file($file) || filesize($file) > 2 * 1024 * 1024) {
                return null;
            }
            $info = @getimagesize($file);
            if (!$info || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true)
                || $info[0] * $info[1] > 16000000) {
                return null;
            }
            $bytes = file_get_contents($file);
            if (!function_exists('imagecreatefromstring')) {
                // Small existing images can still print when GD is unavailable.
                return max($info[0], $info[1]) <= 512 && strlen($bytes) <= 49152
                    ? 'data:'.$info['mime'].';base64,'.base64_encode($bytes) : null;
            }
            $source = @imagecreatefromstring($bytes);
            if (!$source) {
                return null;
            }
            $scale = min(1, 256 / max($info[0], $info[1]));
            $width = max(1, (int) round($info[0] * $scale));
            $height = max(1, (int) round($info[1] * $scale));
            $target = imagecreatetruecolor($width, $height);
            imagefill($target, 0, 0, imagecolorallocate($target, 255, 255, 255));
            imagecopyresampled($target, $source, 0, 0, 0, 0, $width, $height, $info[0], $info[1]);
            ob_start();
            try {
                imagepng($target, null, 9);
                $png = ob_get_contents();
            } finally {
                ob_end_clean();
            }
            if (strlen($png) <= 49152) {
                return 'data:image/png;base64,'.base64_encode($png);
            }
            ob_start();
            try {
                imagejpeg($target, null, 75);
                $jpeg = ob_get_contents();
            } finally {
                ob_end_clean();
            }
            return strlen($jpeg) <= 49152 ? 'data:image/jpeg;base64,'.base64_encode($jpeg) : null;
        } catch (\Throwable $e) {
            Log::warning('Receipt logo unavailable; printing text receipt', ['reason' => $e->getMessage()]);
            return null;
        } finally {
            if ($source) { imagedestroy($source); }
            if ($target) { imagedestroy($target); }
        }
    }
}
