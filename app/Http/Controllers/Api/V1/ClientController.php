<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

    public static function client(Client $c): array
    {
        return ['id' => $c->id, 'name' => $c->full_name, 'phone' => $c->phone, 'document' => $c->id_number, 'email' => $c->email];
    }
}
