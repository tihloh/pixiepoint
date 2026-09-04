<?php

declare(strict_types=1);

namespace PixiePoint\App\Admin\Users;

use RuntimeException;

/**
 * Stores only processed profile pictures generated from the client crop tool.
 */
final class AvatarService
{
    public function __construct(private string $root)
    {
    }

    public function store(int $userId, string $dataUrl): string
    {
        $this->requireImageProcessing();

        if (!preg_match('#^data:image/(?:webp|png|jpeg);base64,(.+)$#', $dataUrl, $matches)) {
            throw new RuntimeException('Choose and crop a valid profile picture.');
        }

        $bytes = base64_decode($matches[1], true);
        if ($bytes === false || strlen($bytes) > 8 * 1024 * 1024) {
            throw new RuntimeException('The profile picture is invalid or too large.');
        }

        $source = @\imagecreatefromstring($bytes);
        if (!$source) {
            throw new RuntimeException('The profile picture could not be processed.');
        }

        $width = \imagesx($source);
        $height = \imagesy($source);
        if ($width < 64 || $height < 64) {
            \imagedestroy($source);
            throw new RuntimeException('The profile picture is too small.');
        }

        $size = 512;
        $output = \imagecreatetruecolor($size, $size);
        if (!$output) {
            \imagedestroy($source);
            throw new RuntimeException('The profile picture could not be processed.');
        }

        \imagealphablending($output, false);
        \imagesavealpha($output, true);
        \imagecopyresampled(
            $output,
            $source,
            0,
            0,
            0,
            0,
            $size,
            $size,
            $width,
            $height,
        );

        $directory = $this->root . '/public/uploads/avatars';
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            \imagedestroy($source);
            \imagedestroy($output);
            throw new RuntimeException('Avatar storage is unavailable.');
        }

        $filename = 'user-' . $userId . '-' . bin2hex(random_bytes(6)) . '.webp';
        $path = $directory . '/' . $filename;

        if (!\imagewebp($output, $path, 82)) {
            \imagedestroy($source);
            \imagedestroy($output);
            throw new RuntimeException('The profile picture could not be saved.');
        }

        \imagedestroy($source);
        \imagedestroy($output);

        return '/uploads/avatars/' . $filename;
    }

    private function requireImageProcessing(): void
    {
        if (
            !extension_loaded('gd')
            || !function_exists('imagecreatefromstring')
            || !function_exists('imagecreatetruecolor')
            || !function_exists('imagewebp')
        ) {
            throw new RuntimeException(
                'Profile picture processing is unavailable. Rebuild the PixiePoint PHP container to enable GD/WebP support.',
            );
        }
    }
}
