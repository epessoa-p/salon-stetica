<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()->is_super_admin;
    }

    public function rules(): array
    {
        // En edición se ignora la propia empresa para no marcar el RUC como repetido.
        $companyId = $this->route('company')?->id;

        return [
            'name' => 'required|string|max:255',
            'ruc' => [
                'nullable', 'string', 'max:20',
                Rule::unique('companies', 'ruc')->ignore($companyId),
            ],
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'description' => 'nullable|string',
            'primary_color' => ['nullable', 'regex:/^#([0-9A-Fa-f]{6})$/'],
            'secondary_color' => ['nullable', 'regex:/^#([0-9A-Fa-f]{6})$/'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'plan_id' => ['nullable', 'exists:plans,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'El nombre de la empresa es requerido',
            'ruc.unique' => 'Este RUC ya está registrado',
        ];
    }
}
