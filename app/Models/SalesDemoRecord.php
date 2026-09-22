<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Raw fixture values must match the SQL export; no business events or PII mutators. */
final class SalesDemoRecord extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $guarded = [];

    public static function forTable(string $table): self
    {
        return (new self())->setTable($table);
    }
}
