<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->is_super_admin;
    }

    public function rules(): array
    {
        $planId = $this->route('plan')?->id;

        return [
            'name' => 'required|string|max:255',
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('plans', 'slug')->ignore($planId)],
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'currency' => 'nullable|string|max:10',
            'billing_days' => 'required|integer|min:1',
            'trial_days' => 'required|integer|min:0',
            'max_branches' => 'nullable|integer|min:0',
            'max_personal' => 'nullable|integer|min:0',
            'max_cajas' => 'nullable|integer|min:0',
            'max_users' => 'nullable|integer|min:0',
            'active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre del plan es requerido',
            'price.required' => 'El precio es requerido',
            'billing_days.required' => 'La duración del ciclo (días) es requerida',
        ];
    }
}
