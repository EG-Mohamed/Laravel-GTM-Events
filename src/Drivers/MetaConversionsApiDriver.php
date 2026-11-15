<?php

namespace MohamedSaid\LaravelGa4Events\Drivers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use MohamedSaid\LaravelGa4Events\Contracts\ServerTrackingDriver;
use MohamedSaid\LaravelGa4Events\LaravelGa4Events;
use MohamedSaid\LaravelGa4Events\Support\DriverResult;
use MohamedSaid\LaravelGa4Events\Support\MetaCustomData;

class MetaConversionsApiDriver implements ServerTrackingDriver
{
    public function __construct(private readonly LaravelGa4Events $manager) {}

    public function destination(): string
    {
        return 'meta';
    }

    public function enabled(): bool
    {
        return (bool) $this->manager->config('meta.capi.enabled', true)
            && $this->manager->metaPixelId() !== null
            && filled($this->manager->config('meta.capi.access_token'));
    }

    public function shouldSend(array $event): bool
    {
        return $this->manager->eventNames()->meta((string) $event['name']) !== null;
    }

    public function buildPayload(array $event): array
    {
        $context = $event['context'] ?? [];
        $metaName = (string) $this->manager->eventNames()->meta((string) $event['name']);

        $userData = array_filter([
            ...($event['user']['meta'] ?? []),
            'client_ip_address' => $context['ip_address'] ?? null,
            'client_user_agent' => $context['user_agent'] ?? null,
            'fbp' => $context['fbp'] ?? null,
            'fbc' => $context['fbc'] ?? null,
        ], fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);

        $data = array_filter([
            'event_name' => $metaName,
            'event_time' => (int) $event['occurred_at'],
            'event_id' => (string) $event['event_id'],
            'action_source' => isset($userData['client_user_agent'])
                ? (string) $this->manager->config('meta.capi.action_source', 'website')
                : 'system_generated',
            'event_source_url' => $event['url'] ?? $context['page_url'] ?? $context['landing_url'] ?? null,
            'user_data' => $userData,
            'custom_data' => MetaCustomData::fromParams($event['params'] ?? []),
        ], fn (mixed $value): bool => $value !== null && $value !== []);

        $data['user_data'] = (object) ($data['user_data'] ?? []);

        return array_filter([
            'data' => [$data],
            'test_event_code' => $this->manager->config('meta.capi.test_event_code') ?: null,
        ]);
    }

    public function send(array $event): DriverResult
    {
        $url = rtrim((string) $this->manager->config('meta.capi.graph_url', 'https://graph.facebook.com'), '/')
            .'/'.$this->manager->config('meta.capi.graph_version', 'v23.0')
            .'/'.$this->manager->metaPixelId().'/events';

        try {
            $response = Http::asJson()
                ->acceptJson()
                ->timeout((int) $this->manager->config('server.timeout', 10))
                ->withQueryParameters(['access_token' => (string) $this->manager->config('meta.capi.access_token')])
                ->post($url, $this->buildPayload($event));
        } catch (ConnectionException $exception) {
            return new DriverResult(false, null, ['error' => $exception->getMessage()], true);
        }

        return new DriverResult(
            $response->successful(),
            $response->status(),
            (array) ($response->json() ?? []),
            $response->serverError() || $response->status() === 429,
        );
    }
}
