<?php

namespace App\Services;

use App\Models\InventoryMovement;
use App\Models\Product;
use DomainException;

/**
 * Movimientos de stock de productos con su registro en el kardex. Lo usan
 * las ventas, sus anulaciones y devoluciones, y las devoluciones a proveedor.
 */
class StockService
{
    /**
     * Mueve el stock de un producto. $delta negativo = sale stock. Sin stock
     * suficiente lanza DomainException (la operación completa se cancela).
     */
    public function move(int $productId, float $delta, string $type, int $companyId, string $reference, string $note): void
    {
        $product = Product::withoutGlobalScopes()->where('company_id', $companyId)->whereKey($productId)->lockForUpdate()->first();
        if (!$product) {
            return;
        }

        $current = (float) $product->current_stock;
        if ($delta < 0 && abs($delta) > $current + 0.0001) {
            throw new DomainException("No hay stock suficiente de {$product->name}. Disponible: " . rtrim(rtrim(number_format($current, 2), '0'), '.'));
        }

        $product->update(['current_stock' => max(0, $current + $delta)]);

        InventoryMovement::create([
            'company_id'    => $companyId,
            'product_id'    => $product->id,
            'user_id'       => auth()->id(),
            'type'          => $type,
            'quantity'      => abs($delta),
            'unit_cost'     => $product->cost,
            'reference'     => $reference,
            'notes'         => $note,
            'movement_date' => now(),
        ]);
    }
}
