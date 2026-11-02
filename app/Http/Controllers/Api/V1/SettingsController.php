<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Branch;
use App\Models\PaymentMethod;
use App\Models\Personal;
use App\Models\StaffSchedule;
use App\Models\StaffScheduleException;
use App\Models\Warehouse;
use App\Services\ScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Configuración desde la app: Mi empresa, Sucursales, Horarios del personal y
 * Métodos de pago. Mismos permisos (de base de datos) y reglas que la web.
 */
class SettingsController extends ApiController
{
    public function __construct(protected ScheduleService $schedules)
    {
    }

    // ---- Mi empresa ----

    public function showCompany(): JsonResponse
    {
        if (!$this->can('my-company.view')) {
            return $this->forbidden();
        }

        return response()->json($this->companyRow() + ['can' => ['edit' => $this->can('my-company.edit')]]);
    }

    public function updateCompany(Request $request): JsonResponse
    {
        if (!$this->can('my-company.edit')) {
            return $this->forbidden();
        }

        $company = $this->company();
        $data = $request->validate([
            'name'            => ['required', 'string', 'max:255'],
            'ruc'             => ['nullable', 'string', 'max:20', Rule::unique('companies', 'ruc')->ignore($company->id)],
            'address'         => ['nullable', 'string', 'max:255'],
            'phone'           => ['nullable', 'string', 'max:20'],
            'email'           => ['nullable', 'email', 'max:255'],
            'description'     => ['nullable', 'string'],
            'primary_color'   => ['nullable', 'regex:/^#([0-9A-Fa-f]{6})$/'],
            'secondary_color' => ['nullable', 'regex:/^#([0-9A-Fa-f]{6})$/'],
        ], [
            'name.required'  => 'Escribe el nombre de la empresa.',
            'ruc.unique'     => 'Este RUC ya está registrado en otra empresa.',
            'email.email'    => 'El correo no es válido.',
            '*.regex'        => 'El color debe tener el formato #RRGGBB.',
        ]);

        $company->update($data);

        return response()->json(['message' => 'Datos de la empresa actualizados.'] + $this->companyRow());
    }

