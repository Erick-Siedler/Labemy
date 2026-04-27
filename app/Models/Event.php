<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Lab;

class Event extends Model
{
    protected $fillable = [
        'tenant_id', 'lab_id', 'created_by', 'title', 'description', 'color', 'due', 'is_mandatory'
    ];

    /**
     * Executa a rotina 'lab' no fluxo de negocio.
     */
    public function lab(){
        return $this->belongsTo(Lab::class);
    }
}
