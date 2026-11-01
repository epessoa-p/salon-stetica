<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\Warehouse;
use DomainException;

/**
 * Traspasos de productos entre almacenes (sucursales). Sale del almacén de
 * origen y entra al de destino en el mismo momento, con su par de
 * movimientos en el kardex. El total de la empresa no cambia.
 * Corre dentro de la transacción de quien llama.
 */
class StockTransferService
{
    public function __construct(protected StockService $stock)
    {
    }

    /**
     * @param  array<int, array{product_id: int, quantity: float}>  $lines
     * @throws DomainException
     */
    public function create(int $companyId, int $fromId, int $toId, array $lines, ?string $notes = null): StockTransfer
    {
        if ($fromId === $toId) {
            throw new DomainException('El almacén de origen y el de destino deben ser distintos.');
        }
        $from = Warehouse::withoutGlobalScopes()->where('company_id', $companyId)->whereNull('deleted_at')->find($fromId);
        $to = Warehouse::withoutGlobalScopes()->where('company_id', $companyId)->whereNull('deleted_at')->find($toId);
        if (!$from || !$to) {
            throw new DomainException('Los almacenes deben ser de la empresa.');
        }

        // Un producto repetido en varias líneas se suma.
        $qty = [];
        foreach ($lines as $line) {
            if ((float) $line['quantity'] > 0) {
                $qty[(int) $line['product_id']] = ($qty[(int) $line['product_id']] ?? 0) + (float) $line['quantity'];
            }
        }
        if (!$qty) {
            throw new DomainException('Agrega al menos un producto con cantidad.');
        }
        $valid = Product::withoutGlobalScopes()->where('company_id', $companyId)->whereIn('id', array_keys($qty))->pluck('id')->all();
        if (count($valid) !== count($qty)) {
            throw new DomainException('Hay productos que no son de la empresa.');
        }

        $transfer = StockTransfer::withoutGlobalScopes()->create([
            'company_id'        => $companyId,
            'transfer_number'   => $this->nextNumber($companyId),
            'from_warehouse_id' => $from->id,
            'to_warehouse_id'   => $to->id,
            'status'            => 'completada',
            'notes'             => $notes,
            'transferred_at'    => now(),
            'created_by'        => auth()->id(),
        ]);

        foreach ($qty as $productId => $quantity) {
            $transfer->items()->create(['product_id' => $productId, 'quantity' => $quantity]);
            // Si no alcanza en el origen, StockService lanza la excepción y se cancela todo.
            $this->stock->move($productId, -$quantity, 'salida', $companyId, $transfer->transfer_number, "Traspaso a {$to->name}", warehouseId: $from->id);
            $this->stock->move($productId, $quantity, 'entrada', $companyId, $transfer->transfer_number, "Traspaso desde {$from->name}", warehouseId: $to->id);
        }

        return $transfer;
    }

    /**
     * Anula: devuelve los productos al origen. Si en el destino ya se
     * vendió parte, no alcanza y no se puede anular.
     *
     * @throws DomainException
     */
    public function void(StockTransfer $transfer): void
    {
        if ($transfer->isVoided()) {
            throw new DomainException('El traspaso ya está anulado.');
        }
        $transfer->loadMissing('items', 'from', 'to');

        foreach ($transfer->items as $item) {
            $q = (float) $item->quantity;
            $this->stock->move($item->product_id, -$q, 'salida', $transfer->company_id, $transfer->transfer_number, "Anulación traspaso (vuelve a {$transfer->from->name})", warehouseId: $transfer->to_warehouse_id);
            $this->stock->move($item->product_id, $q, 'entrada', $transfer->company_id, $transfer->transfer_number, "Anulación traspaso (desde {$transfer->to->name})", warehouseId: $transfer->from_warehouse_id);
        }

        $transfer->update(['status' => 'anulada', 'voided_at' => now(), 'voided_by' => auth()->id()]);
    }

    protected function nextNumber(int $companyId): string
    {
        $last = StockTransfer::withoutGlobalScopes()->where('company_id', $companyId)->lockForUpdate()->count();

        return 'TR-' . str_pad((string) ($last + 1), 6, '0', STR_PAD_LEFT);
    }
}
