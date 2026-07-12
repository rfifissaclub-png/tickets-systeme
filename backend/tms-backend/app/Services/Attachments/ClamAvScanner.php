<?php

namespace App\Services\Attachments;

use App\Contracts\VirusScanner;
use App\Exceptions\VirusScanUnavailableException;

/**
 * Speaks ClamAV's clamd INSTREAM protocol directly over a plain TCP socket
 * (see https://docs.clamav.net/manual/Usage/Scanning.html#stream-scanning):
 * a `zINSTREAM\0` command, then the file as a series of
 * 4-byte-big-endian-length-prefixed chunks, terminated by a zero-length
 * chunk, then a single response line. No composer package needed — the
 * wire protocol is a handful of lines, and implementing it directly avoids
 * trusting an extra dependency for something this small.
 */
class ClamAvScanner implements VirusScanner
{
    /** Matches clamd.conf's default StreamMaxLength chunking; arbitrary otherwise. */
    private const int CHUNK_SIZE = 8192;

    public function __construct(
        private readonly string $host,
        private readonly int $port,
        private readonly int $timeoutSeconds,
    ) {}

    public function isInfected(string $filePath): bool
    {
        $socket = @fsockopen($this->host, $this->port, $errno, $errstr, $this->timeoutSeconds);

        if ($socket === false) {
            throw new VirusScanUnavailableException("Could not connect to clamd at {$this->host}:{$this->port}: {$errstr} ({$errno})");
        }

        try {
            stream_set_timeout($socket, $this->timeoutSeconds);
            fwrite($socket, "zINSTREAM\0");

            $handle = fopen($filePath, 'rb');

            if ($handle === false) {
                throw new VirusScanUnavailableException("Could not open {$filePath} for scanning.");
            }

            try {
                while (! feof($handle)) {
                    $chunk = fread($handle, self::CHUNK_SIZE);

                    if ($chunk === false || $chunk === '') {
                        break;
                    }

                    fwrite($socket, pack('N', strlen($chunk)).$chunk);
                }
            } finally {
                fclose($handle);
            }

            // Zero-length chunk signals end of stream, per protocol.
            fwrite($socket, pack('N', 0));

            $response = fgets($socket);
            $meta = stream_get_meta_data($socket);

            if ($response === false || $meta['timed_out']) {
                throw new VirusScanUnavailableException("clamd at {$this->host}:{$this->port} did not respond in time.");
            }

            $response = trim($response);

            if (str_ends_with($response, 'OK')) {
                return false;
            }

            if (str_contains($response, 'FOUND')) {
                return true;
            }

            // Any other reply (protocol/config error, e.g. file too large
            // for clamd's own StreamMaxLength) is treated as "scanner
            // couldn't give a verdict", not "clean" — the caller's
            // fail_closed policy decides what happens next.
            throw new VirusScanUnavailableException("Unexpected clamd response: {$response}");
        } finally {
            fclose($socket);
        }
    }
}
