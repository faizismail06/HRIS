<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

abstract class OperationalModel extends Model
{
    protected $guarded = ['id'];

    protected $dateFormat = 'Y-m-d H:i:sP';
}
