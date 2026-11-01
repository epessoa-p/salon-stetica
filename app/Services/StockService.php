<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\InventoryMovement;
use App\Models\Product;
use App\Models\ProductStock;
use App\Models\Warehouse;
use DomainException;

/**
 * Stock por almacén con su registro en el kardex. Toda entrada o salida de
 * productos pasa por aquí: ventas, anulaciones, devoluciones, compras,
 * devoluciones a proveedor, ajustes y traspasos.
 *
 * Cada producto tiene su existencia en cada almacén (product_stocks) y
 * products.current_stock guarda el total de la empresa (la suma), que es lo
 * que usan las alertas de stock bajo y los reportes.
 *
 * Almacén de un movimiento: el indicado, el de la sucursal o, si no hay
 * ninguno, el principal de la empresa (el de su sucursal más antigua).
 */
class StockService
{
    /**
     * Mueve el stock de un producto. $delta negativo = sale stock. Sin stock
     * suficiente en ese almacén lanza DomainException (la operación completa
     * se cancela).
     */
    public function move(int $productId, float $delta, string $type, int $companyId, string $reference, string $note, ?int $branchId = null, ?int $warehouseId = null): void
    {
        $product = Product::withoutGlobalScopes()->where('company_id', $companyId)->whereKey($productId)->lockForUpdate()->first();
        if (!$product) {
            return;
        }

        [$warehouseId, $branchId] = $this->place($companyId, $branchId, $warehouseId);
        $this->seed($product);
        $stock = $this->row($companyId, $product->id, $warehouseId);

        $current = (float) $stock->quantity;
        if ($delta < 0 && abs($delta) > $current + 0.0001) {
            $where = Warehouse::withoutGlobalScopes()->whereKey($warehouseId)->value('name');
            throw new DomainException("No hay stock suficiente de {$product->name} en {$where}. Disponible: " . $this->fmt($current));
        }

        $stock->update(['quantity' => max(0, round($current + $delta, 2))]);
        $this->syncTotal($product);
        $this->log($product, $type, abs($delta), $companyId, $warehouseId, $branchId, $reference, $note);
    }

    /** Ajuste: deja la existencia del almacén en una cantidad exacta (conteo físico). */
    public function setTo(int $productId, float $quantity, int $companyId, ?int $warehouseId, ?string $note, ?float $unitCost = null): void
    {
        $product = Product::withoutGlobalScopes()->where('company_id', $companyId)->whereKey($productId)->lockForUpdate()->firstOrFail();
        [$warehouseId, $branchId] = $this->place($companyId, null, $warehouseId);
        $this->seed($product);

        $this->row($companyId, $product->id, $warehouseId)->update(['quantity' => max(0, round($quantity, 2))]);
        $this->syncTotal($product);
        $this->log($product, 'ajuste', $quantity, $companyId, $warehouseId, $branchId, null, $note, $unitCost);
    }

    /** Existencia de cada producto en un almacén (sin almacén: el total de la empresa). */
    public function available(array $productIds, int $companyId, ?int $warehouseId): array
    {
        if (!$productIds) {
            return [];
        }
        if (!$warehouseId) {
            return Product::withoutGlobalScopes()->where('company_id', $companyId)->whereIn('id', $productIds)->pluck('current_stock', 'id')->map(fn ($q) => (float) $q)->all();
        }

        $found = ProductStock::withoutGlobalScopes()->where('warehouse_id', $warehouseId)->whereIn('product_id', $productIds)->pluck('quantity', 'product_id');

        // Productos aún sin existencias por almacén: su total está en el almacén principal.
        $unseeded = [];
        if ($warehouseId === $this->defaultWarehouse($companyId)) {
            $withRows = ProductStock::withoutGlobalScopes()->whereIn('product_id', $productIds)->distinct()->pluck('product_id')->all();
            $missing = array_diff($productIds, $withRows);
            $unseeded = $missing ? Product::withoutGlobalScopes()->whereIn('id', $missing)->pluck('current_stock', 'id')->all() : [];
        }

        return collect($productIds)->mapWithKeys(fn ($id) => [$id => (float) ($found[$id] ?? $unseeded[$id] ?? 0)])->all();
    }

