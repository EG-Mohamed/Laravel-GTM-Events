<?php

namespace MohamedSaid\LaravelGa4Events\Models;

use Illuminate\Database\Eloquent\Model;

class ServerEvent extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    protected $table = 'ga4_server_events';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'response_body' => 'array',
            'sent_at' => 'datetime',
        ];
    }
}
