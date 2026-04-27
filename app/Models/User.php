<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\Tenant;
use App\Models\Notification;
use App\Models\UserRelation;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'tenant_id',
        'name',
        'email',
        'password',
        'role',
        'status',
        'phone',
        'institution',
        'bio',
        'preferences',
        'notifications',
        'profile_photo_path',
        'plan',
        'trial_used',
        'terms_accepted_at',
        'privacy_policy_accepted_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'preferences' => 'array',
            'notifications' => 'array',
            'trial_used' => 'boolean',
            'terms_accepted_at' => 'datetime',
            'privacy_policy_accepted_at' => 'datetime',
        ];
    }

    /**
     * Executa a rotina 'tenant' no fluxo de negocio.
     */
    public function tenant(){
        return $this->hasMany(Tenant::class);
    }

    /**
     * Executa a rotina 'relations' no fluxo de negocio.
     */
    public function relations()
    {
        return $this->hasMany(UserRelation::class, 'user_id');
    }

    /**
     * Executa a rotina 'notifications' no fluxo de negocio.
     */
    public function notifications(){
        return $this->hasMany(Notification::class);
    }

    /**
     * Retorna tenant ativo salvo em sessao.
     */
    public function activeTenantId(): ?int
    {
        $tenantId = (int) session('active_tenant_id', 0);
        return $tenantId > 0 ? $tenantId : null;
    }

    /**
     * Retorna relacao ativa do usuario na sessao.
     */
    public function activeRelation(): ?UserRelation
    {
        $relationId = (int) session('active_relation_id', 0);
        if ($relationId > 0) {
            return UserRelation::where('id', $relationId)
                ->where('user_id', $this->id)
                ->where('status', 'active')
                ->first();
        }

        $tenantId = $this->activeTenantId();
        if (!$tenantId) {
            return null;
        }

        return UserRelation::where('user_id', $this->id)
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->first();
    }

    /**
     * Fallback de tenant_id para relacao ativa.
     */
    public function getTenantIdAttribute($value): ?int
    {
        if (!empty($value)) {
            return (int) $value;
        }

        return $this->activeRelation()?->tenant_id;
    }

    /**
     * Fallback de lab_id para relacao ativa.
     */
    public function getLabIdAttribute($value): ?int
    {
        if (!empty($value)) {
            return (int) $value;
        }

        return $this->activeRelation()?->lab_id;
    }

    /**
     * Fallback de group_id para relacao ativa.
     */
    public function getGroupIdAttribute($value): ?int
    {
        if (!empty($value)) {
            return (int) $value;
        }

        return $this->activeRelation()?->group_id;
    }

    /**
     * Papel efetivo no tenant ativo.
     */
    public function getRoleAttribute($value): string
    {
        if (!empty($value) && (string) $value !== 'owner') {
            return (string) $value;
        }

        $selectedTenantId = (int) session('active_tenant_id', 0);
        if ($selectedTenantId > 0) {
            $ownsTenant = Tenant::where('id', $selectedTenantId)
                ->where('creator_id', $this->id)
                ->exists();

            if ($ownsTenant) {
                return 'owner';
            }

            $relationRole = $this->activeRelation()?->role;
            if (!empty($relationRole)) {
                return (string) $relationRole;
            }
        }

        return (string) ($value ?: 'owner');
    }
}
