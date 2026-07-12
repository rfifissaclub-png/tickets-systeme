<?php

namespace App\Services\Attachments;

use App\Contracts\VirusScanner;

/**
 * Bound in place of ClamAvScanner when
 * config('attachments.antivirus.enabled') is false — a fresh environment
 * with no clamd deployed shouldn't fail every upload with "scanner
 * unreachable" until someone stands one up. Every file reports clean
 * unconditionally; this is a deliberate no-op, not a placeholder.
 */
class NullVirusScanner implements VirusScanner
{
    public function isInfected(string $filePath): bool
    {
        return false;
    }
}
