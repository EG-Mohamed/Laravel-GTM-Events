<?php

namespace MohamedSaid\LaravelGa4Events\Drivers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use MohamedSaid\LaravelGa4Events\Contracts\ServerTrackingDriver;
use MohamedSaid\LaravelGa4Events\LaravelGa4Events;
use MohamedSaid\LaravelGa4Events\Support\DriverResult;

class Ga4MeasurementProtocolDriver implements ServerTrackingDriver
{
    public function __construct(private readonly LaravelGa4Events $manager) {}

    public function destination(): string
    {
        return 'ga4';
    }

    public function enabled(): bool
    {
        return (bool) $this->manager->config('ga4.mp.enabled', true)
            && filled($this->manager->config('ga4.mp.measurement_id'))
            && filled($this->manager->config('ga4.mp.api_secret'));
    }

    public function shouldSend(array $event): bool
    {
        if ($this->manager->eventNames()->ga4((string) $event['name']) === null) {
            return false;
        }

        if (! ($event['browser_mirrored'] ?? false)) {
            return true;
        }

        if (! (bool) $this->manager->config('ga4.mp.skip_browser_mirrored_events', true)) {
            return true;
        }

        return filled($event['params']['transaction_id'] ?? null);
    }

    public function buildPayload(array $event): array
    {
        $context = $event['context'] ?? [];
        $params = [];

        foreach (($event['params'] ?? []) as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $params[$key] = is_string($value) ? mb_substr($value, 0, 100) : $value;
        }

        if (isset($params['currency']) && is_string($params['currency'])) {
            $params['currency'] = strtoupper($params['currency']);
        }

        $params['event_id'] = (string) $event['event_id'];
        $params['engagement_time_msec'] = (int) ($params['engagement_time_msec'] ?? 1);

        if (filled($context['ga_session_id'] ?? null)) {
            $params['session_id'] = (string) $context['ga_session_id'];
        }

        if (filled($context['page_url'] ?? $event['url'] ?? null)) {
            $params['page_location'] = mb_substr((string) ($event['url'] ?? $context['page_url']), 0, 1000);
        }

        if ($this->manager->config('ga4.mp.debug', false)) {
            $params['debug_mode'] = true;
        }

        $payload = [
            'client_id' => (string) ($context['ga_client_id'] ?? $this->fallbackClientId($event)),
            'timestamp_micros' => (int) $event['occurred_at'] * 1000000,
            'non_personalized_ads' => ! ($event['consent']['ad_personalization'] ?? true),
            'events' => [[
                'name' => (string) $this->manager->eventNames()->ga4((string) $event['name']),
                'params' => $params,
            ]],
        ];

        if (filled($event['user']['user_id'] ?? null)) {
            $payload['user_id'] = (string) $event['user']['user_id'];
        }

        if (($event['user']['ga4'] ?? []) !== []) {
            $payload['user_data'] = $event['user']['ga4'];
        }

        if (isset($event['consent'])) {
            $payload['consent'] = [
                'ad_user_data' => ($event['consent']['ad_user_data'] ?? true) ? 'GRANTED' : 'DENIED',
                'ad_personalization' => ($event['consent']['ad_personalization'] ?? true) ? 'GRANTED' : 'DENIED',
            ];
        }

        return $payload;
    }

    public function send(array $event): DriverResult
    {
        $debug = (bool) $this->manager->config('ga4.mp.debug', false);
        $url = rtrim((string) $this->manager->config('ga4.mp.endpoint', 'https://www.google-analytics.com'), '/')
            .($debug ? '/debug/mp/collect' : '/mp/collect');

        try {
            $response = Http::asJson()
                ->timeout((int) $this->manager->config('server.timeout', 10))
                ->withQueryParameters([
                    'measurement_id' => (string) $this->manager->config('ga4.mp.measurement_id'),
                    'api_secret' => (string) $this->manager->config('ga4.mp.api_secret'),
                ])
                ->post($url, $this->buildPayload($event));
        } catch (ConnectionException $exception) {
            return new DriverResult(false, null, ['error' => $exception->getMessage()], true);
        }

        $body = (array) ($response->json() ?? []);
        $hasValidationErrors = $debug && ($body['validationMessages'] ?? []) !== [];

        return new DriverResult(
            $response->successful() && ! $hasValidationErrors,
            $response->status(),
            $body,
            $response->serverError() || $response->status() === 429,
        );
    }

    private function fallbackClientId(array $event): string
    {
        $seed = (string) ($event['user']['user_id'] ?? $event['event_id']);
        $hash = hexdec(substr(hash('sha256', $seed), 0, 8));

        return $hash.'.'.((int) $event['occurred_at']);
    }
}
