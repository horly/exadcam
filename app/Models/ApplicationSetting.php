<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApplicationSetting extends Model
{
    public $incrementing = false;

    protected $fillable = ['values'];

    protected function casts(): array
    {
        return ['values' => 'array'];
    }
}
