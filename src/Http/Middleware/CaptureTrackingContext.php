<?php

namespace MohamedSaid\LaravelGa4Events\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use MohamedSaid\LaravelGa4Events\Concerns\HasTrackingIdentity;
use MohamedSaid\LaravelGa4Events\LaravelGa4Events;
use MohamedSaid\LaravelGa4Events\Support\TrackingContext;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CaptureTrackingContext
{
    public function __construct(private readonly LaravelGa4Events $manager) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->manager->enabled() || ! (bool) $this->manager->config('context.capture', true) || ! $request->hasSession()) {
            return $next($request);
        }

        $session = $request->session();
        $stored = TrackingContext::fromArray((array) $session->get(LaravelGa4Events::SESSION_CONTEXT_KEY, []));
        $current = TrackingContext::fromRequest($request, $this->manager->config('ga4.mp.measurement_id'));
        $context = $stored->merge($current);
        $consent = $this->manager->hasConsent();
        $cookies = TrackingContext::parseCookieHeader((string) $request->headers->get('cookie', ''));

        if ($consent && $context->fbp === null && $this->manager->metaPixelId() !== null) {
            $context = $context->with(['fbp' => TrackingContext::generateFbp()]);
        }

        if ($context->gaClientId === null && $this->manager->serverEnabled()) {
            $context = $context->with(['ga_client_id' => TrackingContext::generateGaClientId()]);
        }

        $session->put(LaravelGa4Events::SESSION_CONTEXT_KEY, $context->toArray());

        $response = $next($request);

        if ($consent && (bool) $this->manager->config('context.set_first_party_cookies', true)) {
            $this->queueFirstPartyCookies($request, $response, $context, $cookies);
        }

        $this->rememberIdentity($request, $context);

        return $response;
    }

    private function queueFirstPartyCookies(Request $request, Response $response, TrackingContext $context, array $cookies): void
    {
        $minutes = max(1, (int) $this->manager->config('context.cookie_lifetime_days', 90)) * 1440;

        foreach (['_fbp' => $context->fbp, '_fbc' => $context->fbc] as $name => $value) {
            if ($value === null || ($cookies[$name] ?? null) === $value) {
                continue;
            }

            $response->headers->setCookie(Cookie::create(
                name: $name,
                value: $value,
                expire: now()->addMinutes($minutes),
                path: '/',
                domain: config('session.domain'),
                secure: $request->isSecure(),
                httpOnly: false,
                raw: true,
                sameSite: Cookie::SAMESITE_LAX,
            ));
        }
    }

    private function rememberIdentity(Request $request, TrackingContext $context): void
    {
        try {
            $user = $this->manager->authenticatedUser();
        } catch (Throwable) {
            return;
        }

        if ($user === null || ! in_array(HasTrackingIdentity::class, class_uses_recursive($user), true)) {
            return;
        }

        $fingerprint = $user::class.':'.$user->getKey().':'.$context->fingerprint();

        if ($request->session()->get(LaravelGa4Events::SESSION_IDENTITY_KEY) === $fingerprint) {
            return;
        }

        try {
            $user->rememberTrackingContext($context);
            $request->session()->put(LaravelGa4Events::SESSION_IDENTITY_KEY, $fingerprint);
        } catch (Throwable) {
            return;
        }
    }
}
