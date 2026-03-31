<?php

return [
    'enabled' => (bool) env('GTM_EVENTS_ENABLED', true),
    'container_id' => env('GTM_CONTAINER_ID'),
    'inject_gtm_script' => (bool) env('GTM_EVENTS_INJECT_SCRIPT', true),
    'event_bus_name' => env('GTM_EVENTS_EVENT_BUS_NAME', 'gtm:event'),
    'livewire_event_name' => env('GTM_EVENTS_LIVEWIRE_EVENT_NAME', 'gtm-event'),
    'global_js_object' => env('GTM_EVENTS_GLOBAL_JS_OBJECT', 'GTMEvents'),
    'debug' => (bool) env('GTM_EVENTS_DEBUG', false),
    'strict_validation' => (bool) env('GTM_EVENTS_STRICT_VALIDATION', false),
    'drop_invalid_events' => (bool) env('GTM_EVENTS_DROP_INVALID_EVENTS', true),
    'max_event_name_length' => (int) env('GTM_EVENTS_MAX_EVENT_NAME_LENGTH', 40),
    'max_params' => (int) env('GTM_EVENTS_MAX_PARAMS', 25),
    'max_param_key_length' => (int) env('GTM_EVENTS_MAX_PARAM_KEY_LENGTH', 40),
    'max_param_value_length' => (int) env('GTM_EVENTS_MAX_PARAM_VALUE_LENGTH', 100),
    'max_param_nesting' => (int) env('GTM_EVENTS_MAX_PARAM_NESTING', 4),
    'allowed_name_pattern' => env('GTM_EVENTS_ALLOWED_NAME_PATTERN', '/^[a-zA-Z][a-zA-Z0-9_]*$/'),
    'console_prefix' => env('GTM_EVENTS_CONSOLE_PREFIX', '[GTM Events]'),
];
