<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Event;
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;
use App\Models\Lab;
use App\Models\UserRelation;
use Illuminate\Support\Facades\Validator;
use App\Services\ActivityService;

class EventController extends Controller
{
    /**
     * Valida os dados recebidos e persiste um novo registro.
     */
    public function store(Request $request)
    {
        $subUser = Auth::user();
        $actorUserId = (int) (Auth::id() ?? 0);
        $isMember = $subUser && $subUser->role !== 'owner';
        if ($isMember) {
            if ($subUser->role !== 'teacher') {
                abort(403);
            }
            $tenantId = $subUser->tenant_id ?: Lab::where('id', $subUser->lab_id)->value('tenant_id');
            $tenant = Tenant::where('id', $tenantId)->firstOrFail();
            $creatorId = $tenant->creator_id;
        } else {
            $tenant = $this->resolveOwnerTenantFromSessionOrFallback((int) Auth::id());
            $creatorId = (int) Auth::id();
        }

        $validator = Validator::make($request->all(), [
            'lab_id' => 'required',
            'title' => 'required|min:3',
            'description' => 'required|min:3',
            'color' => 'required',
            'due' => 'required',
            'is_mandatory' => 'required',
        ]);

        if ($validator->fails()) {
            return $this->eventValidationResponse($request, $validator->errors()->toArray());
        }

        $data = $validator->validated();

        if ($data['lab_id'] !== 'all') {
            $labQuery = Lab::where('tenant_id', $tenant->id)
                ->where('id', $data['lab_id']);

            if ($isMember) {
                $labQuery->where('creator_subuser_id', $subUser->id);
            }

            $lab = $labQuery->first();
            if (!$lab) {
                return $this->eventValidationResponse($request, [
                    'lab_id' => ['Laboratório inválido.'],
                ]);
            }
        }

        if ($data['lab_id'] === 'all') {
            if ($isMember) {
                return $this->eventValidationResponse($request, [
                    'lab_id' => ['Selecione um laboratório válido.'],
                ]);
            }

            $labs = Lab::where('tenant_id', $tenant->id)
                ->orderBy('created_at', 'desc')
                ->get();

            $actor = ActivityService::resolveActor();
            $eventsCreated = 0;

            foreach ($labs as $lab) {
                $event = Event::create([
                    'tenant_id' => $tenant->id,
                    'lab_id' => $lab->id,
                    'created_by' => $creatorId,
                    'title' => $data['title'],
                    'description' => $data['description'],
                    'color' => $data['color'],
                    'due' => $data['due'],
                    'is_mandatory' => $data['is_mandatory'],
                ]);
                $eventsCreated++;

                if (!empty($actor['tenant_id'])) {
                    ActivityService::log(
                        (int) $actor['tenant_id'],
                        (int) $actor['actor_id'],
                        (string) $actor['actor_role'],
                        'event_create',
                        'event',
                        (int) $event->id,
                        'Evento criado: ' . $event->title . ' (Lab: ' . $lab->name . ').'
                    );
                }
            }

            $message = 'Novo evento criado para todos os laboratórios: ' . $data['title'] . ' (Vencimento: ' . $data['due'] . ').';
            $memberIds = UserRelation::where('tenant_id', $tenant->id)
                ->where('status', 'active')
                ->where('user_id', '!=', $actorUserId)
                ->distinct()
                ->pluck('user_id');
            ActivityService::notifyUsers($memberIds, $message, 'alert');

            return $this->eventSuccessResponse(
                $request,
                'Evento criado com sucesso para ' . $eventsCreated . ' laboratório(s).',
                ['events_created' => $eventsCreated]
            );
        }

        $event = Event::create([
            'tenant_id' => $tenant->id,
            'lab_id' => $data['lab_id'],
            'created_by' => $creatorId,
            'title' => $data['title'],
            'description' => $data['description'],
            'color' => $data['color'],
            'due' => $data['due'],
            'is_mandatory' => $data['is_mandatory'],
        ]);

        $actor = ActivityService::resolveActor();
        if (!empty($actor['tenant_id'])) {
            $labName = Lab::where('id', $data['lab_id'])->value('name') ?? 'N/A';
            ActivityService::log(
                (int) $actor['tenant_id'],
                (int) $actor['actor_id'],
                (string) $actor['actor_role'],
                'event_create',
                'event',
                (int) $event->id,
                'Evento criado: ' . $event->title . ' (Lab: ' . $labName . ').'
            );
        }

        $labName = Lab::where('id', $data['lab_id'])->value('name') ?? 'N/A';
        $message = 'Novo evento criado: ' . $data['title'] . ' (Lab: ' . $labName . ' / Vencimento: ' . $data['due'] . ').';
        $memberIds = UserRelation::where('tenant_id', $tenant->id)
            ->where('status', 'active')
            ->where('user_id', '!=', $actorUserId)
            ->distinct()
            ->pluck('user_id');
        ActivityService::notifyUsers($memberIds, $message, 'alert');

        return $this->eventSuccessResponse($request, 'Evento criado com sucesso.');
    }