    public function logo(Request $request): JsonResponse
    {
        if (!$this->can('my-company.edit')) {
            return $this->forbidden();
        }

        $request->validate([
            'logo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ], ['logo.required' => 'Elige una imagen.', 'logo.image' => 'El archivo debe ser una imagen (JPG, PNG o WEBP).', 'logo.mimes' => 'El archivo debe ser una imagen (JPG, PNG o WEBP).', 'logo.max' => 'El logo puede pesar hasta 2 MB.']);

        $company = $this->company();
        if ($company->logo) {
            Storage::disk('public')->delete($company->logo);
        }
        $company->update(['logo' => $request->file('logo')->store('logos', 'public')]);

        return response()->json(['message' => 'Logo actualizado.'] + $this->companyRow());
    }

    // ---- Sucursales ----

    public function branches(): JsonResponse
    {
        if (!$this->can('branches.view')) {
            return $this->forbidden();
        }

        $company = $this->company();
        $branches = Branch::with('warehouse:id,name')->where('company_id', $company->id)->orderByDesc('active')->orderBy('name')->get();

        return response()->json([
            'branches'   => $branches->map(fn ($b) => $this->branchRow($b))->values(),
            'warehouses' => Warehouse::where('company_id', $company->id)->orderBy('name')->get(['id', 'name'])->map(fn ($w) => ['id' => $w->id, 'name' => $w->name])->values(),
            'can'        => [
                'create'    => $this->can('branches.create'),
                'edit'      => $this->can('branches.edit'),
                // Cupo del plan: se avisa antes de abrir el formulario.
                'plan_room' => $company->canCreate('branches'),
            ],
        ]);
    }

    public function storeBranch(Request $request): JsonResponse
    {
        if (!$this->can('branches.create')) {
            return $this->forbidden();
        }

        $company = $this->company();
        if (!$company->canCreate('branches')) {
            return $this->fail(403, 'plan_limit', 'Alcanzaste el límite de sucursales de tu plan. Actualiza tu plan para agregar más.');
        }

        $data = $this->branchData($request);
        $branch = Branch::create($data + [
            'company_id'   => $company->id,
            'warehouse_id' => $this->warehouseFor($data),
            'active'       => $request->boolean('active', true),
        ]);

        return response()->json(['message' => 'Sucursal creada.', 'branch' => $this->branchRow($branch->load('warehouse'))], 201);
    }

    public function updateBranch(Request $request, Branch $branch): JsonResponse
    {
        if (!$this->can('branches.edit')) {
            return $this->forbidden();
        }
        if ((int) $branch->company_id !== $this->company()->id) {
            return $this->fail(404, 'not_found', 'Sucursal no encontrada.');
        }

        $data = $this->branchData($request, $branch->id);
        $branch->update($data + [
            'warehouse_id' => $this->warehouseFor($data),
            'active'       => $request->boolean('active', true),
        ]);

        return response()->json(['message' => 'Sucursal actualizada.', 'branch' => $this->branchRow($branch->fresh('warehouse'))]);
    }

    // ---- Horarios del personal ----

    public function schedules(): JsonResponse
    {
        if (!$this->can('schedules.view')) {
            return $this->forbidden();
        }

        $people = Personal::with(['schedules', 'cargo'])
            ->where('company_id', $this->company()->id)->where('active', true)
            ->orderBy('full_name')->get();

        return response()->json([
            'people' => $people->map(function ($p) {
                $today = $this->schedules->dayFor($p->id, today());

                return [
                    'id'         => $p->id,
                    'name'       => $p->full_name,
                    'cargo'      => $p->cargo?->name,
                    'configured' => $p->schedules->isNotEmpty(),
                    // Días que trabaja (1 = lunes … 7 = domingo).
                    'weekdays'   => $p->schedules->pluck('weekday')->unique()->sort()->values(),
                    'today'      => ['off' => $today['off'], 'blocks' => $today['blocks']],
                ];
            })->values(),
            'can' => ['edit' => $this->can('schedules.edit')],
        ]);
    }

    public function schedule(Personal $personal): JsonResponse
    {
        if (!$this->can('schedules.view')) {
            return $this->forbidden();
        }
        if ((int) $personal->company_id !== $this->company()->id) {
            return $this->fail(404, 'not_found', 'Personal no encontrado.');
        }

        return response()->json($this->scheduleRow($personal) + ['can' => ['edit' => $this->can('schedules.edit')]]);
    }

    public function updateSchedule(Request $request, Personal $personal): JsonResponse
    {
        if (!$this->can('schedules.edit')) {
            return $this->forbidden();
        }
        if ((int) $personal->company_id !== $this->company()->id) {
            return $this->fail(404, 'not_found', 'Personal no encontrado.');
        }

        $request->validate([
            'days'                  => ['nullable', 'array'],
            'days.*.on'             => ['nullable', 'boolean'],
            'days.*.blocks'         => ['nullable', 'array'],
            'days.*.blocks.*.start' => ['nullable', 'date_format:H:i'],
            'days.*.blocks.*.end'   => ['nullable', 'date_format:H:i'],
        ], ['*.date_format' => 'La hora debe tener el formato HH:MM.']);

        $rows = $this->schedules->replaceWeekly($personal, (array) $request->input('days', []));

        return response()->json([
            'message' => $rows ? 'Horario guardado.' : 'Horario vaciado: la agenda no aplicará restricciones de horario a esta persona.',
        ] + $this->scheduleRow($personal));
    }

    // ---- Métodos de pago ----

    public function paymentMethods(): JsonResponse
    {
        if (!$this->can('payment-methods.view')) {
            return $this->forbidden();
        }

        $methods = PaymentMethod::where('company_id', $this->company()->id)->orderBy('sort_order')->orderBy('name')->get();

        return response()->json([
            'methods' => $methods->map(fn ($m) => $this->methodRow($m))->values(),
            'can'     => ['create' => $this->can('payment-methods.create'), 'edit' => $this->can('payment-methods.edit')],
        ]);
    }

    public function storePaymentMethod(Request $request): JsonResponse
    {
        if (!$this->can('payment-methods.create')) {
            return $this->forbidden();
        }

        $companyId = $this->company()->id;
        $data = $this->methodData($request, $companyId);
        $method = DB::transaction(function () use ($data, $companyId, $request) {
            $method = PaymentMethod::create($data + [
                'company_id' => $companyId,
                'active'     => $request->boolean('active', true),
                'created_by' => auth()->id(),
            ]);
            $this->syncDefault($method);

            return $method;
        });

        return response()->json(['message' => 'Método de pago creado.', 'method' => $this->methodRow($method)], 201);
    }

    public function updatePaymentMethod(Request $request, PaymentMethod $paymentMethod): JsonResponse
    {
        if (!$this->can('payment-methods.edit')) {
            return $this->forbidden();
        }
        if ((int) $paymentMethod->company_id !== $this->company()->id) {
            return $this->fail(404, 'not_found', 'Método de pago no encontrado.');
        }

        $data = $this->methodData($request, $paymentMethod->company_id, $paymentMethod->id);
        DB::transaction(function () use ($data, $request, $paymentMethod) {
            $paymentMethod->update($data + ['active' => $request->boolean('active', true)]);
            $this->syncDefault($paymentMethod);
        });

        return response()->json(['message' => 'Método de pago actualizado.', 'method' => $this->methodRow($paymentMethod->fresh())]);
    }

    // ---- Helpers ----

    protected function companyRow(): array
    {
        $c = $this->company()->fresh();

        return [
            'name'            => $c->name,
            'ruc'             => $c->ruc,
            'address'         => $c->address,
            'phone'           => $c->phone,
            'email'           => $c->email,
            'description'     => $c->description,
            'primary_color'   => $c->primary_color,
            'secondary_color' => $c->secondary_color,
            'logo_url'        => $c->logo ? asset('storage/' . $c->logo) : null,
        ];
    }

    protected function branchData(Request $request, ?int $ignoreId = null): array
    {
        $companyId = $this->company()->id;

        return $request->validate([
            'name'         => ['required', 'string', 'max:255'],
            'code'         => ['nullable', 'string', 'max:50', Rule::unique('branches', 'code')->ignore($ignoreId)],
            'phone'        => ['nullable', 'string', 'max:20'],
            'email'        => ['nullable', 'email', 'max:255'],
            'address'      => ['nullable', 'string', 'max:255'],
            'manager_name' => ['nullable', 'string', 'max:255'],
            'warehouse_id' => ['nullable', Rule::exists('warehouses', 'id')->where('company_id', $companyId)],
        ], [
            'name.required'       => 'Escribe el nombre de la sucursal.',
            'code.unique'         => 'Ese código ya está en uso.',
            'email.email'         => 'El correo no es válido.',
            'warehouse_id.exists' => 'El almacén no es de este salón.',
        ]);
    }

    /** El almacén elegido o uno nuevo para la sucursal (igual que la web). */
    protected function warehouseFor(array $data): int
    {
        return !empty($data['warehouse_id'])
            ? (int) $data['warehouse_id']
            : Warehouse::createForBranch($this->company()->id, $data['name'], $data['address'] ?? null)->id;
    }

    protected function branchRow(Branch $b): array
    {
        return [
            'id'           => $b->id,
            'name'         => $b->name,
            'code'         => $b->code,
            'phone'        => $b->phone,
            'email'        => $b->email,
            'address'      => $b->address,
            'manager_name' => $b->manager_name,
            'active'       => (bool) $b->active,
            'warehouse'    => $b->warehouse ? ['id' => $b->warehouse->id, 'name' => $b->warehouse->name] : null,
        ];
    }

    protected function scheduleRow(Personal $personal): array
    {
        $weekly = StaffSchedule::where('personal_id', $personal->id)->orderBy('starts_time')->get()->groupBy('weekday');
        $exceptions = StaffScheduleException::where('personal_id', $personal->id)
            ->where('date_to', '>=', today()->toDateString())
            ->orderBy('date_from')->limit(10)->get();

        return [
            'id'   => $personal->id,
            'name' => $personal->full_name,
            'days' => collect(StaffSchedule::WEEKDAYS)->map(fn ($label, $weekday) => [
                'weekday' => $weekday,
                'label'   => $label,
                'on'      => $weekly->has($weekday),
                'blocks'  => ($weekly[$weekday] ?? collect())->map(fn ($s) => [
                    'start' => substr((string) $s->starts_time, 0, 5),
                    'end'   => substr((string) $s->ends_time, 0, 5),
                ])->values(),
            ])->values(),
            // Libres, vacaciones y horarios especiales próximos (se gestionan en la web).
            'exceptions' => $exceptions->map(fn ($e) => [
                'type'   => StaffScheduleException::TYPES[$e->type] ?? $e->type,
                'from'   => $e->date_from->toDateString(),
                'to'     => $e->date_to->toDateString(),
                'start'  => $e->starts_time ? substr((string) $e->starts_time, 0, 5) : null,
                'end'    => $e->ends_time ? substr((string) $e->ends_time, 0, 5) : null,
                'reason' => $e->reason,
            ])->values(),
        ];
    }

    protected function methodData(Request $request, int $companyId, ?int $ignoreId = null): array
    {
        $request->merge(['code' => Str::slug((string) ($request->input('code') ?: $request->input('name')), '_')]);

        $data = $request->validate([
            'name'         => ['required', 'string', 'max:80'],
            'code'         => [
                'required', 'string', 'max:30',
                Rule::unique('payment_methods', 'code')
                    ->where(fn ($q) => $q->where('company_id', $companyId)->whereNull('deleted_at'))
                    ->ignore($ignoreId),
            ],
            'icon'         => ['nullable', 'string', 'max:40'],
            'sort_order'   => ['nullable', 'integer', 'min:0', 'max:999'],
            'affects_cash' => ['nullable', 'boolean'],
            'is_default'   => ['nullable', 'boolean'],
        ], [
            'name.required' => 'Escribe el nombre del método.',
            'code.required' => 'Escribe el nombre del método.',
            'code.unique'   => 'Ya existe un método con ese nombre o código.',
        ]);

        return [
            'name'         => $data['name'],
            'code'         => $data['code'],
            'icon'         => $data['icon'] ?? null,
            'sort_order'   => (int) ($data['sort_order'] ?? 0),
            'affects_cash' => $request->boolean('affects_cash'),
            'is_default'   => $request->boolean('is_default'),
        ];
    }

    /** Solo un método puede quedar como predeterminado por empresa. */
    protected function syncDefault(PaymentMethod $method): void
    {
        if (!$method->is_default) {
            return;
        }

        PaymentMethod::withoutGlobalScopes()
            ->where('company_id', $method->company_id)
            ->where('id', '!=', $method->id)
            ->update(['is_default' => false]);
    }

    protected function methodRow(PaymentMethod $m): array
    {
        return [
            'id'           => $m->id,
            'name'         => $m->name,
            'code'         => $m->code,
            'icon'         => $m->icon,
            'affects_cash' => (bool) $m->affects_cash,
            'is_default'   => (bool) $m->is_default,
            'active'       => (bool) $m->active,
            'sort_order'   => (int) $m->sort_order,
        ];
    }
}
