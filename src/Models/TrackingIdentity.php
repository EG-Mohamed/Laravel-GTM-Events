<?php

namespace MohamedSaid\LaravelGa4Events\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use MohamedSaid\LaravelGa4Events\Support\TrackingContext;

class TrackingIdentity extends Model
{
    protected $table = 'ga4_tracking_identities';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'utm' => 'array',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }

    public function trackable(): MorphTo
    {
        return $this->morphTo();
    }

    public function toContext(): TrackingContext
    {
        return TrackingContext::fromArray($this->only([
            'fbp',
            'fbc',
            'ga_client_id',
            'ga_session_id',
            'gclid',
            'ip_address',
            'user_agent',
            'utm',
            'landing_url',
            'referrer',
        ]));
    }
}
