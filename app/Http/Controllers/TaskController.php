<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Task;
use App\Models\Tenant;
use App\Models\UserRelation;
use App\Services\TaskAccessService;
use App\Services\TenantContextService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TaskController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validateWithBag('taskCreate', [
            'project_id' => 'required|integer',
            'title' => 'required|string|min:3|max:255',
            'description' => 'required|string|min:3',
            'version_id' => 'nullable|integer',
        ]);

        $tenant = $this->resolveTenantForTaskAccess();
        $project = Project::where('id', $validated['project_id'])
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();
        abort_unless($this->canManageProjectTasks($tenant, $project), 403);

        $request->validateWithBag('taskCreate', [
            'version_id' => [
                'nullable',
                'integer',
                Rule::exists('project_versions', 'id')->where(function ($query) use ($tenant, $project) {
                    $allowedStatuses = $this->allowedVersionStatusesForTaskLink();
                    $query->where('tenant_id', $tenant->id)
                        ->where('project_id', $project->id)
                        ->whereIn('status_version', $allowedStatuses);
                }),
            ],
        ], [
            'version_id.exists' => $this->versionLinkValidationMessage(),
        ]);

        Task::create([
            'tenant_id' => $tenant->id,
            'project_id' => $project->id,
            'version_id' => $validated['version_id'] ?? null,
            'title' => $validated['title'],
            'description' => $validated['description'],
            'status' => 'draft',
        ]);

        return back()->with('status', 'Task criada com sucesso.');
    }

    public function edit(Request $request, $task)
    {
        $validated = $request->validateWithBag('taskEdit', [
            'project_id' => 'required|integer',
            'task_id' => 'required|integer',
            'title' => 'required|string|min:3|max:255',
            'description' => 'required|string|min:3',
            'version_id' => 'nullable|integer',
        ]);

        $tenant = $this->resolveTenantForTaskAccess();
        $project = Project::where('id', $validated['project_id'])
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();
        abort_unless($this->canManageProjectTasks($tenant, $project), 403);

        $taskRow = Task::where('id', $task)
            ->where('tenant_id', $tenant->id)
            ->where('project_id', $project->id)
            ->firstOrFail();

        if ((int) $validated['task_id'] !== (int) $taskRow->id) {
            return back()
                ->withErrors(['task_id' => 'Task invalida para edicao.'], 'taskEdit')
                ->withInput();
        }

        $request->validateWithBag('taskEdit', [
            'version_id' => [
                'nullable',
                'integer',
                Rule::exists('project_versions', 'id')->where(function ($query) use ($tenant, $project) {
                    $allowedStatuses = $this->allowedVersionStatusesForTaskLink();
                    $query->where('tenant_id', $tenant->id)
                        ->where('project_id', $project->id)
                        ->whereIn('status_version', $allowedStatuses);
                }),
            ],
        ], [
            'version_id.exists' => $this->versionLinkValidationMessage(),
        ]);

        $taskRow->update([
            'version_id' => $validated['version_id'] ?? null,
            'title' => $validated['title'],
            'description' => $validated['description'],
        ]);

        return back()->with('status', 'Task atualizada com sucesso.');
    }

    public function destroy($task)
    {
        $tenant = $this->resolveTenantForTaskAccess();

        $taskRow = Task::where('id', $task)
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        $project = Project::where('id', $taskRow->project_id)
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();
        abort_unless($this->canManageProjectTasks($tenant, $project), 403);

        $taskRow->delete();

        return back()->with('status', 'Task removida com sucesso.');
    }

    public function updateStatus(Request $request, $task)
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['draft', 'approved', 'in_progress', 'done'])],
        ]);

        $tenant = $this->resolveTenantForTaskAccess();

        $taskRow = Task::where('id', $task)
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        $project = Project::where('id', $taskRow->project_id)
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();
        abort_unless($this->canManageProjectTasks($tenant, $project), 403);

        $taskRow->status = $validated['status'];
        $taskRow->save();

        return response()->json([
            'success' => true,
            'task_id' => (int) $taskRow->id,
            'status' => (string) $taskRow->status,
        ]);
    }

    private function resolveTenantForTaskAccess(): Tenant
    {
        return app(TaskAccessService::class)->resolveTenantForTaskAccess();
    }

    /**
     * Define se o usuario atual pode gerenciar tasks do projeto.
     */
    private function canManageProjectTasks(Tenant $tenant, Project $project): bool
    {
        return app(TaskAccessService::class)->canManageProjectTasks($tenant, $project);
    }

    /**
     * Retorna os status de versao aceitos no vinculo de task para o perfil atual.
     */
    private function allowedVersionStatusesForTaskLink(): array
    {
        $user = Auth::user();
        if (!$user) {
            return ['approved'];
        }

        if (in_array((string) $user->role, ['student', 'assistant', 'assitant'], true)) {
            return ['approved', 'submitted'];
        }

        return ['approved'];
    }

    /**
     * Mensagem de validacao conforme status permitido para vinculo de task.
     */
    private function versionLinkValidationMessage(): string
    {
        $allowedStatuses = $this->allowedVersionStatusesForTaskLink();

        if (in_array('submitted', $allowedStatuses, true)) {
            return 'Selecione uma versao aprovada ou enviada deste projeto.';
        }

        return 'Selecione uma versao aprovada deste projeto.';
    }
}
