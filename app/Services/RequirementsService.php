<?php

namespace App\Services;

use App\Exports\RequirementsExport;
use App\Http\Requests\StoreFuncReqRequest;
use App\Http\Requests\StoreNonFuncReqRequest;
use App\Http\Requests\UpdateFuncReqRequest;
use App\Http\Requests\UpdateNonFuncReqRequest;
use App\Models\FuncReq;
use App\Models\NonFuncReq;
use App\Models\Project;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class RequirementsService
{
    public function index(
        Request $request,
        Project $project,
        HomeOwnerDataService $homeOwnerData,
        SubHomeDataService $subHomeData
    ) {
        [$projectData, $tenantId, $actor] = $this->loadAuthorizedProject($project);
        $projectId = (int) $projectData->id;

        $functionalRelation = [
            'nonFunctional' => function ($query) use ($tenantId, $projectId) {
                $query->where('tenant_id', $tenantId)
                    ->where('project_id', $projectId)
                    ->orderBy('code');
            },
        ];

        $funcReqs = FuncReq::forTenant($tenantId)
            ->forProject($projectId)
            ->with($functionalRelation)
            ->orderBy('code')
            ->paginate(4, ['*'], 'rf_page')
            ->withQueryString()
            ->fragment('rf-section');

        $funcReqOptions = FuncReq::forTenant($tenantId)
            ->forProject($projectId)
            ->orderBy('code')
            ->get();

        $nonFunctionalRelation = [
            'functional' => function ($query) use ($tenantId, $projectId) {
                $query->where('tenant_id', $tenantId)
                    ->where('project_id', $projectId)
                    ->orderBy('code');
            },
        ];

        $allNonFuncReqsCount = NonFuncReq::forTenant($tenantId)
            ->forProject($projectId)
            ->count();

        $globalNonFuncReqs = NonFuncReq::forTenant($tenantId)
            ->forProject($projectId)
            ->whereDoesntHave('functional', function ($query) use ($tenantId, $projectId) {
                $query->where('tenant_id', $tenantId)
                    ->where('project_id', $projectId);
            })
            ->with($nonFunctionalRelation)
            ->orderBy('code')
            ->paginate(4, ['*'], 'rnf_global_page')
            ->withQueryString()
            ->fragment('rnf-global-section');

        $linkedNonFuncReqs = NonFuncReq::forTenant($tenantId)
            ->forProject($projectId)
            ->whereHas('functional', function ($query) use ($tenantId, $projectId) {
                $query->where('tenant_id', $tenantId)
                    ->where('project_id', $projectId);
            })
            ->with($nonFunctionalRelation)
            ->orderBy('code')
            ->paginate(4, ['*'], 'rnf_linked_page')
            ->withQueryString()
            ->fragment('rnf-linked-section');

        $data = $this->buildLayoutData($actor, $projectData, $homeOwnerData, $subHomeData);
        $data['funcReqsWithNonFunc'] = $funcReqs;
        $data['funcReqOptions'] = $funcReqOptions;
        $data['globalNonFuncReqs'] = $globalNonFuncReqs;
        $data['linkedNonFuncReqs'] = $linkedNonFuncReqs;
        $data['allNonFuncReqsCount'] = $allNonFuncReqsCount;
        $data['paginationState'] = $this->requirementsPaginationStateFromRequest($request);

        return view('main.home.requirements-student', $data);
    }

    public function export(Project $project)
    {
        [$projectData, $tenantId] = $this->loadAuthorizedProject($project);
        $projectId = (int) $projectData->id;
        $projectSlug = Str::slug((string) ($projectData->title ?? 'project'));
        $fileName = 'requirements-' . $projectSlug . '-' . now()->format('Ymd_His') . '.xlsx';

        return Excel::download(
            new RequirementsExport($tenantId, $projectId),
            $fileName
        );
    }

    public function storeFunc(StoreFuncReqRequest $request, Project $project): RedirectResponse
    {
        [$projectData, $tenantId, $actor] = $this->loadAuthorizedProject($project);
        if ($redirect = $this->readOnlyRedirect($actor, (int) $projectData->id, $request)) {
            return $redirect;
        }
        $validated = $request->validated();

        $code = $this->resolveFuncCode(
            (string) ($validated['code'] ?? ''),
            $tenantId,
            (int) $projectData->id
        );

        $funcReq = FuncReq::create([
            'tenant_id' => $tenantId,
            'project_id' => (int) $projectData->id,
            'created_by_table' => $actor['table'],
            'created_by_id' => $actor['id'],
            'code' => $code,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'priority' => $validated['priority'] ?? 'medium',
            'status' => $validated['status'] ?? 'draft',
            'acceptance_criteria' => $validated['acceptance_criteria'] ?? null,
        ]);

        $this->logAction(
            $tenantId,
            $actor,
            'func_req_create',
            'func_req',
            (int) $funcReq->id,
            'Requisito funcional criado: ' . $funcReq->code . ' - ' . $funcReq->title . '.'
        );

        return $this->redirectToRequirementsIndex($projectData, $request)
            ->with('success', 'Requisito funcional criado.');
    }

    public function updateFunc(UpdateFuncReqRequest $request, Project $project, FuncReq $funcReq): RedirectResponse
    {
        [$projectData, $tenantId, $actor] = $this->loadAuthorizedProject($project);
        if ($redirect = $this->readOnlyRedirect($actor, (int) $projectData->id, $request)) {
            return $redirect;
        }
        $this->ensureFuncReqBelongsToProject($funcReq, $tenantId, (int) $projectData->id);

        $validated = $request->validated();
        $incomingCode = trim((string) ($validated['code'] ?? ''));
        $resolvedCode = $incomingCode !== ''
            ? $this->resolveFuncCode($incomingCode, $tenantId, (int) $projectData->id, (int) $funcReq->id)
            : $funcReq->code;

        $funcReq->update([
            'code' => $resolvedCode,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'priority' => $validated['priority'] ?? 'medium',
            'status' => $validated['status'] ?? 'draft',
            'acceptance_criteria' => $validated['acceptance_criteria'] ?? null,
        ]);

        $this->logAction(
            $tenantId,
            $actor,
            'func_req_update',
            'func_req',
            (int) $funcReq->id,
            'Requisito funcional atualizado: ' . $funcReq->code . ' - ' . $funcReq->title . '.'
        );

        return $this->redirectToRequirementsIndex($projectData, $request)
            ->with('success', 'Requisito funcional atualizado.');
    }

    public function destroyFunc(Request $request, Project $project, FuncReq $funcReq): RedirectResponse
    {
        [$projectData, $tenantId, $actor] = $this->loadAuthorizedProject($project);
        if ($redirect = $this->readOnlyRedirect($actor, (int) $projectData->id, $request)) {
            return $redirect;
        }
        $this->ensureFuncReqBelongsToProject($funcReq, $tenantId, (int) $projectData->id);

        $code = $funcReq->code;
        $title = $funcReq->title;
        $funcReq->delete();

        $this->logAction(
            $tenantId,
            $actor,
            'func_req_delete',
            'func_req',
            (int) $funcReq->id,
            'Requisito funcional removido: ' . $code . ' - ' . $title . '.'
        );

        return $this->redirectToRequirementsIndex($projectData, $request)
            ->with('success', 'Requisito funcional removido.');
    }

    public function storeNonFunc(StoreNonFuncReqRequest $request, Project $project): RedirectResponse
    {
        [$projectData, $tenantId, $actor] = $this->loadAuthorizedProject($project);
        if ($redirect = $this->readOnlyRedirect($actor, (int) $projectData->id, $request)) {
            return $redirect;
        }
        $validated = $request->validated();

        $code = $this->resolveNonFuncCode(
            (string) ($validated['code'] ?? ''),
            $tenantId,
            (int) $projectData->id
        );

        $funcReqIds = $this->normalizeIds($validated['func_req_ids'] ?? []);
        $this->assertFuncReqIdsBelongToProject($funcReqIds, $tenantId, (int) $projectData->id);

        $nonFuncReq = NonFuncReq::create([
            'tenant_id' => $tenantId,
            'project_id' => (int) $projectData->id,
            'created_by_table' => $actor['table'],
            'created_by_id' => $actor['id'],
            'code' => $code,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'] ?? null,
            'priority' => $validated['priority'] ?? 'medium',
            'status' => $validated['status'] ?? 'draft',
            'acceptance_criteria' => $validated['acceptance_criteria'] ?? null,
        ]);

        $nonFuncReq->functional()->sync($funcReqIds);

        $this->logAction(
            $tenantId,
            $actor,
            'non_func_req_create',
            'non_func_req',
            (int) $nonFuncReq->id,
            'Requisito nao funcional criado: ' . $nonFuncReq->code . ' - ' . $nonFuncReq->title . '.'
        );

        return $this->redirectToRequirementsIndex($projectData, $request)
            ->with('success', 'Requisito nao funcional criado.');
    }

    public function updateNonFunc(UpdateNonFuncReqRequest $request, Project $project, NonFuncReq $nonFuncReq): RedirectResponse
    {
        [$projectData, $tenantId, $actor] = $this->loadAuthorizedProject($project);
        if ($redirect = $this->readOnlyRedirect($actor, (int) $projectData->id, $request)) {
            return $redirect;
        }
        $this->ensureNonFuncReqBelongsToProject($nonFuncReq, $tenantId, (int) $projectData->id);

        $validated = $request->validated();
        $incomingCode = trim((string) ($validated['code'] ?? ''));
        $resolvedCode = $incomingCode !== ''
            ? $this->resolveNonFuncCode($incomingCode, $tenantId, (int) $projectData->id, (int) $nonFuncReq->id)
            : $nonFuncReq->code;

        $funcReqIds = $this->normalizeIds($validated['func_req_ids'] ?? []);
        $this->assertFuncReqIdsBelongToProject($funcReqIds, $tenantId, (int) $projectData->id);

        $nonFuncReq->update([
            'code' => $resolvedCode,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'category' => $validated['category'] ?? null,
            'priority' => $validated['priority'] ?? 'medium',
            'status' => $validated['status'] ?? 'draft',
            'acceptance_criteria' => $validated['acceptance_criteria'] ?? null,
        ]);

        $nonFuncReq->functional()->sync($funcReqIds);

        $this->logAction(
            $tenantId,
            $actor,
            'non_func_req_update',
            'non_func_req',
            (int) $nonFuncReq->id,
            'Requisito nao funcional atualizado: ' . $nonFuncReq->code . ' - ' . $nonFuncReq->title . '.'
        );

        return $this->redirectToRequirementsIndex($projectData, $request)
            ->with('success', 'Requisito nao funcional atualizado.');
    }

    public function destroyNonFunc(Request $request, Project $project, NonFuncReq $nonFuncReq): RedirectResponse
    {
        [$projectData, $tenantId, $actor] = $this->loadAuthorizedProject($project);
        if ($redirect = $this->readOnlyRedirect($actor, (int) $projectData->id, $request)) {
            return $redirect;
        }
        $this->ensureNonFuncReqBelongsToProject($nonFuncReq, $tenantId, (int) $projectData->id);

        $code = $nonFuncReq->code;
        $title = $nonFuncReq->title;
        $nonFuncReq->delete();

        $this->logAction(
            $tenantId,
            $actor,
            'non_func_req_delete',
            'non_func_req',
            (int) $nonFuncReq->id,
            'Requisito nao funcional removido: ' . $code . ' - ' . $title . '.'
        );

        return $this->redirectToRequirementsIndex($projectData, $request)
            ->with('success', 'Requisito nao funcional removido.');
    }

    private function buildLayoutData(array $actor, Project $project, HomeOwnerDataService $homeOwnerData, SubHomeDataService $subHomeData): array
    {
        $isSubUser = in_array((string) ($actor['role'] ?? ''), ['teacher', 'assistant', 'assitant', 'student'], true);
        $role = (string) $actor['role'];
        $authenticatedUser = $actor['user'];

        if (!$isSubUser) {
            $isSoloOwner = $this->isSoloTenantContext($authenticatedUser, (int) $project->tenant_id);
            $data = $isSoloOwner
                ? $homeOwnerData->buildSolo($authenticatedUser)
                : $homeOwnerData->build($authenticatedUser);
        } elseif ($role === 'student') {
            $data = $subHomeData->buildStudent($authenticatedUser);
        } elseif (in_array($role, ['assistant', 'assitant'], true)) {
            $data = $subHomeData->buildAssistant($authenticatedUser);
        } else {
            $data = $subHomeData->buildTeacher($authenticatedUser);
        }

        $data['layout'] = $isSubUser ? 'layouts.header-side-not-sub' : 'layouts.header-side-not';
        $data['user'] = $authenticatedUser;
        $data['project'] = $project;
        $data['lab'] = $project->lab;
        $data['group'] = $project->group;
        $data['pageTitle'] = 'Requisitos';
        $data['pageBreadcrumbHome'] = 'Inicio';
        $data['pageBreadcrumbCurrent'] = 'Requisitos';
        $data['projectBackUrl'] = $this->resolveProjectBackUrl($actor, $project);
        $data['theme'] = app(UserUiPreferencesService::class)->resolveTheme($data['userPreferences'] ?? null);
        $data['readonly'] = $this->isReadOnlyActor($actor);

        return $data;
    }

    private function resolveProjectBackUrl(array $actor, Project $project): string
    {
        $user = $actor['user'] ?? null;
        if ($this->isSoloTenantContext($user, (int) $project->tenant_id)) {
            return route('home-solo', ['project' => $project->id]);
        }

        if (
            in_array((string) ($actor['role'] ?? ''), ['student', 'assistant', 'assitant'], true)
        ) {
            return route('subuser-home', [
                'project' => $project->id,
                'group' => $project->group_id,
            ]);
        }

        return route('project.index', ['project' => $project->id]);
    }

    private function loadAuthorizedProject(Project $project): array
    {
        $user = Auth::guard('web')->user();
        if (!$user) {
            abort(403);
        }

        $tenantContext = app(TenantContextService::class);
        $tenant = $tenantContext->resolveTenantFromSession($user, true);
        $projectData = Project::with(['lab', 'group'])
            ->where('tenant_id', (int) $tenant->id)
            ->where('id', (int) $project->id)
            ->firstOrFail();

        $isOwner = $tenantContext->isOwnerContext($user, $tenant);
        $role = $tenantContext->resolveRoleInTenant($user, $tenant);

        if (!$isOwner) {
            $this->ensureSubUserCanAccessProject($user, $projectData);
        }

        return [
            $projectData,
            (int) $tenant->id,
            [
                'table' => 'users',
                'id' => (int) $user->id,
                'role' => $role,
                'user' => $user,
                'tenant_id' => (int) $tenant->id,
            ],
        ];
    }

    private function ensureSubUserCanAccessProject($subUser, Project $project): void
    {
        $role = (string) ($subUser->role ?? '');

        if ($role === 'teacher') {
            $isTeacherOwner = (int) ($project->lab?->creator_subuser_id ?? 0) === (int) $subUser->id;
            $isAssignedLab = !empty($subUser->lab_id) && (int) $project->lab_id === (int) $subUser->lab_id;
            if (!$isTeacherOwner && !$isAssignedLab) {
                abort(403);
            }

            return;
        }

        if (in_array($role, ['assistant', 'assitant'], true)) {
            $isAssignedLab = !empty($subUser->lab_id) && (int) $project->lab_id === (int) $subUser->lab_id;
            if (!$isAssignedLab) {
                abort(403);
            }

            return;
        }

        if ($role === 'student') {
            $isAssignedGroup = !empty($subUser->group_id) && (int) $project->group_id === (int) $subUser->group_id;
            if (!$isAssignedGroup) {
                abort(403);
            }

            return;
        }

        abort(403);
    }

    private function ensureFuncReqBelongsToProject(FuncReq $funcReq, int $tenantId, int $projectId): void
    {
        if ((int) $funcReq->tenant_id !== $tenantId || (int) $funcReq->project_id !== $projectId) {
            abort(404);
        }
    }

    private function ensureNonFuncReqBelongsToProject(NonFuncReq $nonFuncReq, int $tenantId, int $projectId): void
    {
        if ((int) $nonFuncReq->tenant_id !== $tenantId || (int) $nonFuncReq->project_id !== $projectId) {
            abort(404);
        }
    }

    private function normalizeIds(array $ids): array
    {
        return collect($ids)
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    private function assertFuncReqIdsBelongToProject(array $funcReqIds, int $tenantId, int $projectId): void
    {
        if (empty($funcReqIds)) {
            return;
        }

        $count = FuncReq::forTenant($tenantId)
            ->forProject($projectId)
            ->whereIn('id', $funcReqIds)
            ->count();

        if ($count !== count($funcReqIds)) {
            throw ValidationException::withMessages([
                'func_req_ids' => 'Selecione apenas requisitos funcionais do mesmo projeto.',
            ]);
        }
    }

    private function requirementsPaginationStateFromRequest(Request $request): array
    {
        return [
            'rf_page' => max(1, (int) $request->input('rf_page', $request->query('rf_page', 1))),
            'rnf_global_page' => max(1, (int) $request->input('rnf_global_page', $request->query('rnf_global_page', 1))),
            'rnf_linked_page' => max(1, (int) $request->input('rnf_linked_page', $request->query('rnf_linked_page', 1))),
        ];
    }

    private function redirectToRequirementsIndex(Project $project, Request $request): RedirectResponse
    {
        return redirect()->route(
            'requirements.index',
            array_merge(
                ['project' => (int) $project->id],
                $this->requirementsPaginationStateFromRequest($request)
            )
        );
    }

    private function resolveFuncCode(string $incomingCode, int $tenantId, int $projectId, ?int $ignoreId = null): string
    {
        $code = trim($incomingCode);
        if ($code === '') {
            return $this->nextCode('RF', FuncReq::class, $tenantId, $projectId);
        }

        $normalized = strtoupper($code);
        $query = FuncReq::forTenant($tenantId)
            ->forProject($projectId)
            ->where('code', $normalized);

        if (!is_null($ignoreId)) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'code' => 'Codigo RF ja utilizado neste projeto.',
            ]);
        }

        return $normalized;
    }

    private function resolveNonFuncCode(string $incomingCode, int $tenantId, int $projectId, ?int $ignoreId = null): string
    {
        $code = trim($incomingCode);
        if ($code === '') {
            return $this->nextCode('RNF', NonFuncReq::class, $tenantId, $projectId);
        }

        $normalized = strtoupper($code);
        $query = NonFuncReq::forTenant($tenantId)
            ->forProject($projectId)
            ->where('code', $normalized);

        if (!is_null($ignoreId)) {
            $query->where('id', '!=', $ignoreId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'code' => 'Codigo RNF ja utilizado neste projeto.',
            ]);
        }

        return $normalized;
    }

    private function nextCode(string $prefix, string $modelClass, int $tenantId, int $projectId): string
    {
        $existingCodes = $modelClass::forTenant($tenantId)
            ->forProject($projectId)
            ->where('code', 'like', $prefix . '-%')
            ->pluck('code');

        $highest = 0;
        foreach ($existingCodes as $existingCode) {
            if (preg_match('/^' . preg_quote($prefix, '/') . '-(\d+)$/i', (string) $existingCode, $matches)) {
                $current = (int) $matches[1];
                if ($current > $highest) {
                    $highest = $current;
                }
            }
        }

        do {
            $highest++;
            $candidate = sprintf('%s-%02d', $prefix, $highest);
            $exists = $modelClass::forTenant($tenantId)
                ->forProject($projectId)
                ->where('code', $candidate)
                ->exists();
        } while ($exists);

        return $candidate;
    }

    private function logAction(
        int $tenantId,
        array $actor,
        string $action,
        string $entityType,
        int $entityId,
        string $description
    ): void {
        ActivityService::log(
            $tenantId,
            (int) $actor['id'],
            (string) $actor['role'],
            $action,
            $entityType,
            $entityId,
            $description
        );
    }

    private function isReadOnlyActor(array $actor): bool
    {
        $role = (string) ($actor['role'] ?? '');
        $user = $actor['user'] ?? null;
        $tenantId = (int) ($actor['tenant_id'] ?? 0);

        if ($role === 'owner') {
            if ($this->isSoloTenantContext($user, $tenantId)) {
                return false;
            }

            return true;
        }

        return $role === 'teacher';
    }

    private function isSoloTenantContext($user, int $tenantId): bool
    {
        if (!$user || $tenantId <= 0) {
            return false;
        }

        return Tenant::where('id', $tenantId)
            ->where('creator_id', (int) $user->id)
            ->where('plan', 'solo')
            ->exists();
    }

    private function readOnlyRedirect(array $actor, int $projectId, ?Request $request = null): ?RedirectResponse
    {
        if (!$this->isReadOnlyActor($actor)) {
            return null;
        }

        $params = ['project' => $projectId];
        if ($request) {
            $params = array_merge($params, $this->requirementsPaginationStateFromRequest($request));
        }

        return redirect()
            ->route('requirements.index', $params)
            ->with('error', 'Acesso somente leitura.');
    }
}
