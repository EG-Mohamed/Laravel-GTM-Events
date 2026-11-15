<?php

namespace MohamedSaid\LaravelGa4Events\Livewire;

use Illuminate\Database\Eloquent\Model;
use MohamedSaid\LaravelGa4Events\LaravelGa4Events;

trait TracksEvents
{
    protected function trackEvent(string $name, array $params = [], bool $server = false, ?string $eventId = null, ?Model $subject = null): string
    {
        $manager = app(LaravelGa4Events::class);
        $event = $manager->event($name)->params($params)->eventId($eventId);

        $this->dispatch(
            (string) $manager->config('livewire_event_name', 'gtm-event'),
            name: $name,
            params: $event->getParams(),
            event_id: $event->getEventId(),
        );

        if ($server) {
            $event->for($subject ?? $manager->authenticatedUser())->mirrored()->server()->send();
        }

        return $event->getEventId();
    }
}
