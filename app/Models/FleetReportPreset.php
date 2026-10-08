<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FleetReportPreset extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['filters' => 'array'];
    }
}
