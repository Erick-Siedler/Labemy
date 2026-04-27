<?php

namespace App\Http\Requests;

class UpdateFuncReqRequest extends RequirementRequest
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
        return [
            'code' => ['nullable', 'string', 'max:40'],
            'title' => ['required', 'string', 'min:3', 'max:255'],
            'description' => ['nullable', 'string'],
            'priority' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'string', 'max:30'],
            'acceptance_criteria' => ['nullable', 'string'],
        ];
    }
}
