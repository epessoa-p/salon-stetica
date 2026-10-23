<?php

namespace App\Services;

use App\Models\CashSession;
use App\Models\CompanySetting;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Devoluciones de clientes sobre una venta, parciales por línea y cantidad.
 *
 * Reglas de Glamora:
 *  - Lo máximo a reintegrar por línea es lo que se pagó por esa cantidad: el
 *    total de la línea más su parte del impuesto y menos su parte de los
 *    descuentos globales y puntos (todo se reparte en proporción). La cajera
 *    puede reintegrar menos (ej. un servicio que se rehízo a medias).
 *  - El reintegro es en dinero (egreso de caja) o como saldo a favor. Si la
 *    venta se pagó en parte con saldo, esa parte solo vuelve como saldo.
 *  - Los productos vuelven al stock si se marca; las sesiones de membresía o
 *    paquete usadas vuelven solas; la membresía o el paquete vendidos en la
 *    línea se cancelan.
 *  - Comisiones: un negativo pendiente proporcional a lo devuelto. Puntos: se
 *    quita la parte proporcional de lo ganado y se devuelve la de lo canjeado.
 *  - La propina no se devuelve: es del profesional.
 *  - Una venta con devoluciones ya no se anula (se devuelve el resto).
 */
class SaleReturnService
{
    public function __construct(
        protected StockService $stock,
        protected CommissionService $commissions,
        protected LoyaltyService $loyalty,
        protected MembershipService $memberships,
        protected PackageService $packages,
        protected CreditService $credit,
    ) {
    }

    /** Factor para pasar del total de las líneas a lo realmente cobrado (impuesto, descuentos globales, puntos). */
    public function factor(Sale $sale): float
    {
        $lines = (float) $sale->items->sum('total');

        return $lines > 0 ? (float) $sale->total / $lines : 0.0;
    }

    /** Máximo a reintegrar por una cantidad de una línea. */
    public function maxRefund(SaleItem $item, float $quantity, float $factor): float
    {
        if ((float) $item->quantity <= 0) {
            return 0.0;
        }

        return round((float) $item->total / (float) $item->quantity * $quantity * $factor, 2);
    }

    /** Cuánto de la venta se puede devolver todavía en dinero (el resto, solo como saldo). */
    public function cashRefundable(Sale $sale): float
    {
        $cashRefunded = (float) $sale->returns()->where('refund_method', 'cash')->sum('total');

        return max(0, round($sale->paidInMoney() - $cashRefunded, 2));
    }

    /**
     * Registra la devolución. $data: reason, notes, refund_method (cash|credit),
     * payment_method, cash_session_id e items [{sale_detail_id, quantity, amount?, restock?}].
     *
     * @throws DomainException con un mensaje para mostrar
     */
    public function create(Sale $sale, array $data): SaleReturn
    {
        return DB::transaction(function () use ($sale, $data) {
            $sale = Sale::withoutGlobalScopes()->whereKey($sale->id)->lockForUpdate()->firstOrFail();
            $sale->load('items');

            if ($sale->isVoided()) {
                throw new DomainException('La venta está anulada: no se puede devolver.');
            }

            $factor = $this->factor($sale);
            $rows = $this->validatedRows($sale, $data['items'] ?? [], $factor);
            $total = round(array_sum(array_column($rows, 'amount')), 2);
            $method = $data['refund_method'];

            if ($total > round((float) $sale->total - (float) $sale->returned_total, 2) + 0.01) {
                throw new DomainException('El reintegro supera lo que queda por devolver de la venta.');
            }
            if ($method === 'credit' && !$sale->client_id) {
                throw new DomainException('Para devolver como saldo a favor, la venta debe tener un cliente registrado.');
            }
            if ($method === 'cash' && $total > $this->cashRefundable($sale) + 0.01) {
                throw new DomainException('Esta venta se pagó en parte con saldo a favor: en dinero se pueden devolver hasta '
                    . number_format($this->cashRefundable($sale), 2) . '. El resto, como saldo a favor.');
            }

            $session = $this->cashSession($sale, $method, $total, $data['cash_session_id'] ?? null);
            $setting = CompanySetting::current($sale->company_id);
            $count = SaleReturn::withoutGlobalScopes()->where('company_id', $sale->company_id)->count() + 1;

            $return = SaleReturn::create([
                'company_id'      => $sale->company_id,
                'sale_id'         => $sale->id,
                'client_id'       => $sale->client_id,
                'return_number'   => $setting->documentFolio('DEV-', $count),
                'return_date'     => now(),
                'reason'          => $data['reason'],
                'refund_method'   => $method,
                'payment_method'  => $method === 'cash' ? ($data['payment_method'] ?? $sale->payment_method) : null,
                'cash_session_id' => $session?->id,
                'total'           => $total,
                'notes'           => $data['notes'] ?? null,
                'created_by'      => auth()->id(),
            ]);

            $quantities = [];
            foreach ($rows as $row) {
                /** @var SaleItem $item */
                $item = $row['item'];
                $return->items()->create([
                    'sale_detail_id' => $item->id,
                    'quantity'       => $row['quantity'],
                    'amount'         => $row['amount'],
                    'restock'        => $row['restock'],
                ]);
                $item->update(['returned_quantity' => round((float) $item->returned_quantity + $row['quantity'], 2)]);
                $quantities[$item->id] = $row['quantity'];

                if ($row['restock']) {
                    $this->stock->move((int) $item->product_id, $row['quantity'], 'entrada', $sale->company_id, $return->return_number, 'Devolución ' . $return->return_number . ' · venta ' . $sale->sale_number);
                }
                if ($item->isMembershipSale()) {
                    $this->memberships->cancelForLine($item, 'devolución ' . $return->return_number);
                }
                if ($item->isPackageSale()) {
                    $this->packages->cancelForLine($item, 'devolución ' . $return->return_number);
                }
            }

            $this->commissions->reverseForReturn($sale, $quantities, $return->return_number);
            $this->adjustPoints($sale, $return);

            if ($method === 'credit' && $total > 0) {
                $this->credit->fromReturn($return);
            }
            if ($session && $total > 0) {
                $session->movements()->create([
                    'type'           => 'egreso',
                    'amount'         => $total,
                    'concept'        => 'Devolución ' . $return->return_number . ' · venta ' . $sale->sale_number,
                    'category'       => 'Devoluciones',
                    'payment_method' => $return->payment_method,
                    'reference'      => (string) $sale->id,
                    'created_by'     => auth()->id(),
                ]);
            }

            $sale->update(['returned_total' => round((float) $sale->returned_total + $total, 2)]);

            return $return;
        });
    }

