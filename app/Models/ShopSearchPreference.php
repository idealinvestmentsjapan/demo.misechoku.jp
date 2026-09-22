<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShopSearchPreference extends Model
{
    protected $casts = [
        'work_periods' => 'array',
    ];
}
