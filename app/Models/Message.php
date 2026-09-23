<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Message extends Model
{
    use SoftDeletes;

    // sender_type values
    public const SENDER_CAST = 1;
    public const SENDER_SHOP = 2;
    // System (auto-sent) messages are audience-scoped: each row is visible only
    // to the role it targets, so the single is_read flag never conflicts.
    public const SENDER_SYSTEM_TO_CAST = 3;
    public const SENDER_SYSTEM_TO_SHOP = 4;

    // type values (1-7 are managed in TalkController)
    public const TYPE_BILLING_SYSTEM = 8;

    protected $table = 'messages';

    protected $fillable = [
        'cast_id',
        'shop_id',
        'sender_type',
        'type',
        'content',
        'is_read',
    ];

    protected function casts(): array
    {
        return [
            'is_read' => 'boolean',
        ];
    }

    public function cast(): BelongsTo
    {
        return $this->belongsTo(Cast::class, 'cast_id', 'id');
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id', 'id');
    }
}
