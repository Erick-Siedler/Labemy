<?php

namespace App\Services;

use App\Models\Lab;
use App\Models\Log;
use App\Models\Notification;
use App\Models\SubUsers;
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ActivityService
{
    public static function notifyUser(int $userId, string $message, string $type = 'alert'): void
    {
        Notification::create([
            'user_id' => $userId,
            'table' => 'users',
            'description' => $message,
            'type' => $type,
        ]);
    }

    public static function notifySubUser(int $subUserId, string $message, string $type = 'alert'): void
    {
        Notification::create([
            'user_id' => $subUserId,
            'table' => 'subusers',
            'description' => $message,
            'type' => $type,
        ]);
    }

    public static function notifySubUsers(iterable $subUsers, string $message, string $type = 'alert'): void
    {
        foreach ($subUsers as $subUser) {
            $id = $subUser instanceof SubUsers ? $subUser->id : (int) $subUser;
            if ($id) {
                self::notifySubUser($id, $message, $type);
            }
        }
    }

    public static function notifyOwnerAndStaff(Tenant $tenant, string $message, ?int $labId = null, ?int $groupId = null, bool $includeAssistants = true): void
    {
        self::notifyUser($tenant->creator_id, $message, 'approval');

        $roles = $includeAssistants ? ['teacher', 'assistant', 'asssitant', 'assitant'] : ['teacher'];

        $staffQuery = SubUsers::where('tenant_id', $tenant->id)
            ->whereIn('role', $roles);

        if ($labId || $groupId) {
            $staffQuery->where(function ($query) use ($labId, $groupId) {
                if ($groupId) {
                    $query->where('group_id', $groupId);
                }
                if ($labId) {
                    $query->orWhere('lab_id', $labId);
                }
            });
        }

        $staffIds = $staffQuery->pluck('id');
        foreach ($staffIds as $staffId) {
            self::notifySubUser((int) $staffId, $message, 'approval');
        }
    }

    public static function log(int $tenantId, int $actorId, string $actorRole, string $action, string $entityType, ?int $entityId, string $description): void
    {
        Log::create([
            'tenant_id' => $tenantId,
            'user_id' => $actorId,
            'action' => $action,
            'user_role' => $actorRole,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'description' => $description,
        ]);
    }

    public static function resolveTenantIdFromSubUser(SubUsers $subUser): ?int
    {
        if (!empty($subUser->tenant_id)) {
            return (int) $subUser->tenant_id;
        }

        if (!empty($subUser->lab_id)) {
            return (int) Lab::where('id', $subUser->lab_id)->value('tenant_id');
        }

        return null;
    }

    public static function resolveActor(): array
    {
        $subUser = Auth::guard('subusers')->user();
        if ($subUser) {
            return [
                'actor_id' => (int) $subUser->id,
                'actor_role' => (string) $subUser->role,
                'tenant_id' => self::resolveTenantIdFromSubUser($subUser),
                'actor_type' => 'subuser',
            ];
        }

        $user = Auth::user();
        if ($user) {
            $tenantId = Tenant::where('creator_id', $user->id)->value('id');
            return [
                'actor_id' => (int) $user->id,
                'actor_role' => (string) ($user->role ?? 'owner'),
                'tenant_id' => $tenantId ? (int) $tenantId : null,
                'actor_type' => 'user',
            ];
        }

        return [
            'actor_id' => 0,
            'actor_role' => 'guest',
            'tenant_id' => null,
            'actor_type' => 'guest',
        ];
    }

    public static function formatRole(string $role): string
    {
        $map = [
            'owner' => 'Owner',
            'teacher' => 'Professor',
            'assistant' => 'Assistente',
            'asssitant' => 'Assistente',
            'assitant' => 'Assistente',
            'student' => 'Aluno',
        ];

        return $map[$role] ?? Str::title($role);
    }
}
