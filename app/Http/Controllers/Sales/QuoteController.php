<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Combo;
use App\Models\CompanySetting;
use App\Models\MembershipPlan;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Service;
use App\Models\ServicePackage;
use App\Services\CheckoutService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Cotizaciones. Se arman en el punto de venta (mismo catálogo y mismo
 * cálculo) y se convierten en venta desde allí: vigentes con sus precios,
 * vencidas con los precios actuales. No reservan stock.
 */
class QuoteController extends Controller
{
    /** Errores del carrito que no impiden cotizar (solo aplican al cobrar). */
    protected const IGNORED_ERRORS = '/saldo a favor|canje|puntos|propina|elegir el cliente/i';

    public function __construct(protected CheckoutService $checkout)
    {
    }

    public function index(Request $request)
    {
        $companyId = $this->companyId();
        if (!$companyId) {
            return view('sales.quotes.index', ['noCompany' => true]);
        }

        $filter = $request->input('filter', 'pending');
        $term = trim((string) $request->input('q'));
        $today = today()->toDateString();

        $quotes = Quote::with(['client', 'sale'])
            ->where('company_id', $companyId)
            ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('quote_number', 'like', "%{$term}%")
                ->orWhere('client_name', 'like', "%{$term}%")
                ->orWhereHas('client', fn ($c) => $c->where('full_name', 'like', "%{$term}%"))))
            ->when($filter === 'pending', fn ($q) => $q->where('status', 'pendiente')->where('valid_until', '>=', $today))
            ->when($filter === 'expired', fn ($q) => $q->where('status', 'pendiente')->where('valid_until', '<', $today))
            ->when($filter === 'converted', fn ($q) => $q->where('status', 'convertida'))
            ->when($filter === 'void', fn ($q) => $q->where('status', 'anulada'))
            ->latest('id')
            ->paginate(20)->withQueryString();

        // Tasa de conversión de los últimos 90 días: cuántas cotizaciones terminaron en venta.
        $recent = Quote::where('company_id', $companyId)->where('created_at', '>=', now()->subDays(90))->where('status', '!=', 'anulada');
        $stats = [
            'pending'   => Quote::where('company_id', $companyId)->where('status', 'pendiente')->where('valid_until', '>=', $today)->count(),
            'pending_amount' => (float) Quote::where('company_id', $companyId)->where('status', 'pendiente')->where('valid_until', '>=', $today)->sum('total'),
            'total90'   => (clone $recent)->count(),
            'converted90' => (clone $recent)->where('status', 'convertida')->count(),
        ];

        return view('sales.quotes.index', ['noCompany' => false] + compact('quotes', 'filter', 'term', 'stats'));
    }

    /** Guarda el carrito del POS como cotización (JSON). */
    public function store(Request $request): JsonResponse
    {
        $company = auth()->user()->getCurrentCompany();
        abort_unless($company, 403);

        $data = $request->validate(array_merge(CheckoutService::rules($company->id, forStore: false), [
            'items' => ['required', 'array', 'min:1'],
        ]), ['items.required' => 'Agrega al menos una línea para cotizar.', 'items.min' => 'Agrega al menos una línea para cotizar.']);
        // Puntos, saldo y propina se deciden al cobrar.
        $data = array_merge($data, ['redeem_points' => 0, 'credit_amount' => 0, 'tip' => 0, 'on_credit' => false, 'coupon_code' => null]);

        $quote = $this->checkout->quote($data, $company);
        $errors = array_values(array_filter($quote['errors'], fn ($e) => !preg_match(self::IGNORED_ERRORS, $e)));
        if ($errors) {
            return response()->json(['message' => $errors[0], 'errors' => ['items' => $errors]], 422);
        }

        $setting = CompanySetting::current($company->id);

        $record = DB::transaction(function () use ($data, $quote, $company, $setting) {
            $count = Quote::withTrashed()->where('company_id', $company->id)->count() + 1;

            $record = Quote::create([
                'company_id'      => $company->id,
                'branch_id'       => $data['branch_id'] ?? null,
                'client_id'       => $data['client_id'] ?? null,
                'client_name'     => empty($data['client_id']) ? ($data['client_name'] ?? null) : null,
                'personal_id'     => $data['personal_id'] ?? null,
                'quote_number'    => $setting->documentFolio('COT-', $count),
                'valid_until'     => today()->addDays(max(1, $setting->quote_valid_days) - 1)->toDateString(),
                'status'          => 'pendiente',
                'subtotal'        => $quote['subtotal'],
                'discount'        => round($quote['line_discounts'] + $quote['membership_discount'] + $quote['package_discount'], 2),
                'global_discount' => $quote['global_discount'],
                'tax'             => $quote['tax'],
                'total'           => $quote['total'],
                'notes'           => $data['notes'] ?? null,
                'created_by'      => auth()->id(),
            ]);

            foreach ($data['items'] as $i => $item) {
                $line = $quote['lines'][$i];
                $record->items()->create([
                    'kind'               => $line['kind'],
                    'service_id'         => $line['service_id'],
                    'product_id'         => $line['product_id'],
                    'combo_id'           => $line['combo_id'],
                    'service_package_id' => $line['service_package_id'],
                    'membership_plan_id' => $line['membership_plan_id'],
                    'personal_id'        => $line['personal_id'],
                    'description'        => $item['description'] ?? $this->name($line),
                    'quantity'           => $line['quantity'],
                    'unit_price'         => $line['unit_price'],
                    'discount'           => $line['discount'],
                    'total'              => $line['total'],
                ]);
            }

            return $record;
        });

        session()->flash('success', 'Cotización ' . $record->quote_number . ' guardada. Vigente hasta el ' . $record->valid_until->format('d/m/Y') . '.');

        return response()->json(['id' => $record->id, 'url' => route('quotes.show', $record)], 201);
    }

    public function show(Quote $quote)
    {
        $this->authorizeQuote($quote);
        $quote->load(['items.specialist', 'client', 'specialist', 'sale', 'creator']);

        return view('sales.quotes.show', [
            'quote'    => $quote,
            'setting'  => CompanySetting::current($quote->company_id),
            'company'  => $quote->company,
            'whatsapp' => $this->whatsappLink($quote),
        ]);
    }

    public function void(Quote $quote)
    {
        $this->authorizeQuote($quote);
        if (!$quote->canConvert()) {
            return back()->withErrors(['error' => 'Solo se anulan cotizaciones que no se convirtieron en venta.']);
        }

        $quote->update(['status' => 'anulada']);

        return back()->with('success', 'Cotización ' . $quote->quote_number . ' anulada.');
    }

    // ---- Helpers ----

    /** Enlace para enviar la cotización por WhatsApp al teléfono del cliente. */
    protected function whatsappLink(Quote $quote): ?string
    {
        $setting = CompanySetting::current($quote->company_id);
        $lines = $quote->items->map(fn ($i) => '• ' . rtrim(rtrim(number_format((float) $i->quantity, 2), '0'), '.') . ' × ' . $i->description . ': ' . $setting->money($i->total));

        $text = "Hola {$quote->clientDisplay()}, te enviamos la cotización {$quote->quote_number}:\n"
            . $lines->implode("\n")
            . "\nTotal: " . $setting->money($quote->total)
            . "\nVálida hasta el " . $quote->valid_until->format('d/m/Y') . '.';

        $phone = preg_replace('/\D+/', '', (string) $quote->client?->phone);
        // Números locales de Bolivia (8 dígitos): se antepone el código de país.
        if (strlen($phone) === 8) {
            $phone = '591' . $phone;
        }

        return 'https://wa.me/' . $phone . '?text=' . rawurlencode($text);
    }

    protected function name(array $line): ?string
    {
        return match ($line['kind']) {
            'service'    => Service::find($line['service_id'])?->name,
            'product'    => Product::find($line['product_id'])?->name,
            'combo'      => 'Combo ' . Combo::find($line['combo_id'])?->name,
            'package'    => 'Paquete ' . ServicePackage::find($line['service_package_id'])?->name,
            'membership' => 'Membresía ' . MembershipPlan::find($line['membership_plan_id'])?->name,
            default      => null,
        };
    }

    protected function authorizeQuote(Quote $quote): void
    {
        abort_if($quote->company_id !== $this->companyId(), 403);
    }

    protected function companyId(): ?int
    {
        return auth()->user()->getCurrentCompany()?->id;
    }
}
