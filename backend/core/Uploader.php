<?php
declare(strict_types=1);

namespace App\Core;

class Uploader {
    private const ALLOWED_MIME = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'application/pdf' => 'pdf'
    ];

    public static function uploadPrescription(array $file): ?string {
        if (!isset($file['error']) || is_array($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return null;
        }

        // Limit size (Max 10MB)
        if ($file['size'] > 10 * 1024 * 1024) {
            return null;
        }

        // Real MIME verification via finfo (cannot be spoofed by extension)
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);

        if (!array_key_exists($mime, self::ALLOWED_MIME)) {
            return null;
        }

        $extension = self::ALLOWED_MIME[$mime];
        $filename = 'rx_' . date('Ymd_His') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
        $targetDir = __DIR__ . '/../../storage/prescriptions/';

        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0755, true);
        }

        if (move_uploaded_file($file['tmp_name'], $targetDir . $filename)) {
            return $filename;
        }

        return null;
    }
}
