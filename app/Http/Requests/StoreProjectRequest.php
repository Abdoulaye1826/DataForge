<?php

namespace App\Http\Requests;

use App\Enums\ProjectDomain;
use App\Enums\ProjectObjective;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class StoreProjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Project::class);
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'domain' => ['nullable', new Enum(ProjectDomain::class)],
            'domain_other' => ['nullable', 'string', 'max:255'],
            'objective' => ['nullable', new Enum(ProjectObjective::class)],
            'objective_other' => ['nullable', 'string', 'max:255'],
        ];
    }
}
