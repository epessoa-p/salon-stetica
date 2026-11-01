<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Catálogo de servicios desde la app: ver, crear y editar (mismos permisos que la web). */
class CatalogController extends ApiController
{
    public function services(Request $request): JsonResponse
    {
        if ($r = $this->guard('services.view')) {
            return $r;
        }

        $companyId = $this->company()->id;
        $term = trim((string) $request->input('q'));

        $services = Service::with('category:id,name')->where('company_id', $companyId)
            ->when($term !== '', fn ($q) => $q->where('name', 'like', "%{$term}%"))
            ->orderByDesc('active')->orderBy('name')->get();

        return response()->json([
            'services'   => $services->map(fn ($s) => $this->row($s))->values(),
            'categories' => $this->categories($companyId),
            'can'        => [
                'create'          => $this->can('services.create'),
                'edit'            => $this->can('services.edit'),
                'create_category' => $this->can('service-categories.create'),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        if ($r = $this->guard('services.create')) {
            return $r;
        }

        $company = $this->company();
        if (!$company->canCreate('services')) {
            return $this->fail(403, 'plan_limit', 'Alcanzaste el límite de servicios de tu plan.');
        }

        $service = Service::create($this->validated($request) + [
            'company_id' => $company->id,
            'created_by' => auth()->id(),
        ]);

        return response()->json(['message' => 'Servicio creado.', 'service' => $this->row($service->load('category'))], 201);
    }

    public function update(Request $request, Service $service): JsonResponse
    {
        if ($r = $this->guard('services.edit')) {
            return $r;
        }
        if ($service->company_id !== $this->company()->id) {
            return $this->fail(404, 'not_found', 'Servicio no encontrado.');
        }

        $service->update($this->validated($request));

        return response()->json(['message' => 'Servicio actualizado.', 'service' => $this->row($service->fresh('category'))]);
    }

    /** Alta rápida de categoría desde el formulario de servicio. */
    public function storeCategory(Request $request): JsonResponse
    {
        if ($r = $this->guard('service-categories.create')) {
            return $r;
        }

        $companyId = $this->company()->id;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('service_categories', 'name')->where('company_id', $companyId)->whereNull('deleted_at')],
        ], ['name.required' => 'Escribe el nombre de la categoría.', 'name.unique' => 'Ya existe una categoría con ese nombre.']);

        $category = ServiceCategory::create([
            'company_id' => $companyId,
            'name'       => mb_strtoupper(trim($data['name'])),
            'active'     => true,
            'created_by' => auth()->id(),
        ]);

        return response()->json(['id' => $category->id, 'name' => $category->name], 201);
    }

    // ---- Helpers ----

    protected function guard(string $permission): ?JsonResponse
    {
        if ($r = $this->planMissing('catalog')) {
            return $r;
        }

        return $this->can($permission) ? null : $this->forbidden();
    }

    protected function validated(Request $request): array
    {
        $companyId = $this->company()->id;
        $data = $request->validate([
            'name'                => ['required', 'string', 'max:255'],
            'service_category_id' => ['nullable', Rule::exists('service_categories', 'id')->where('company_id', $companyId)->whereNull('deleted_at')],
            'price'               => ['required', 'numeric', 'min:0'],
            'duration_minutes'    => ['nullable', 'integer', 'min:5', 'max:1440'],
            'description'         => ['nullable', 'string', 'max:2000'],
            'active'              => ['nullable', 'boolean'],
        ], [
            'name.required'  => 'Escribe el nombre del servicio.',
            'price.required' => 'Indica el precio.',
            'service_category_id.exists' => 'La categoría no es de este salón.',
        ]);
        $data['name'] = mb_strtoupper(trim($data['name']));
        $data['active'] = $request->boolean('active', true);

        return $data;
    }

    protected function categories(int $companyId): array
    {
        return ServiceCategory::where('company_id', $companyId)->where('active', true)->orderBy('name')->get(['id', 'name'])
            ->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->all();
    }

    protected function row(Service $s): array
    {
        return [
            'id'          => $s->id,
            'name'        => $s->name,
            'price'       => (float) $s->price,
            'duration'    => $s->duration_minutes,
            'description' => $s->description,
            'active'      => (bool) $s->active,
            'category'    => $s->category ? ['id' => $s->category->id, 'name' => $s->category->name] : null,
        ];
    }
}
