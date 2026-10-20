<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkflowDefinition extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['steps' => 'array', 'is_active' => 'boolean'];
    }
}