    /** Valida las líneas pedidas: que sean de la venta, cantidades y montos. */
    protected function validatedRows(Sale $sale, array $input, float $factor): array
    {
        $items = $sale->items->keyBy('id');
        $rows = [];

        foreach ($input as $row) {
            $qty = round((float) ($row['quantity'] ?? 0), 2);
            if ($qty <= 0) {
                continue;
            }

            $item = $items[(int) ($row['sale_detail_id'] ?? 0)] ?? null;
            if (!$item) {
                throw new DomainException('La devolución incluye una línea que no es de esta venta.');
            }
            $name = $item->description ?: 'la línea';
            if ($qty > $item->returnableQuantity() + 0.001) {
                throw new DomainException("De {$name} se pueden devolver hasta " . rtrim(rtrim(number_format($item->returnableQuantity(), 2), '0'), '.') . '.');
            }
            if (($item->isMembershipSale() || $item->isPackageSale()) && $qty < (float) $item->quantity) {
                throw new DomainException("{$name} se devuelve completa.");
            }

            $max = $this->maxRefund($item, $qty, $factor);
            $amount = isset($row['amount']) && $row['amount'] !== '' && $row['amount'] !== null ? round((float) $row['amount'], 2) : $max;
            if ($amount < 0 || $amount > $max + 0.01) {
                throw new DomainException("Por {$name} se pueden reintegrar hasta " . number_format($max, 2) . '.');
            }

            $rows[] = [
                'item'     => $item,
                'quantity' => $qty,
                'amount'   => min($amount, $max),
                'restock'  => $item->isProduct() && !empty($row['restock']),
            ];
        }

        if (!$rows) {
            throw new DomainException('Indica qué se devuelve: al menos una línea con cantidad.');
        }

        return $rows;
    }

    /** Caja donde sale el dinero: obligatoria si la empresa lo exige. */
    protected function cashSession(Sale $sale, string $method, float $total, $sessionId): ?CashSession
    {
        if ($method !== 'cash' || $total <= 0) {
            return null;
        }

        $session = $sessionId
            ? CashSession::where('id', $sessionId)->where('status', 'open')
                ->whereHas('register', fn ($q) => $q->where('company_id', $sale->company_id))->first()
            : null;

        if ($sessionId && !$session) {
            throw new DomainException('La caja elegida no está abierta.');
        }
        if (!$session && CompanySetting::current($sale->company_id)->require_cash_session) {
            throw new DomainException('Las preferencias de la empresa exigen registrar el reintegro en una caja abierta.');
        }

        return $session;
    }

    /**
     * Puntos en proporción a lo devuelto. En la devolución que completa la
     * venta se ajusta el resto, para que no queden puntos sueltos por redondeo.
     */
    protected function adjustPoints(Sale $sale, SaleReturn $return): void
    {
        $earned = (int) $sale->loyalty_points_earned;
        $redeemed = (int) $sale->loyalty_points_redeemed;
        if (!$sale->client_id || ($earned === 0 && $redeemed === 0) || (float) $sale->total <= 0) {
            return;
        }

        $previous = SaleReturn::withoutGlobalScopes()->where('sale_id', $sale->id)->where('id', '!=', $return->id);
        $removedBefore = (int) (clone $previous)->sum('points_removed');
        $returnedBefore = (int) (clone $previous)->sum('points_returned');

        $complete = (float) $sale->returned_total + (float) $return->total >= (float) $sale->total - 0.01;
        $fraction = (float) $return->total / (float) $sale->total;

        $removed = $complete ? $earned - $removedBefore : min($earned - $removedBefore, (int) round($earned * $fraction));
        $returned = $complete ? $redeemed - $returnedBefore : min($redeemed - $returnedBefore, (int) round($redeemed * $fraction));
        $removed = max(0, $removed);
        $returned = max(0, $returned);

        $return->update(['points_removed' => $removed, 'points_returned' => $returned]);
        $this->loyalty->adjustForReturn($sale, $removed, $returned, $return->return_number);
    }
}
