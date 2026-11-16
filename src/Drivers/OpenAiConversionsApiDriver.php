<?php

namespace MohamedSaid\LaravelGa4Events\Drivers;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use MohamedSaid\LaravelGa4Events\Contracts\ServerTrackingDriver;
use MohamedSaid\LaravelGa4Events\LaravelGa4Events;
use MohamedSaid\LaravelGa4Events\Support\DriverResult;
use MohamedSaid\LaravelGa4Events\Support\EventNameMapper;
use MohamedSaid\LaravelGa4Events\Support\OpenAiEventData;

class OpenAiConversionsApiDriver implements ServerTrackingDriver
{
    public function __construct(private readonly LaravelGa4Events $manager) {}

    public function destination(): string
    {
        return 'openai';
    }

    public function enabled(): bool
    {
        return (bool) $this->manager->config('openai.capi.enabled', true)
            && $this->manager->openAiPixelId() !== null
            && filled($this->manager->config('openai.capi.api_key'));
    }

    public function shouldSend(array $event): bool
    {
        return $this->manager->eventNames()->openai((string) $event['name']) !== null
            && (int) ($event['occurred_at'] ?? 0) >= now()->subDays(7)->getTimestamp();
    }

    public function buildPayload(array $event): array
    {
        $context = $event['context'] ?? [];
        $mappedName = (string) $this->manager->eventNames()->openai((string) $event['name']);
        $isStandard = EventNameMapper::isOpenAiStandardEvent($mappedName);
        $type = $isStandard ? $mappedName : 'custom';

        $user = array_filter([
            ...($event['user']['openai'] ?? []),
            'obref' => $context['obref'] ?? null,
            'ip_address' => $context['ip_address'] ?? null,
            'user_agent' => $context['user_agent'] ?? null,
        ], fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);

        $payloadEvent = array_filter([
            'id' => (string) $event['event_id'],
            'type' => $type,
            'custom_event_name' => $isStandard ? null : EventNameMapper::openAiCustomName($mappedName),
            'timestamp_ms' => (int) $event['occurred_at'] * 1000,
            'oppref' => $context['oppref'] ?? null,
            'source_url' => $this->sourceUrl($event),
            'action_source' => (string) $this->manager->config('openai.capi.action_source', 'web'),
            'user' => $user !== [] ? $user : null,
            'data' => OpenAiEventData::fromParams($type, $event['params'] ?? []),
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        return array_filter([
            'validate_only' => (bool) $this->manager->config('openai.capi.validate_only', false) ?: null,
            'integration_source' => $this->manager->config('openai.capi.integration_source') ?: null,
            'events' => [$payloadEvent],
        ], fn (mixed $value): bool => $value !== null);
    }

    public function send(array $event): DriverResult
    {
        try {
            $response = Http::asJson()
                ->acceptJson()
                ->withToken((string) $this->manager->config('openai.capi.api_key'))
                ->timeout((int) $this->manager->config('server.timeout', 10))
                ->withQueryParameters(['pid' => (string) $this->manager->openAiPixelId()])
                ->post((string) $this->manager->config('openai.capi.endpoint', 'https://bzr.openai.com/v1/events'), $this->buildPayload($event));
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

    private function sourceUrl(array $event): string
    {
        $context = $event['context'] ?? [];

        foreach ([$event['url'] ?? null, $context['page_url'] ?? null, $context['landing_url'] ?? null] as $url) {
            if (is_string($url) && filter_var($url, FILTER_VALIDATE_URL) !== false) {
                return $url;
            }
        }

        return (string) config('app.url');
    }
}
