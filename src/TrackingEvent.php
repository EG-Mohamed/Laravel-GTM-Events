<?php

namespace MohamedSaid\LaravelGa4Events;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use MohamedSaid\LaravelGa4Events\Support\TrackingContext;

class TrackingEvent
{
    private string $eventId;

    private array $params = [];

    private ?Model $subject = null;

    private bool $subjectResolved = false;

    private array $userData = [];

    private ?TrackingContext $context = null;

    private ?string $url = null;

    private ?CarbonInterface $occurredAt = null;

    private bool $toBrowser = false;

    private bool $toServer = false;

    private bool $browserMirrored = false;

    public function __construct(private readonly LaravelGa4Events $manager, private readonly string $name)
    {
        $this->eventId = (string) Str::uuid();
    }

    public function eventId(?string $eventId): self
    {
        if ($eventId !== null && trim($eventId) !== '') {
            $this->eventId = Str::limit(trim($eventId), 64, '');
        }

        return $this;
    }

    public function params(array $params): self
    {
        $this->params = array_merge($this->params, $params);

        return $this;
    }

    public function param(string $key, mixed $value): self
    {
        $this->params[$key] = $value;

        return $this;
    }

    public function value(float|int|string|null $value, ?string $currency = null): self
    {
        if ($value !== null) {
            $this->params['value'] = round((float) $value, 2);
        }

        if ($currency !== null) {
            $this->params['currency'] = Str::upper($currency);
        }

        return $this;
    }

    public function transactionId(?string $transactionId): self
    {
        if ($transactionId !== null && $transactionId !== '') {
            $this->params['transaction_id'] = $transactionId;
        }

        return $this;
    }

    public function for(?Model $subject): self
    {
        $this->subject = $subject;
        $this->subjectResolved = true;

        return $this;
    }

    public function user(array $userData): self
    {
        $this->userData = array_merge($this->userData, $userData);

        return $this;
    }

    public function context(TrackingContext $context): self
    {
        $this->context = $context;

        return $this;
    }

    public function url(?string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function at(CarbonInterface $occurredAt): self
    {
        $this->occurredAt = $occurredAt;

        return $this;
    }

    public function browser(bool $enabled = true): self
    {
        $this->toBrowser = $enabled;

        return $this;
    }

    public function server(bool $enabled = true): self
    {
        $this->toServer = $enabled;

        return $this;
    }

    public function mirrored(bool $mirrored = true): self
    {
        $this->browserMirrored = $mirrored;

        return $this;
    }

    public function getEventId(): string
    {
        return $this->eventId;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getParams(): array
    {
        return $this->cleanParams();
    }

    public function toBrowserPayload(): array
    {
        return [
            'name' => $this->name,
            'params' => $this->cleanParams(),
            'event_id' => $this->eventId,
        ];
    }

    public function toServerPayload(): array
    {
        $subject = $this->subjectResolved ? $this->subject : $this->manager->authenticatedUser();

        return $this->manager->buildServerPayload(
            name: $this->name,
            eventId: $this->eventId,
            params: $this->cleanParams(),
            subject: $subject,
            userData: $this->userData,
            context: $this->context,
            url: $this->url,
            occurredAt: $this->occurredAt,
            browserMirrored: $this->browserMirrored || $this->toBrowser,
        );
    }

    public function send(): self
    {
        if (! $this->manager->enabled()) {
            return $this;
        }

        $validated = $this->manager->track($this->name, $this->cleanParams());

        if (! $validated['valid'] && (bool) $this->manager->config('drop_invalid_events', true)) {
            return $this;
        }

        $this->params = $validated['params'];

        if ($this->toBrowser) {
            $this->manager->queueBrowserEvent($this->toBrowserPayload());
        }

        if ($this->toServer) {
            $this->manager->dispatchServerEvent($this->toServerPayload());
        }

        return $this;
    }

    private function cleanParams(): array
    {
        return array_filter($this->params, fn (mixed $value): bool => $value !== null && $value !== '' && $value !== []);
    }
}
