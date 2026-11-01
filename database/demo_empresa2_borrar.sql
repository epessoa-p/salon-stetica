-- ═══════════════════════════════════════════════════════════════════════
-- Glamora · Borra los datos de demostración de la empresa 2
-- (los que insertó demo_empresa2_ultimos_11_dias.sql).
--
-- SEGURO DE REEJECUTAR: solo toca filas marcadas «Datos de demostración».
-- La actividad (ventas, citas, caja, asistencia, inventario) se borra; el
-- catálogo (servicios, productos, clientes, personal, caja) se da de baja
-- (borrado lógico) para no romper ventas reales que lo usen.
-- Después se puede volver a ejecutar el script de demostración.
-- ═══════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;
SET @c := 2;

DELETE d FROM sale_details d JOIN sales s ON s.id = d.sale_id WHERE s.company_id = @c AND s.notes = 'Datos de demostración';
DELETE m FROM cash_movements m JOIN cash_sessions cs ON cs.id = m.cash_session_id WHERE cs.notes = 'Datos de demostración'
  AND cs.cash_register_id IN (SELECT id FROM cash_registers WHERE company_id = @c);
DELETE FROM sales WHERE company_id = @c AND notes = 'Datos de demostración';
DELETE FROM cash_sessions WHERE notes = 'Datos de demostración' AND cash_register_id IN (SELECT id FROM cash_registers WHERE company_id = @c);
DELETE FROM appointments WHERE company_id = @c AND notes = 'Datos de demostración';
DELETE FROM attendances WHERE company_id = @c AND notes = 'Datos de demostración';

-- Stock: se devuelve lo vendido en la demo y se quita el inventario inicial.
UPDATE products p SET current_stock = GREATEST(0, current_stock
    + COALESCE((SELECT SUM(quantity) FROM inventory_movements WHERE product_id = p.id AND type = 'salida' AND notes LIKE CONCAT('%', 'Datos de demostración')), 0)
    - COALESCE((SELECT SUM(quantity) FROM inventory_movements WHERE product_id = p.id AND type = 'entrada' AND notes = 'Datos de demostración'), 0))
WHERE company_id = @c;
DELETE FROM inventory_movements WHERE company_id = @c AND (notes = 'Datos de demostración' OR notes LIKE CONCAT('% · ', 'Datos de demostración'));

-- Catálogo de demostración: baja lógica (se puede reactivar desde la web).
UPDATE services SET deleted_at = NOW() WHERE company_id = @c AND description = 'Datos de demostración' AND deleted_at IS NULL;
UPDATE service_categories SET deleted_at = NOW() WHERE company_id = @c AND description = 'Datos de demostración' AND deleted_at IS NULL;
UPDATE products SET deleted_at = NOW() WHERE company_id = @c AND description = 'Datos de demostración' AND deleted_at IS NULL;
UPDATE product_categories SET deleted_at = NOW() WHERE company_id = @c AND description = 'Datos de demostración' AND deleted_at IS NULL;
UPDATE clients SET deleted_at = NOW() WHERE company_id = @c AND source = 'Datos de demostración' AND deleted_at IS NULL;
DELETE FROM staff_schedules WHERE personal_id IN (SELECT id FROM personals WHERE company_id = @c AND notes = 'Datos de demostración');
UPDATE personals SET deleted_at = NOW() WHERE company_id = @c AND notes = 'Datos de demostración' AND deleted_at IS NULL;
UPDATE cargos SET deleted_at = NOW() WHERE company_id = @c AND description = 'Datos de demostración' AND deleted_at IS NULL;
UPDATE cash_registers SET branch_id = NULL WHERE company_id = @c AND branch_id = (SELECT id FROM branches WHERE code = 'DEMO2-SC01');
UPDATE branches SET deleted_at = NOW() WHERE company_id = @c AND code = 'DEMO2-SC01' AND deleted_at IS NULL;
UPDATE warehouses SET deleted_at = NOW() WHERE company_id = @c AND code = 'DEMO2-WH01' AND deleted_at IS NULL;

SELECT 'Datos de demostración eliminados.' AS resultado;
