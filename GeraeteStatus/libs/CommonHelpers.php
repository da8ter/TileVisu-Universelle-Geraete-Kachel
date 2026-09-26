<?php

declare(strict_types=1);

namespace UDT;

/**
 * Statische Hilfsfunktionen ohne Instanzbezug.
 */
final class Helpers
{
    public static function decodeJsonArray(mixed $value, string $context): ?array
    {
        if (is_array($value)) {
            return $value;
        }
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $decoded = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        if (!is_array($decoded)) {
            return null;
        }

        return $decoded;
    }

    /**
     * Liefert den data-URI-Präfix für eine Bilddatei-Endung oder '' bei nicht unterstütztem Typ.
     * Bewusst case-sensitiv (Verhalten der bisherigen switch-Blöcke).
     */
    public static function mimePrefixFromExtension(string $ext): string
    {
        switch ($ext) {
            case 'bmp':  return 'data:image/bmp;base64,';
            case 'jpg':
            case 'jpeg': return 'data:image/jpeg;base64,';
            case 'gif':  return 'data:image/gif;base64,';
            case 'png':  return 'data:image/png;base64,';
            case 'ico':  return 'data:image/x-icon;base64,';
            case 'webp': return 'data:image/webp;base64,';
            default:     return '';
        }
    }

    /**
     * Erkennt den MIME-Type eines Bildes anhand der ersten Bytes (Magic Numbers).
     */
    public static function detectMimeFromBinary(string $bin): string
    {
        $hdr = substr($bin, 0, 12);
        if (strncmp($hdr, "\xFF\xD8\xFF", 3) === 0) return 'image/jpeg';
        if (strncmp($hdr, "\x89PNG\x0D\x0A\x1A\x0A", 8) === 0) return 'image/png';
        if (strncmp($hdr, 'GIF87a', 6) === 0 || strncmp($hdr, 'GIF89a', 6) === 0) return 'image/gif';
        if (substr($hdr, 0, 4) === 'RIFF' && substr($hdr, 8, 4) === 'WEBP') return 'image/webp';
        if (strncmp($hdr, 'BM', 2) === 0) return 'image/bmp';
        return 'application/octet-stream';
    }
}
