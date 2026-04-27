<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;

class Notification extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'table',
        'description',
        'type',
        'source',
        'reference_type',
        'reference_id',
    ];

    /**
     * Executa a rotina 'user' no fluxo de negocio.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
