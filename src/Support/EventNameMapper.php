<?php

namespace MohamedSaid\LaravelGa4Events\Support;

final class EventNameMapper
{
    private array $map;

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

    public function toFrontend(): array
    {
        return collect($this->map)
            ->map(fn (array $destinations, string $name): array => [
                'meta' => $this->meta($name) ?? false,
                'ga4' => $this->ga4($name) ?? false,
            ])
            ->all();
    }
}
