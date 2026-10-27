<?php

declare(strict_types=1);

/*
 * Nextcloud requires this file (OC_App::registerAutoloading) instead of
 * registering its default PSR-4 mapping for lib/, so both are done here:
 * the app's own classes and the bundled runtime libraries in /3rdparty
 * (FPDF/FPDI, see build/3rdparty/composer.json).
 */

spl_autoload_register(static function (string $class): void {
    $prefix = 'OCA\\DonationReceipts\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = __DIR__ . '/../lib/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require_once __DIR__ . '/../3rdparty/autoload.php';
