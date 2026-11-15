<?php

namespace MohamedSaid\LaravelGa4Events\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphOne;
use MohamedSaid\LaravelGa4Events\Models\TrackingIdentity;
use MohamedSaid\LaravelGa4Events\Support\TrackingContext;

trait HasTrackingIdentity
{
    public function trackingIdentity(): MorphOne
    {
        return $this->morphOne(TrackingIdentity::class, 'trackable');
    }

    public function trackingContext(): TrackingContext
    {
        return $this->trackingIdentity?->toContext() ?? new TrackingContext;
    }

    public function rememberTrackingContext(TrackingContext $context): void
    {
        if ($context->isEmpty()) {
            return;
        }

        $identity = $this->trackingIdentity()->firstOrNew();
        $merged = ($identity->exists ? $identity->toContext() : new TrackingContext)->merge($context);

        $identity->fill([
            'fbp' => $merged->fbp,
            'fbc' => $merged->fbc,
            'ga_client_id' => $merged->gaClientId,
            'ga_session_id' => $merged->gaSessionId,
            'gclid' => $merged->gclid,
            'ip_address' => $merged->ipAddress,
            'user_agent' => $merged->userAgent,
            'utm' => $merged->utm !== [] ? $merged->utm : null,
            'landing_url' => $identity->landing_url ?? $merged->landingUrl,
            'referrer' => $identity->referrer ?? $merged->referrer,
            'first_seen_at' => $identity->first_seen_at ?? now(),
            'last_seen_at' => now(),
        ])->save();

        $this->setRelation('trackingIdentity', $identity);
    }
}
