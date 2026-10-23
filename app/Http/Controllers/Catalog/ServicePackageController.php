<?php

namespace App\Http\Controllers\Catalog;

use App\Http\Controllers\Controller;
use App\Models\ClientPackage;
use App\Models\Service;
use App\Models\ServicePackage;
use App\Services\PackageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Paquetes de sesiones prepagadas: catálogo y paquetes vendidos. */
class ServicePackageController extends Controller
{
    public function __construct(protected PackageService $packages)
    {
    }

    public function index()
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('catalog.packages.index', ['noCompany' => true]);
        }

        $list = ServicePackage::with('items.service')
            ->where('company_id', $companyId)
            ->orderBy('sort_order')->orderBy('name')
            ->get();

        $activeCounts = ClientPackage::where('company_id', $companyId)->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('expires_on')->orWhere('expires_on', '>=', today()))
            ->selectRaw('service_package_id, COUNT(*) AS n')->groupBy('service_package_id')
            ->pluck('n', 'service_package_id');

        return view('catalog.packages.index', ['noCompany' => false, 'packages' => $list, 'activeCounts' => $activeCounts]);
    }

    /** Paquetes vendidos, con lo que le queda a cada cliente. */
    public function sold(Request $request)
    {
        $companyId = $this->companyId();
        abort_unless($companyId, 403);
        $filter = $request->input('filter', 'active');
        $term = trim((string) $request->input('q'));

        $query = ClientPackage::with(['client', 'items.service', 'sale'])
            ->where('company_id', $companyId)
            ->when($term !== '', fn ($q) => $q->whereHas('client', fn ($c) => $c->where('full_name', 'like', "%{$term}%")->orWhere('phone', 'like', "%{$term}%")))
            ->latest('id');

        match ($filter) {
            'expired'  => $query->where('status', 'active')->whereNotNull('expires_on')->where('expires_on', '<', today()),
            'canceled' => $query->where('status', 'canceled'),
            'all'      => null,
            default    => $query->where('status', 'active')->where(fn ($q) => $q->whereNull('expires_on')->orWhere('expires_on', '>=', today())),
        };

        $sold = $query->paginate(20)->withQueryString();
        $summaries = $sold->getCollection()->mapWithKeys(fn ($p) => [$p->id => $this->packages->summary($p)]);

        return view('catalog.packages.sold', compact('sold', 'summaries', 'filter', 'term'));
    }

    public function cancel(Request $request, ClientPackage $clientPackage)
    {
        abort_if($clientPackage->company_id !== $this->companyId(), 403);
        $reason = $request->validate(['reason' => ['required', 'string', 'max:255']], ['reason.required' => 'Indica el motivo de la cancelación.'])['reason'];

        if ($clientPackage->status === 'canceled') {
            return back()->withErrors(['error' => 'El paquete ya está cancelado.']);
        }

        $this->packages->cancel($clientPackage, $reason);

        return back()->with('success', 'Paquete cancelado. No se reintegra dinero: para eso registra una devolución de la venta.');
    }

    public function create()
    {
        return view('catalog.packages.form', $this->formData(new ServicePackage(['active' => true, 'validity_days' => 180])));
    }

    public function store(Request $request)
    {
        $companyId = $this->companyId();
        abort_unless($companyId, 403);
        $data = $this->validatePackage($request, $companyId);

        DB::transaction(function () use ($data, $companyId) {
            $package = ServicePackage::create($data['package'] + ['company_id' => $companyId, 'created_by' => auth()->id()]);
            $package->items()->createMany($data['items']);
        });

        return redirect()->route('packages.index')->with('success', 'Paquete creado. Ya se puede vender desde el punto de venta.');
    }

    public function edit(ServicePackage $package)
    {
        $this->authorizePackage($package);

        return view('catalog.packages.form', $this->formData($package->load('items')));
    }

    public function update(Request $request, ServicePackage $package)
    {
        $this->authorizePackage($package);
        $data = $this->validatePackage($request, $package->company_id);

        DB::transaction(function () use ($package, $data) {
            $package->update($data['package']);
            $package->items()->delete();
            $package->items()->createMany($data['items']);
        });

        return redirect()->route('packages.index')->with('success', 'Paquete actualizado. Los ya vendidos conservan sus condiciones.');
    }

    public function destroy(ServicePackage $package)
    {
        $this->authorizePackage($package);
        $package->delete();

        return redirect()->route('packages.index')->with('success', 'Paquete eliminado. Los ya vendidos se siguen pudiendo usar.');
    }

    // ---- Helpers ----

    protected function validatePackage(Request $request, int $companyId): array
    {
        $v = $request->validate([
            'name'                => ['required', 'string', 'max:120'],
            'description'         => ['nullable', 'string', 'max:1000'],
            'price'               => ['required', 'numeric', 'min:0'],
            'validity_days'       => ['nullable', 'integer', 'min:1', 'max:3650'],
            'sort_order'          => ['nullable', 'integer', 'min:0', 'max:999'],
            'items'               => ['nullable', 'array'],
            'items.*.service_id'  => ['nullable', Rule::exists('services', 'id')->where('company_id', $companyId)],
            'items.*.quantity'    => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        $items = collect($v['items'] ?? [])
            ->filter(fn ($i) => !empty($i['service_id']) && !empty($i['quantity']))
            ->groupBy('service_id')
            ->map(fn ($rows, $serviceId) => ['service_id' => (int) $serviceId, 'quantity' => (int) $rows->sum('quantity')])
            ->values();

        if ($items->isEmpty()) {
            throw ValidationException::withMessages(['items' => 'Agrega al menos un servicio con sus sesiones.']);
        }

        return [
            'package' => [
                'name'          => $v['name'],
                'description'   => $v['description'] ?? null,
                'price'         => $v['price'],
                'validity_days' => $request->boolean('no_expiry') ? null : ($v['validity_days'] ?? null),
                'sort_order'    => (int) ($v['sort_order'] ?? 0),
                'active'        => $request->boolean('active'),
            ],
            'items' => $items->all(),
        ];
    }

    protected function formData(ServicePackage $package): array
    {
        return [
            'package'  => $package,
            'services' => Service::where('company_id', $this->companyId())->where('active', true)->orderBy('name')->get(['id', 'name', 'price']),
        ];
    }

    protected function authorizePackage(ServicePackage $package): void
    {
        abort_if($package->company_id !== $this->companyId(), 403);
    }

    protected function companyId(): ?int
    {
        return auth()->user()->getCurrentCompany()?->id;
    }
}
