<?php

namespace MohamedSaid\LaravelGa4Events\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use MohamedSaid\LaravelGa4Events\LaravelGa4Events;

class CollectEventController
{
    public function __invoke(Request $request, LaravelGa4Events $manager): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'event_id' => ['required', 'string', 'max:64'],
            'params' => ['nullable', 'array'],
            'url' => ['nullable', 'string', 'max:2000'],
        ]);

        $allowed = (array) $manager->config('collect_endpoint.allowed_events', []);

        if ($allowed === [] || ! in_array($validated['name'], $allowed, true)) {
            return response()->json(['accepted' => false], 202);
        }

        $url = $validated['url'] ?? null;

        if ($url !== null && parse_url($url, PHP_URL_HOST) !== $request->getHost()) {
            $url = null;
        }

        $context = $manager->contextFor($manager->authenticatedUser())->with(array_filter([
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'page_url' => $url,
        ]));

        $manager->event($validated['name'])
            ->eventId($validated['event_id'])
            ->params((array) ($validated['params'] ?? []))
            ->url($url)
            ->context($context)
            ->mirrored()
            ->server()
            ->send();

        return response()->json(['accepted' => true], 202);
    }
}
