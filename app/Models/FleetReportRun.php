<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FleetReportRun extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['filters' => 'array', 'vehicle_ids' => 'array', 'expires_at' => 'datetime'];
    }

    public function resultPath(): string
    {
        return 'reports/'.$this->id.'.json';
    }
}
