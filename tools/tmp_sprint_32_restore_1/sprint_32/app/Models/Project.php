<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Project extends Model
{   

    protected $fillable =[
        'tenant_id', 'lab_id', 'group_id', 'title', 'slug', 'description', 'status', 'current_version', 'submitted_at', 'approved_at'
    ];
    
    public function lab(){
        return $this->belongsTo(Lab::class);
    }
    public function group()
    {
        return $this->belongsTo(Group::class);
    }
}
