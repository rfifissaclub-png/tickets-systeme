<?php

namespace App\Contracts;

use App\Exceptions\VirusScanUnavailableException;

/**
 * Which implementation this resolves to is decided entirely by
 * config('attachments.antivirus.enabled') — see
 * AppServiceProvider::register() — so callers (FileUploadService) never
 * branch on whether scanning is turned on; they just always scan.
 */
interface VirusScanner
{
    /**
     * @throws VirusScanUnavailableException if the scanner could not be reached or errored mid-scan
     */
    public function isInfected(string $filePath): bool;
}
