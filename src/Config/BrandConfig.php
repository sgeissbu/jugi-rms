<?php

namespace App\Config;

final class BrandConfig {
    private const DEFAULT_APP_NAME = 'Vereins-Resultate';
    private const DEFAULT_COLOR_PRIMARY = '#1D284C';
    private const DEFAULT_COLOR_SECONDARY = '#FEB400';
    private const PLACEHOLDER_LOGO = 'assets/branding/logo-placeholder.svg';
    private const LOGO_CANDIDATES = ['assets/branding/logo.svg', 'assets/branding/logo.png'];
    private const DARK_LOGO_CANDIDATES = ['assets/branding/logo-dark.svg', 'assets/branding/logo-dark.png'];
    private const FAVICON_CANDIDATES = ['favicon.png', 'assets/branding/favicon.png'];

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
     * Optionales zweites Logo für den Dunkelmodus (z.B. helle Schrift); null = kein eigenes.
     */
    public static function getDarkLogoWebPath(): ?string {
        foreach (self::DARK_LOGO_CANDIDATES as $relative) {
            if (is_file(__DIR__ . '/../../' . $relative)) {
                return $relative;
            }
        }

        return null;
    }

    /**
     * Logo als <picture>: im Dunkelmodus das Dunkel-Logo, falls vorhanden.
     */
    public static function logoHtml(string $class = 'logo', string $alt = ''): string {
        $esc = function (string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); };
        $dark = self::getDarkLogoWebPath();

        return '<picture>' . ($dark ? '<source srcset="' . $esc($dark) . '" media="(prefers-color-scheme: dark)">' : '')
            . '<img class="' . $esc($class) . '" src="' . $esc(self::getLogoWebPath()) . '" alt="' . $esc($alt) . '"></picture>';
    }

    /**
     * Tab-Symbol: favicon.png (Wurzel oder assets/branding), sonst das Logo.
     */
    public static function getFaviconWebPath(): string {
        foreach (self::FAVICON_CANDIDATES as $relative) {
            if (is_file(__DIR__ . '/../../' . $relative)) {
                return $relative;
            }
        }

        return self::getLogoWebPath();
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

    /**
     * Gut lesbare Schriftfarbe auf einer Vereinsfarbe (hell oder dunkel, nach relativer Helligkeit).
     */
    public static function textOn(string $hex): string {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
        }
        $rgb = array_map(function ($c) {
            $v = hexdec($c) / 255;
            return $v <= 0.03928 ? $v / 12.92 : (($v + 0.055) / 1.055) ** 2.4;
        }, str_split(substr($hex, 0, 6), 2));
        $luminance = 0.2126 * $rgb[0] + 0.7152 * $rgb[1] + 0.0722 * $rgb[2];

        return $luminance > 0.36 ? '#141A2E' : '#FFFFFF';
    }

    private static function readColor(string $envName, string $default): string {
        $value = trim((string)getenv($envName));

        return preg_match('/^#[0-9a-fA-F]{3,8}$/', $value) ? $value : $default;
    }
}
