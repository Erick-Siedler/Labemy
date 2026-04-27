<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class StoreNonFuncReqRequest extends RequirementRequest
{
    /**
     * Define se o usuario possui autorizacao para esta acao.
     */
    public function authorize(): bool
    {
        return !$this->isReadOnlyActor();
    }

    /**
     * Define as regras de validacao desta requisicao.
     */
    public function rules(): array
    {
        $tenantId = $this->currentTenantId();
        $projectId = $this->currentProjectId();

        return [
            'code' => ['nullable', 'string', 'max:40'],
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['nullable', 'string'],
            'category' => ['nullable', 'string', 'max:60'],
            'priority' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'string', 'max:30'],
            'acceptance_criteria' => ['nullable', 'string'],
            'func_req_ids' => ['nullable', 'array'],
            'func_req_ids.*' => [
                'integer',
                Rule::exists('func_reqs', 'id')->where(function ($query) use ($tenantId, $projectId) {
                    if (!empty($tenantId)) {
                        $query->where('tenant_id', (int) $tenantId);
                    }
                    if (!empty($projectId)) {
                        $query->where('project_id', (int) $projectId);
                    }
                }),
            ],
        ];
    }
}
