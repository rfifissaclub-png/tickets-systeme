<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * API-appropriate locale negotiation: a client sends `Accept-Language`, not a
 * session cookie (this app has no server-rendered pages to remember a
 * locale in a session for). Only region subtags are dropped (fr-FR -> fr) —
 * we don't currently ship region-specific variants of a locale.
 */
class SetLocale
{
    /** @var list<string> */
    private const SUPPORTED_LOCALES = ['en', 'fr'];

    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale($this->resolveLocale($request));

        return $next($request);
    }

    private function resolveLocale(Request $request): string
    {
        $header = $request->header('Accept-Language');

        if ($header) {
            foreach ($this->parseAcceptLanguage($header) as $lang) {
                $primary = strtolower(substr($lang, 0, 2));

                if (in_array($primary, self::SUPPORTED_LOCALES, true)) {
                    return $primary;
                }
            }
        }

        return config('app.locale');
    }

    /**
     * Parses "fr-FR,fr;q=0.9,en;q=0.8" into languages ordered by descending
     * quality (priority), highest first.
     *
     * @return list<string>
     */
    private function parseAcceptLanguage(string $header): array
    {
        $withPriority = array_map(function (string $entry) {
            [$lang, $quality] = array_pad(explode(';q=', trim($entry)), 2, '1');

            return ['lang' => $lang, 'quality' => (float) $quality];
        }, explode(',', $header));

        usort($withPriority, fn (array $a, array $b) => $b['quality'] <=> $a['quality']);

        return array_column($withPriority, 'lang');
    }
}