    /** Almacén donde ocurre una operación de la sucursal indicada (o el principal). */
    public function warehouseFor(int $companyId, ?int $branchId = null): ?int
    {
        return $this->place($companyId, $branchId, null)[0];
    }

    /** Almacén principal de la empresa: el de su sucursal más antigua (o su primer almacén). */
    public function defaultWarehouse(int $companyId): ?int
    {
        return Branch::withoutGlobalScopes()->whereNull('deleted_at')->where('company_id', $companyId)->whereNotNull('warehouse_id')->orderBy('id')->value('warehouse_id')
            ?? Warehouse::withoutGlobalScopes()->whereNull('deleted_at')->where('company_id', $companyId)->orderBy('id')->value('id');
    }

    // ---- Internos ----

    /** [almacén, sucursal] de un movimiento. Sin ningún almacén en la empresa, se crea el principal. */
    protected function place(int $companyId, ?int $branchId, ?int $warehouseId): array
    {
        if (!$warehouseId && $branchId) {
            $warehouseId = Branch::withoutGlobalScopes()->where('company_id', $companyId)->whereKey($branchId)->value('warehouse_id');
        }
        $warehouseId ??= $this->defaultWarehouse($companyId)
            ?? Warehouse::createForBranch($companyId, 'PRINCIPAL')->id;

        // La sucursal del movimiento: la indicada o la dueña del almacén.
        $branchId ??= Branch::withoutGlobalScopes()->whereNull('deleted_at')->where('company_id', $companyId)->where('warehouse_id', $warehouseId)->orderBy('id')->value('id');

        return [(int) $warehouseId, $branchId ? (int) $branchId : null];
    }

    /**
     * Producto sin existencias por almacén (creado antes del inventario por
     * almacén o por otra vía): su total pasa al almacén principal.
     */
    protected function seed(Product $product): void
    {
        if (ProductStock::withoutGlobalScopes()->where('product_id', $product->id)->exists()) {
            return;
        }
        $main = $this->defaultWarehouse($product->company_id) ?? Warehouse::createForBranch($product->company_id, 'PRINCIPAL')->id;
        ProductStock::withoutGlobalScopes()->create(['company_id' => $product->company_id, 'product_id' => $product->id, 'warehouse_id' => $main, 'quantity' => (float) $product->current_stock]);
    }

    /** Fila de existencia (bloqueada) del producto en el almacén. */
    protected function row(int $companyId, int $productId, int $warehouseId): ProductStock
    {
        ProductStock::withoutGlobalScopes()->firstOrCreate(
            ['product_id' => $productId, 'warehouse_id' => $warehouseId],
            ['company_id' => $companyId, 'quantity' => 0],
        );

        return ProductStock::withoutGlobalScopes()->where('product_id', $productId)->where('warehouse_id', $warehouseId)->lockForUpdate()->first();
    }

    protected function syncTotal(Product $product): void
    {
        $product->update(['current_stock' => (float) ProductStock::withoutGlobalScopes()->where('product_id', $product->id)->sum('quantity')]);
    }

    protected function log(Product $product, string $type, float $quantity, int $companyId, int $warehouseId, ?int $branchId, ?string $reference, ?string $note, ?float $unitCost = null): void
    {
        InventoryMovement::create([
            'company_id'    => $companyId,
            'branch_id'     => $branchId,
            'warehouse_id'  => $warehouseId,
            'product_id'    => $product->id,
            'user_id'       => auth()->id(),
            'type'          => $type,
            'quantity'      => $quantity,
            'unit_cost'     => $unitCost ?? $product->cost,
            'reference'     => $reference,
            'notes'         => $note,
            'movement_date' => now(),
        ]);
    }

    protected function fmt(float $n): string
    {
        return rtrim(rtrim(number_format($n, 2), '0'), '.');
    }
}
