<?php

namespace MohamedSaid\LaravelGa4Events;

use MohamedSaid\LaravelGa4Events\Exceptions\InvalidGa4ConfigurationException;
use MohamedSaid\LaravelGa4Events\Support\Ga4EventValidator;

class LaravelGa4Events
{
    public function __construct(private readonly array $config) {}

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
}
