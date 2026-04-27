<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Log extends Model
{
    protected $fillable = [
        'tenant_id',
        'user_id',
        'action',
        'user_role',
        'entity_type',
        'entity_id',
        'description',
    ];
}