    /**
     * Remove um evento respeitando o escopo de permissao do usuario logado.
     */
    public function destroy(Request $request, Event $event)
    {
        $user = Auth::user();
        if (!$user) {
            abort(403);
        }

        $actorUserId = (int) (Auth::id() ?? 0);
        $isMember = $user->role !== 'owner';

        if ($isMember) {
            if ($user->role !== 'teacher') {
                abort(403);
            }

            $tenantId = $user->tenant_id ?: Lab::where('id', $user->lab_id)->value('tenant_id');
            if ((int) $event->tenant_id !== (int) $tenantId) {
                abort(403);
            }

            $eventLab = $event->lab ?: Lab::where('id', $event->lab_id)->first();
            if (!$eventLab) {
                abort(404);
            }

            $isTeacherOwner = (int) ($eventLab->creator_subuser_id ?? 0) === (int) $user->id;
            $isAssignedLab = !empty($user->lab_id) && (int) $eventLab->id === (int) $user->lab_id;

            if (!$isTeacherOwner && !$isAssignedLab) {
                abort(403);
            }
        } else {
            $tenant = $this->resolveOwnerTenantFromSessionOrFallback($actorUserId);
            if ((int) $event->tenant_id !== (int) $tenant->id) {
                abort(403);
            }
        }

        $eventId = (int) $event->id;
        $eventTitle = (string) $event->title;
        $eventLabName = $event->lab?->name ?? (Lab::where('id', $event->lab_id)->value('name') ?? 'N/A');
        $tenantId = (int) $event->tenant_id;

        $event->delete();

        $actor = ActivityService::resolveActor();
        if (!empty($actor['tenant_id'])) {
            ActivityService::log(
                (int) $actor['tenant_id'],
                (int) $actor['actor_id'],
                (string) $actor['actor_role'],
                'event_delete',
                'event',
                $eventId,
                'Evento removido: ' . $eventTitle . ' (Lab: ' . $eventLabName . ').'
            );
        }

        $message = 'Evento removido: ' . $eventTitle . ' (Lab: ' . $eventLabName . ').';
        $memberIds = UserRelation::where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->where('user_id', '!=', $actorUserId)
            ->distinct()
            ->pluck('user_id');
        ActivityService::notifyUsers($memberIds, $message, 'alert');

        return $this->eventSuccessResponse($request, 'Evento removido com sucesso.', [], 200);
    }

    /**
     * Resolve tenant do owner priorizando tenant ativo em sessao.
     */
    private function resolveOwnerTenantFromSessionOrFallback(int $ownerId): Tenant
    {
        $activeTenantId = (int) session('active_tenant_id', 0);
        if ($activeTenantId > 0) {
            $selectedTenant = Tenant::where('id', $activeTenantId)
                ->where('creator_id', $ownerId)
                ->first();

            if ($selectedTenant) {
                return $selectedTenant;
            }
        }

        return Tenant::where('creator_id', $ownerId)->firstOrFail();
    }

    /**
     * Padroniza resposta de erro de validacao para ajax e submit tradicional.
     */
    private function eventValidationResponse(Request $request, array $errors, int $status = 422)
    {
        $message = collect($errors)
            ->flatten()
            ->filter()
            ->first() ?? 'Dados inválidos.';

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $message,
                'errors' => $errors,
            ], $status);
        }

        return back()->withErrors($errors, 'event')->withInput();
    }

    /**
     * Padroniza resposta de sucesso para ajax e submit tradicional.
     */
    private function eventSuccessResponse(Request $request, string $message, array $extraPayload = [], int $status = 201)
    {
        if ($request->expectsJson()) {
            return response()->json(array_merge([
                'success' => true,
                'message' => $message,
            ], $extraPayload), $status);
        }

        return back()->with('success', $message);
    }
}


