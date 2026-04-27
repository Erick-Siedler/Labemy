<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\User;

class Payment extends Model
{
    protected $fillable = [
        'user_id', 'CPF/CNPJ', 'email','plan', 'amount', 'status', 'payment_token'
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
