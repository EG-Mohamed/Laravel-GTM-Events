<?php

namespace MohamedSaid\LaravelGa4Events\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Schema;
use MohamedSaid\LaravelGa4Events\Contracts\ServerTrackingDriver;
use MohamedSaid\LaravelGa4Events\Models\ServerEvent;
use RuntimeException;
use Throwable;

class SendServerTrackingEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries;

    public function __construct(public string $driver, public array $event)
    {
        $this->tries = max(1, (int) config('ga4-events.server.tries', 5));
    }

    public function backoff(): array
    {
        return array_values(array_map('intval', (array) config('ga4-events.server.backoff', [10, 60, 300])));
    }

    public function handle(): void
    {
        $driver = app($this->driver);

        if (! $driver instanceof ServerTrackingDriver || ! $driver->enabled() || ! $driver->shouldSend($this->event)) {
            return;
        }

        $record = $this->record($driver);

        if ($record?->status === ServerEvent::STATUS_SENT) {
            return;
        }

        $result = $driver->send($this->event);

        $record?->fill([
            'status' => $result->successful ? ServerEvent::STATUS_SENT : ServerEvent::STATUS_FAILED,
            'response_code' => $result->status,
            'response_body' => $result->body,
            'attempts' => $this->attempts(),
            'sent_at' => $result->successful ? now() : null,
        ])->save();

        if (! $result->successful && $result->retryable) {
            throw new RuntimeException((string) __('Server tracking event delivery to :destination failed with status :status.', [
                'destination' => $driver->destination(),
                'status' => $result->status ?? 'none',
            ]));
        }
    }

    public function failed(?Throwable $exception): void
    {
        if (! $this->storesEvents()) {
            return;
        }

        ServerEvent::query()
            ->where('event_id', (string) $this->event['event_id'])
            ->where('status', '!=', ServerEvent::STATUS_SENT)
            ->update(['status' => ServerEvent::STATUS_FAILED]);
    }

    private function record(ServerTrackingDriver $driver): ?ServerEvent
    {
        if (! $this->storesEvents()) {
            return null;
        }

        return ServerEvent::query()->firstOrCreate(
            [
                'event_id' => (string) $this->event['event_id'],
                'destination' => $driver->destination(),
            ],
            [
                'name' => (string) $this->event['name'],
                'status' => ServerEvent::STATUS_PENDING,
                'payload' => $driver->buildPayload($this->event),
            ],
        );
    }

    private function storesEvents(): bool
    {
        static $tableExists = null;

        if (! (bool) config('ga4-events.server.store_events', true)) {
            return false;
        }

        return $tableExists ??= Schema::hasTable('ga4_server_events');
    }
}
