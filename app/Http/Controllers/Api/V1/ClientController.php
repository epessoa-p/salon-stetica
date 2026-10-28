<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Appointment;
use App\Models\Client;
use App\Models\ClientPhoto;
use App\Models\CompanySetting;
use App\Models\Sale;
use App\Services\CreditService;
use App\Services\LoyaltyService;
use App\Services\MembershipService;
use App\Services\PackageService;
use App\Services\PhotoService;
use App\Services\ReceivableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Búsqueda y alta rápida de clientes desde la app. */
class ClientController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        if ($r = $this->planMissing('crm')) {
            return $r;
        }
        if (!$this->can('clients.view')) {
            return $this->forbidden('No tienes permiso para ver clientes.');
        }

        $term = trim((string) $request->input('q'));

        $clients = Client::where('company_id', $this->company()->id)
            ->where('active', true)
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('full_name', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")
                ->orWhere('id_number', 'like', "%{$term}%")))
            ->orderBy('full_name')
            ->limit(25)
            ->get();

        return response()->json($clients->map(fn ($c) => self::client($c))->values());
    }

    public function store(Request $request): JsonResponse
    {
        if ($r = $this->planMissing('crm')) {
            return $r;
        }
        if (!$this->can('clients.create')) {
            return $this->forbidden('No tienes permiso para registrar clientes.');
        }

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'phone'     => ['nullable', 'string', 'max:30'],
            'id_number' => ['nullable', 'string', 'max:50'],
            'email'     => ['nullable', 'email', 'max:255'],
        ], ['full_name.required' => 'Escribe el nombre del cliente.']);

        // Mismo criterio que la web: los nombres se guardan en mayúsculas.
        $data['full_name'] = mb_strtoupper($data['full_name']);

        $client = Client::create($data + [
            'company_id' => $this->company()->id,
            'active'     => true,
            'source'     => 'App móvil',
            'created_by' => auth()->id(),
        ]);

        return response()->json(self::client($client), 201);
    }

    /**
     * Ficha del cliente: datos, indicadores, últimas citas y compras,
     * membresía, paquetes, puntos, saldo a favor y cuenta corriente. Cada
     * bloque sale solo si el plan lo incluye (y las compras, con permiso).
     */
    public function show(Client $client): JsonResponse
    {
        if ($r = $this->planMissing('crm')) {
            return $r;
        }
        if (!$this->can('clients.view')) {
            return $this->forbidden('No tienes permiso para ver clientes.');
        }
        $company = $this->company();
        if ($client->company_id !== $company->id) {
            return $this->fail(404, 'not_found', 'Cliente no encontrado.');
        }

        $setting = CompanySetting::current($company->id);
        $appointments = $company->planAllows('scheduling')
            ? Appointment::with(['service', 'specialist'])->where('client_id', $client->id)->orderByDesc('starts_at')->limit(10)->get()
            : collect();
        $showSales = $company->planAllows('sales') && $this->can('sales.view');
        $sales = $showSales ? Sale::where('client_id', $client->id)->orderByDesc('sale_date')->limit(10)->get() : collect();
        $paid = $showSales ? Sale::where('client_id', $client->id)->where('status', '!=', 'anulada') : null;

        $membership = $company->planAllows('loyalty') ? app(MembershipService::class)->activeFor($company->id, $client->id) : null;
        $loyalty = app(LoyaltyService::class);
        $packages = app(PackageService::class);

        return response()->json([
            'client'   => self::client($client) + ['birthdate' => $client->birthdate?->toDateString(), 'notes' => $client->notes],
            'currency' => $setting->currency_symbol,
            'stats'    => [
                'visits'     => Appointment::where('client_id', $client->id)->where('status', 'completada')->count(),
                'last_visit' => Appointment::where('client_id', $client->id)->where('status', 'completada')->max('starts_at'),
                'purchases'  => $paid ? (clone $paid)->count() : null,
                'spent'      => $paid ? round((float) (clone $paid)->sum(DB::raw('total - returned_total')), 2) : null,
            ],
            'appointments' => $appointments->map(fn ($a) => [
                'id'           => $a->id,
                'starts_at'    => $a->starts_at->toIso8601String(),
                'status_label' => $a->statusLabel(),
                'status_color' => $a->statusColor(),
                'service'      => $a->service?->name,
                'personal'     => $a->specialist?->full_name,
            ])->values(),
            'sales' => $showSales ? $sales->map(fn ($s) => [
                'id'     => $s->id,
                'number' => $s->sale_number,
                'date'   => $s->sale_date?->toIso8601String(),
                'total'  => (float) $s->total,
                'status' => $s->status,
            ])->values() : null,
            'membership' => $membership ? app(MembershipService::class)->summary($membership) : null,
            'packages'   => $company->planAllows('catalog')
                ? $packages->usableFor($company->id, $client->id)->map(fn ($p) => $packages->summary($p))->values() : [],
            'points'     => $loyalty->enabled($company) ? $loyalty->summary($company->id, $client->id) : null,
            'credit'     => $showSales ? app(CreditService::class)->balance($company->id, $client->id) : null,
            'account'    => $company->planAllows('finance') && $this->can('receivables.view')
                ? app(ReceivableService::class)->summary($client) : null,
        ]);
    }

    /** Edición de los datos de contacto del cliente desde la app. */
    public function update(Request $request, Client $client): JsonResponse
    {
        if ($r = $this->planMissing('crm')) {
            return $r;
        }
        if (!$this->can('clients.edit')) {
            return $this->forbidden('No tienes permiso para editar clientes.');
        }
        if ($client->company_id !== $this->company()->id) {
            return $this->fail(404, 'not_found', 'Cliente no encontrado.');
        }

        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'phone'     => ['nullable', 'string', 'max:30'],
            'id_number' => ['nullable', 'string', 'max:50'],
            'email'     => ['nullable', 'email', 'max:255'],
            'birthdate' => ['nullable', 'date', 'before:today'],
            'notes'     => ['nullable', 'string', 'max:2000'],
        ], [
            'full_name.required' => 'Escribe el nombre del cliente.',
            'email.email'        => 'El correo no es válido.',
            'birthdate.before'   => 'La fecha de nacimiento debe ser anterior a hoy.',
        ]);

        // Mismo criterio que la web: los nombres se guardan en mayúsculas.
        $data['full_name'] = mb_strtoupper($data['full_name']);
        $client->update($data);

        return response()->json(self::client($client->fresh()) + ['birthdate' => $client->birthdate?->toDateString(), 'notes' => $client->notes]);
    }

    /** Fotos antes/después del cliente. Las imágenes se piden con el mismo token. */
    public function photos(Client $client): JsonResponse
    {
        if ($r = $this->photoGuard($client, 'photos.view')) {
            return $r;
        }

        return response()->json(ClientPhoto::with('service')->where('client_id', $client->id)
            ->orderByDesc('taken_on')->orderByDesc('id')->get()->map(fn ($p) => self::photo($p))->values());
    }

    public function storePhoto(Request $request, Client $client): JsonResponse
    {
        if ($r = $this->photoGuard($client, 'photos.create')) {
            return $r;
        }

        $data = $request->validate([
            'photo'    => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'kind'     => ['nullable', 'in:before,after,other'],
            'taken_on' => ['nullable', 'date', 'before_or_equal:today'],
            'notes'    => ['nullable', 'string', 'max:255'],
        ], ['photo.required' => 'Elige una foto.', 'photo.max' => 'La foto puede pesar hasta 10 MB.', 'photo.image' => 'El archivo debe ser una imagen (JPG, PNG o WEBP).', 'photo.mimes' => 'El archivo debe ser una imagen (JPG, PNG o WEBP).']);

        try {
            $photo = app(PhotoService::class)->store($client, $request->file('photo'), $data + ['kind' => $data['kind'] ?? 'other']);
        } catch (\DomainException $e) {
            return $this->fail(422, 'invalid_image', $e->getMessage());
        }

        return response()->json(self::photo($photo->load('service')), 201);
    }

    public function photoImage(ClientPhoto $photo, string $size = 'full')
    {
        if ($photo->company_id !== $this->company()->id) {
            return $this->fail(404, 'not_found', 'Foto no encontrada.');
        }
        if (!$this->can('photos.view')) {
            return $this->forbidden();
        }
        $path = $size === 'thumb' ? ($photo->thumb_path ?: $photo->path) : $photo->path;
        if (!Storage::disk('local')->exists($path)) {
            return $this->fail(404, 'not_found', 'El archivo de la foto no existe.');
        }

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, max-age=86400']);
    }

    public function destroyPhoto(ClientPhoto $photo): JsonResponse
    {
        if ($photo->company_id !== $this->company()->id) {
            return $this->fail(404, 'not_found', 'Foto no encontrada.');
        }
        if (!$this->can('photos.delete')) {
            return $this->forbidden('No tienes permiso para eliminar fotos.');
        }
        app(PhotoService::class)->delete($photo);

        return response()->json(['message' => 'Foto eliminada.']);
    }

    protected function photoGuard(Client $client, string $permission): ?JsonResponse
    {
        if ($r = $this->planMissing('crm')) {
            return $r;
        }
        if ($client->company_id !== $this->company()->id) {
            return $this->fail(404, 'not_found', 'Cliente no encontrado.');
        }

        return $this->can($permission) ? null : $this->forbidden('No tienes permiso para las fotos de clientes.');
    }

    public static function photo(ClientPhoto $p): array
    {
        return [
            'id'        => $p->id,
            'kind'      => $p->kind,
            'kind_label' => $p->kindLabel(),
            'taken_on'  => $p->taken_on->toDateString(),
            'notes'     => $p->notes,
            'service'   => $p->service?->name,
            'url'       => route('api.photos.image', $p),
            'thumb_url' => route('api.photos.image', [$p, 'thumb']),
        ];
    }

    public static function client(Client $c): array
    {
        return ['id' => $c->id, 'name' => $c->full_name, 'phone' => $c->phone, 'document' => $c->id_number, 'email' => $c->email];
    }
}
