<?php

namespace App\Config;

final class BrandConfig {
    private const DEFAULT_APP_NAME = 'Vereins-Resultate';
    private const DEFAULT_COLOR_PRIMARY = '#1D284C';
    private const DEFAULT_COLOR_SECONDARY = '#FEB400';
    private const PLACEHOLDER_LOGO = 'assets/branding/logo-placeholder.svg';
    private const LOGO_CANDIDATES = ['assets/branding/logo.svg', 'assets/branding/logo.png'];

    public static function getAppName(): string {
        $value = trim((string)getenv('APP_NAME'));

        return $value !== '' ? $value : self::DEFAULT_APP_NAME;
    }

    public static function getPrimaryColor(): string {
        return self::readColor('BRAND_COLOR_PRIMARY', self::DEFAULT_COLOR_PRIMARY);
    }

    public static function getSecondaryColor(): string {
        return self::readColor('BRAND_COLOR_SECONDARY', self::DEFAULT_COLOR_SECONDARY);
    }

    /**
     * Relativer Pfad fürs Web (für <img src>).
     */
    public static function getLogoWebPath(): string {
        foreach (self::LOGO_CANDIDATES as $relative) {
            if (is_file(__DIR__ . '/../../' . $relative)) {
                return $relative;
            }
        }

        return self::PLACEHOLDER_LOGO;
    }

    /**
     * Absoluter Dateipfad zum Logo.
     */
    public static function getLogoAbsolutePath(): string {
        return realpath(__DIR__ . '/../../' . self::getLogoWebPath());
    }

    /**
     * Logo als Base64-Data-URI (für dompdf/PDF-Export). Dompdf löst lokale Dateipfade
     * je nach Hosting-Umgebung unzuverlässig auf (Chroot-/Protokollprüfung bei file://);
     * eine eingebettete Data-URI umgeht das vollständig.
     */
    public static function getLogoDataUri(): string {
        $path = self::getLogoAbsolutePath();
        $mimeTypes = ['svg' => 'image/svg+xml', 'png' => 'image/png'];
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($path === false || !isset($mimeTypes[$extension])) {
            return '';
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            return '';
        }

        return 'data:' . $mimeTypes[$extension] . ';base64,' . base64_encode($contents);
    }

    private static function readColor(string $envName, string $default): string {
        $value = trim((string)getenv($envName));

        return preg_match('/^#[0-9a-fA-F]{3,8}$/', $value) ? $value : $default;
    }
}
