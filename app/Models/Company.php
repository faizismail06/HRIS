<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    public $incrementing = false;

    protected $attributes = ['id' => 1];

    protected $guarded = ['id'];
}
