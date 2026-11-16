<?php

namespace MohamedSaid\LaravelGa4Events\Support;

final class EventNameMapper
{
    public const OPENAI_STANDARD_EVENTS = [
        'appointment_scheduled',
        'checkout_started',
        'contents_viewed',
        'items_added',
        'lead_created',
        'order_created',
        'page_viewed',
        'registration_completed',
        'subscription_created',
        'trial_started',
    ];

    private array $map;

    private bool $openAiCustomEvents;

    public function __construct(array $config)
    {
        $map = [];

        foreach (($config['event_map'] ?? []) as $name => $destinations) {
            if (is_string($name) && is_array($destinations)) {
                $map[trim($name)] = $destinations;
            }
        }

        foreach (($config['meta_pixel_event_map'] ?? []) as $name => $metaName) {
            if (is_string($name) && is_string($metaName) && trim($metaName) !== '') {
                $map[trim($name)] = array_merge($map[trim($name)] ?? [], ['meta' => trim($metaName)]);
            }
        }

        $this->map = $map;
        $this->openAiCustomEvents = (bool) ($config['openai_send_custom_events'] ?? false);
    }

    public function meta(string $name): ?string
    {
        $mapped = $this->map[$name]['meta'] ?? $name;

        if ($mapped === false || $mapped === null) {
            return null;
        }

        return trim((string) $mapped) !== '' ? trim((string) $mapped) : $name;
    }

    public function ga4(string $name): ?string
    {
        $mapped = $this->map[$name]['ga4'] ?? $name;

        if ($mapped === false || $mapped === null) {
            return null;
        }

        return trim((string) $mapped) !== '' ? trim((string) $mapped) : $name;
    }

    public function openai(string $name): ?string
    {
        $mapped = $this->map[$name]['openai'] ?? null;

        if ($mapped === false) {
            return null;
        }

        if (is_string($mapped) && trim($mapped) !== '') {
            return trim($mapped);
        }

        if (in_array($name, self::OPENAI_STANDARD_EVENTS, true)) {
            return $name;
        }

        return $this->openAiCustomEvents ? self::openAiCustomName($name) : null;
    }

    public static function isOpenAiStandardEvent(string $name): bool
    {
        return in_array($name, self::OPENAI_STANDARD_EVENTS, true);
    }

    public static function openAiCustomName(string $name): ?string
    {
        $name = strtolower((string) preg_replace('/[^A-Za-z0-9_-]+/', '_', trim($name)));
        $name = trim(substr($name, 0, 64), '_-');

        if ($name === '' || self::isOpenAiStandardEvent($name)) {
            return null;
        }

        return $name;
    }

    public function toFrontend(): array
    {
        return collect($this->map)
            ->map(fn (array $destinations, string $name): array => [
                'meta' => $this->meta($name) ?? false,
                'ga4' => $this->ga4($name) ?? false,
                'openai' => $this->openai($name) ?? false,
            ])
            ->all();
    }
}
