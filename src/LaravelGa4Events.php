<?php

namespace MohamedSaid\LaravelGa4Events;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use MohamedSaid\LaravelGa4Events\Concerns\HasTrackingIdentity;
use MohamedSaid\LaravelGa4Events\Contracts\ProvidesTrackingUserData;
use MohamedSaid\LaravelGa4Events\Exceptions\InvalidGa4ConfigurationException;
use MohamedSaid\LaravelGa4Events\Jobs\SendServerTrackingEvent;
use MohamedSaid\LaravelGa4Events\Support\EventNameMapper;
use MohamedSaid\LaravelGa4Events\Support\Ga4EventValidator;
use MohamedSaid\LaravelGa4Events\Support\TrackingContext;
use MohamedSaid\LaravelGa4Events\Support\UserDataNormalizer;
use Throwable;

class LaravelGa4Events
{
    public const SESSION_CONTEXT_KEY = 'ga4-events.context';

    public const SESSION_PENDING_KEY = 'ga4-events.pending';

    public const SESSION_IDENTITY_KEY = 'ga4-events.identity';

    private ?EventNameMapper $eventNames = null;

    public function __construct(private readonly array $config) {}

    public function config(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->config, $key, $default);
    }

    public function event(string $name): TrackingEvent
    {
        return new TrackingEvent($this, $name);
    }

    public function eventNames(): EventNameMapper
    {
        return $this->eventNames ??= new EventNameMapper($this->config);
    }

    public function serverEnabled(): bool
    {
        return $this->enabled() && (bool) $this->config('server.enabled', true);
    }

    public function hasConsent(): bool
    {
        $resolver = $this->config('consent_resolver');

        if ($resolver === null) {
            return true;
        }

        $resolver = is_string($resolver) ? app($resolver) : $resolver;

        return is_callable($resolver) ? (bool) $resolver(request()) : true;
    }

    public function currentContext(): TrackingContext
    {
        $request = request();

        if (! $request->hasSession()) {
            return new TrackingContext;
        }

        return TrackingContext::fromArray((array) $request->session()->get(self::SESSION_CONTEXT_KEY, []));
    }

    public function authenticatedUser(): ?Model
    {
        foreach ($this->identityGuards() as $guard) {
            try {
                $user = auth()->guard($guard)->user();
            } catch (Throwable) {
                continue;
            }

            if ($user instanceof Model) {
                return $user;
            }
        }

        return null;
    }

    public function identityGuards(): array
    {
        $guards = $this->config('context.identity_guards');

        if (is_array($guards)) {
            return $guards;
        }

        return collect((array) config('auth.guards', []))
            ->filter(fn (mixed $guard): bool => is_array($guard) && ($guard['driver'] ?? null) === 'session')
            ->keys()
            ->all();
    }

    public function isCurrentRequestUser(?Model $subject): bool
    {
        if ($subject === null) {
            return true;
        }

        $user = $this->authenticatedUser();

        return $user !== null && $user->is($subject);
    }

    public function userDataFor(?Model $subject): array
    {
        if ($subject === null) {
            return [];
        }

        if ($subject instanceof ProvidesTrackingUserData) {
            return $subject->trackingUserData();
        }

        $attributes = $subject->getAttributes();

        return array_filter([
            'external_id' => $subject->getKey(),
            'email' => $attributes['email'] ?? null,
            'phone' => $attributes['phone'] ?? $attributes['phone_number'] ?? $attributes['mobile'] ?? null,
            'first_name' => $attributes['first_name'] ?? null,
            'last_name' => $attributes['last_name'] ?? null,
            'name' => $attributes['name'] ?? null,
            'city' => $attributes['city'] ?? null,
            'country' => $attributes['country'] ?? $attributes['country_code'] ?? null,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    public function contextFor(?Model $subject): TrackingContext
    {
        $stored = $subject !== null && in_array(HasTrackingIdentity::class, class_uses_recursive($subject), true)
            ? $subject->trackingContext()
            : new TrackingContext;

        if ($this->isCurrentRequestUser($subject)) {
            return $stored->merge($this->currentContext());
        }

        return $stored;
    }

    public function buildServerPayload(
        string $name,
        string $eventId,
        array $params,
        ?Model $subject,
        array $userData = [],
        ?TrackingContext $context = null,
        ?string $url = null,
        ?CarbonInterface $occurredAt = null,
        bool $browserMirrored = false,
    ): array {
        $context ??= $this->contextFor($subject);
        $normalized = UserDataNormalizer::normalize(array_merge($this->userDataFor($subject), $userData));
        $consent = $this->hasConsent();

        return [
            'event_id' => $eventId,
            'name' => $name,
            'params' => $params,
            'occurred_at' => ($occurredAt ?? now())->getTimestamp(),
            'url' => $url,
            'browser_mirrored' => $browserMirrored,
            'context' => $context->toArray(),
            'user' => [
                'user_id' => $normalized['external_id'] ?? null,
                'meta' => UserDataNormalizer::forMeta($normalized),
                'ga4' => UserDataNormalizer::forGa4($normalized),
            ],
            'consent' => [
                'ad_user_data' => $consent,
                'ad_personalization' => $consent,
            ],
        ];
    }

    public function dispatchServerEvent(array $payload): void
    {
        if (! $this->serverEnabled() || ! $this->hasConsent()) {
            return;
        }

        foreach ((array) $this->config('server.drivers', []) as $driver) {
            if (! is_string($driver) || ! class_exists($driver)) {
                continue;
            }

            $pending = SendServerTrackingEvent::dispatch($driver, $payload)->afterCommit();

            if (filled($this->config('server.connection'))) {
                $pending->onConnection((string) $this->config('server.connection'));
            }

            if (filled($this->config('server.queue'))) {
                $pending->onQueue((string) $this->config('server.queue'));
            }
        }
    }

    public function queueBrowserEvent(array $payload): void
    {
        $request = request();

        if (! $request->hasSession()) {
            return;
        }

        $pending = (array) $request->session()->get(self::SESSION_PENDING_KEY, []);
        $pending[] = $payload;

        $request->session()->put(self::SESSION_PENDING_KEY, array_slice($pending, -20));
    }

    public function pullBrowserEvents(): array
    {
        $request = request();

        if (! $request->hasSession()) {
            return [];
        }

        return array_values((array) $request->session()->pull(self::SESSION_PENDING_KEY, []));
    }

    public function metaPixelAdvancedMatching(): array
    {
        if (! (bool) $this->config('meta_pixel_advanced_matching', true)) {
            return [];
        }

        $user = $this->authenticatedUser();

        if ($user === null) {
            return [];
        }

        return UserDataNormalizer::forMetaPixel(UserDataNormalizer::normalize($this->userDataFor($user)));
    }

    public function frontendPayload(): array
    {
        $collectEnabled = $this->serverEnabled() && (bool) $this->config('collect_endpoint.enabled', true);

        return array_merge($this->toFrontendConfig(), [
            'eventMap' => $this->eventNames()->toFrontend(),
            'metaAdvancedMatching' => (object) $this->metaPixelAdvancedMatching(),
            'collectUrl' => $collectEnabled ? url((string) $this->config('collect_endpoint.path', 'ga4-events/collect')) : null,
            'csrfToken' => $collectEnabled && request()->hasSession() ? csrf_token() : null,
            'pendingEvents' => $this->pullBrowserEvents(),
            'consent' => $this->hasConsent(),
        ]);
    }

    public function enabled(): bool
    {
        return (bool) ($this->config['enabled'] ?? false);
    }

    public function containerId(): ?string
    {
        $containerId = trim((string) ($this->config['container_id'] ?? ''));

        return $containerId !== '' ? $containerId : null;
    }

    public function requireContainerId(): string
    {
        $containerId = $this->containerId();

        if ($containerId === null) {
            throw InvalidGa4ConfigurationException::missingContainerId();
        }

        return $containerId;
    }

    public function metaPixelId(): ?string
    {
        $metaPixelId = trim((string) ($this->config['meta_pixel_id'] ?? ''));

        return $metaPixelId !== '' ? $metaPixelId : null;
    }

    public function track(string $name, array $params = []): array
    {
        return $this->validator()->validate([
            'name' => $name,
            'params' => $params,
        ], $this->strictValidation());
    }

    public function validator(): Ga4EventValidator
    {
        return new Ga4EventValidator($this->config);
    }

    public function toFrontendConfig(): array
    {
        return [
            'enabled' => $this->enabled(),
            'containerId' => $this->containerId(),
            'injectGtmScript' => (bool) ($this->config['inject_gtm_script'] ?? true),
            'metaPixelId' => $this->metaPixelId(),
            'injectMetaPixelScript' => (bool) ($this->config['inject_meta_pixel_script'] ?? true),
            'metaPixelEventMap' => $this->metaPixelEventMap(),
            'metaPixelStandardEvents' => $this->metaPixelStandardEvents(),
            'eventBusName' => (string) ($this->config['event_bus_name'] ?? 'gtm:event'),
            'livewireEventName' => (string) ($this->config['livewire_event_name'] ?? 'gtm-event'),
            'globalJsObject' => (string) ($this->config['global_js_object'] ?? 'GTMEvents'),
            'debug' => (bool) ($this->config['debug'] ?? false),
            'strictValidation' => $this->strictValidation(),
            'dropInvalidEvents' => (bool) ($this->config['drop_invalid_events'] ?? true),
            'maxEventNameLength' => (int) ($this->config['max_event_name_length'] ?? 40),
            'maxParams' => (int) ($this->config['max_params'] ?? 25),
            'maxParamKeyLength' => (int) ($this->config['max_param_key_length'] ?? 40),
            'maxParamValueLength' => (int) ($this->config['max_param_value_length'] ?? 100),
            'maxParamNesting' => (int) ($this->config['max_param_nesting'] ?? 4),
            'allowedNamePattern' => (string) ($this->config['allowed_name_pattern'] ?? '/^[a-zA-Z][a-zA-Z0-9_]*$/'),
            'consolePrefix' => (string) ($this->config['console_prefix'] ?? '[GTM Events]'),
        ];
    }

    private function strictValidation(): bool
    {
        return (bool) ($this->config['strict_validation'] ?? false);
    }

    private function metaPixelEventMap(): array
    {
        $eventMap = $this->config['meta_pixel_event_map'] ?? [];

        if (! is_array($eventMap)) {
            return [];
        }

        return collect($eventMap)
            ->filter(fn (mixed $value, mixed $key): bool => is_string($key) && is_string($value))
            ->mapWithKeys(fn (string $value, string $key): array => [trim($key) => trim($value)])
            ->filter(fn (string $value, string $key): bool => $key !== '' && $value !== '')
            ->all();
    }

    private function metaPixelStandardEvents(): array
    {
        $standardEvents = $this->config['meta_pixel_standard_events'] ?? [];

        if (! is_array($standardEvents)) {
            return [];
        }

        return array_values(array_filter(array_map(function (mixed $event): ?string {
            if (! is_string($event)) {
                return null;
            }

            $event = trim($event);

            return $event !== '' ? $event : null;
        }, $standardEvents)));
    }
}
