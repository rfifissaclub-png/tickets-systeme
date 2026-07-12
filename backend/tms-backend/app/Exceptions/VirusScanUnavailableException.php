<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown by a VirusScanner implementation when it cannot produce a verdict
 * at all (connection failure, timeout, malformed response) — distinct from
 * a clean scan_result of false. What happens next is a policy decision made
 * by the caller (FileUploadService, via config('attachments.antivirus.fail_closed')),
 * not by the scanner itself.
 */
class VirusScanUnavailableException extends RuntimeException {}
