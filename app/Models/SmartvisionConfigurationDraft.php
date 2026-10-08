<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

// A desired configuration only: nothing in this model dispatches device commands.
class SmartvisionConfigurationDraft extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['settings' => 'array', 'revision' => 'integer'];
    }
}
