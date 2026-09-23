<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AvailabilityDate extends Model
{
    public const OWNER_CAST = 'cast';
    public const OWNER_SHOP = 'shop';

    protected $table = 'availability_dates';

    protected $fillable = [
        'owner_type',
        'owner_id',
        'available_on',
    ];

    protected $casts = [
        'available_on' => 'date',
    ];
}
