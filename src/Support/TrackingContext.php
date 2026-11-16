<?php

namespace MohamedSaid\LaravelGa4Events\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class TrackingContext
{
    public const UTM_KEYS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content', 'utm_id'];

    public function __construct(
        public readonly ?string $fbp = null,
        public readonly ?string $fbc = null,
        public readonly ?string $gaClientId = null,
        public readonly ?string $gaSessionId = null,
        public readonly ?string $gclid = null,
        public readonly ?string $ipAddress = null,
        public readonly ?string $userAgent = null,
        public readonly array $utm = [],
        public readonly ?string $landingUrl = null,
        public readonly ?string $referrer = null,
        public readonly ?string $pageUrl = null,
        public readonly ?string $oppref = null,
        public readonly ?string $obref = null,
    ) {}

    public static function fromRequest(Request $request, ?string $measurementId = null): self
    {
        $cookies = self::parseCookieHeader((string) $request->headers->get('cookie', ''));
        $fbclid = self::clean($request->query('fbclid'));
        $fbc = self::clean($cookies['_fbc'] ?? null);

        if ($fbclid !== null && ($fbc === null || ! str_ends_with($fbc, '.'.$fbclid))) {
            $fbc = 'fb.1.'.((int) floor(microtime(true) * 1000)).'.'.$fbclid;
        }

        $utm = [];

        foreach (self::UTM_KEYS as $key) {
            $value = self::clean($request->query($key));

            if ($value !== null) {
                $utm[$key] = Str::limit($value, 150, '');
            }
        }

        $referrer = self::clean($request->headers->get('referer'));

        if ($referrer !== null && parse_url($referrer, PHP_URL_HOST) === $request->getHost()) {
            $referrer = null;
        }

        return new self(
            fbp: self::clean($cookies['_fbp'] ?? null),
            fbc: $fbc,
            gaClientId: self::parseGaClientId($cookies['_ga'] ?? null),
            gaSessionId: self::parseGaSessionId($cookies, $measurementId),
            gclid: self::clean($request->query('gclid')),
            ipAddress: $request->ip(),
            userAgent: self::clean($request->userAgent()),
            utm: $utm,
            landingUrl: null,
            referrer: $referrer,
            pageUrl: $request->isMethod('GET') && ! $request->headers->has('X-Livewire') ? $request->fullUrl() : null,
            oppref: self::clean($request->query('oppref')) ?? self::clean($cookies['__oppref'] ?? null),
            obref: self::clean($cookies['__obref'] ?? null),
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            fbp: self::clean($data['fbp'] ?? null),
            fbc: self::clean($data['fbc'] ?? null),
            gaClientId: self::clean($data['ga_client_id'] ?? null),
            gaSessionId: self::clean($data['ga_session_id'] ?? null),
            gclid: self::clean($data['gclid'] ?? null),
            ipAddress: self::clean($data['ip_address'] ?? null),
            userAgent: self::clean($data['user_agent'] ?? null),
            utm: is_array($data['utm'] ?? null) ? $data['utm'] : [],
            landingUrl: self::clean($data['landing_url'] ?? null),
            referrer: self::clean($data['referrer'] ?? null),
            pageUrl: self::clean($data['page_url'] ?? null),
            oppref: self::clean($data['oppref'] ?? null),
            obref: self::clean($data['obref'] ?? null),
        );
    }

    public function merge(self $newer): self
    {
        return new self(
            fbp: $newer->fbp ?? $this->fbp,
            fbc: $newer->fbc ?? $this->fbc,
            gaClientId: $newer->gaClientId ?? $this->gaClientId,
            gaSessionId: $newer->gaSessionId ?? $this->gaSessionId,
            gclid: $newer->gclid ?? $this->gclid,
            ipAddress: $newer->ipAddress ?? $this->ipAddress,
            userAgent: $newer->userAgent ?? $this->userAgent,
            utm: $newer->utm !== [] ? $newer->utm : $this->utm,
            landingUrl: $this->landingUrl ?? $newer->landingUrl ?? $newer->pageUrl,
            referrer: $newer->referrer ?? $this->referrer,
            pageUrl: $newer->pageUrl ?? $this->pageUrl,
            oppref: $newer->oppref ?? $this->oppref,
            obref: $newer->obref ?? $this->obref,
        );
    }

    public function with(array $values): self
    {
        return self::fromArray(array_merge($this->toArray(), $values));
    }

    public function isEmpty(): bool
    {
        return array_filter($this->toArray(), fn (mixed $value): bool => $value !== null && $value !== []) === [];
    }

    public function fingerprint(): string
    {
        return md5(json_encode([$this->fbp, $this->fbc, $this->gaClientId, $this->gclid, $this->ipAddress, $this->userAgent, $this->utm, $this->oppref, $this->obref]));
    }

    public function toArray(): array
    {
        return [
            'fbp' => $this->fbp,
            'fbc' => $this->fbc,
            'ga_client_id' => $this->gaClientId,
            'ga_session_id' => $this->gaSessionId,
            'gclid' => $this->gclid,
            'ip_address' => $this->ipAddress,
            'user_agent' => $this->userAgent,
            'utm' => $this->utm,
            'landing_url' => $this->landingUrl,
            'referrer' => $this->referrer,
            'page_url' => $this->pageUrl,
            'oppref' => $this->oppref,
            'obref' => $this->obref,
        ];
    }

    public static function generateFbp(): string
    {
        return 'fb.1.'.((int) floor(microtime(true) * 1000)).'.'.random_int(1000000000, 9999999999);
    }

    public static function generateGaClientId(): string
    {
        return random_int(1000000000, 2147483647).'.'.time();
    }

    public static function parseGaClientId(mixed $cookie): ?string
    {
        $cookie = self::clean($cookie);

        if ($cookie === null) {
            return null;
        }

        $parts = explode('.', $cookie);

        if (count($parts) < 4) {
            return null;
        }

        return $parts[count($parts) - 2].'.'.$parts[count($parts) - 1];
    }

    public static function parseGaSessionId(array $cookies, ?string $measurementId): ?string
    {
        $value = null;

        if ($measurementId !== null && $measurementId !== '') {
            $value = $cookies['_ga_'.str_replace('G-', '', $measurementId)] ?? null;
        }

        if ($value === null) {
            foreach ($cookies as $name => $cookie) {
                if (str_starts_with((string) $name, '_ga_')) {
                    $value = $cookie;

                    break;
                }
            }
        }

        $value = self::clean($value);

        if ($value === null) {
            return null;
        }

        if (preg_match('/\$?s(\d{9,})/', $value, $matches) === 1) {
            return $matches[1];
        }

        $parts = explode('.', $value);

        return isset($parts[2]) && ctype_digit($parts[2]) ? $parts[2] : null;
    }

    public static function parseCookieHeader(string $header): array
    {
        $cookies = [];

        foreach (explode(';', $header) as $pair) {
            $segments = explode('=', trim($pair), 2);

            if (count($segments) === 2 && $segments[0] !== '') {
                $cookies[$segments[0]] = urldecode($segments[1]);
            }
        }

        return $cookies;
    }

    private static function clean(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
