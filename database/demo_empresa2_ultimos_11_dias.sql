-- ═══════════════════════════════════════════════════════════════════════
-- Glamora · Datos de demostración para la empresa 2 (usuario 3)
-- Actividad de los últimos 10 días más hoy, relativa a la fecha en que se
-- ejecuta (CURDATE()): citas, ventas con su detalle, caja diaria con
-- arqueo, gastos, asistencia del personal y movimientos de inventario.
--
-- SEGURO DE REEJECUTAR: el catálogo (categorías, servicios, productos,
-- clientes, personal, horarios y caja) se crea solo si no existe, y la
-- actividad se inserta una sola vez (si ya hay ventas de demostración, no
-- inserta nada más). Todo queda marcado como «Datos de demostración».
-- Para quitarlo: database/demo_empresa2_borrar.sql
--
-- Requisitos: la empresa 2 y el usuario 3 deben existir. No cambia
-- configuración (reglas de comisión, preferencias ni plan).
-- ═══════════════════════════════════════════════════════════════════════

SET NAMES utf8mb4;
SET @c := 2;
SET @u := 3;

-- Se ejecuta solo si la empresa y el usuario existen y aún no hay demo.
SET @ok := (SELECT COUNT(*) FROM companies WHERE id = @c) * (SELECT COUNT(*) FROM users WHERE id = @u);
SET @run := IF(@ok > 0 AND (SELECT COUNT(*) FROM sales WHERE company_id = @c AND notes = 'Datos de demostración') = 0, 1, 0);
SELECT IF(@ok = 0, 'La empresa 2 o el usuario 3 no existen: no se insertó nada.',
       IF(@run = 1, 'Insertando datos de demostración…', 'Ya existen datos de demostración: no se insertó nada.')) AS estado;

SET @pg := (SELECT id FROM personals WHERE company_id = @c AND user_id = @u AND deleted_at IS NULL LIMIT 1);
SET @prefix := COALESCE((SELECT sale_prefix FROM company_settings WHERE company_id = @c), 'V-');
SET @pad := COALESCE((SELECT sale_padding FROM company_settings WHERE company_id = @c), 6);
-- Último correlativo: sin comparar textos entre tablas (sus collations pueden diferir).
SET @n := COALESCE((SELECT MAX(CAST(SUBSTRING(sale_number, CHAR_LENGTH(@prefix) + 1) AS UNSIGNED)) FROM sales WHERE company_id = @c), 0);

-- ── Catálogo (solo lo que falte) ─────────────────────────────────────────
INSERT INTO service_categories (company_id, name, description, active, created_by, created_at, updated_at)
SELECT @c, 'CABELLO', 'Datos de demostración', 1, @u, NOW(), NOW() FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM service_categories WHERE company_id = @c AND name = 'CABELLO' AND deleted_at IS NULL);
INSERT INTO service_categories (company_id, name, description, active, created_by, created_at, updated_at)
SELECT @c, 'UÑAS', 'Datos de demostración', 1, @u, NOW(), NOW() FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM service_categories WHERE company_id = @c AND name = 'UÑAS' AND deleted_at IS NULL);
INSERT INTO service_categories (company_id, name, description, active, created_by, created_at, updated_at)
SELECT @c, 'FACIAL Y CEJAS', 'Datos de demostración', 1, @u, NOW(), NOW() FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM service_categories WHERE company_id = @c AND name = 'FACIAL Y CEJAS' AND deleted_at IS NULL);
INSERT INTO services (company_id, service_category_id, name, description, price, duration_minutes, active, created_by, created_at, updated_at)
SELECT @c, (SELECT id FROM service_categories WHERE company_id = @c AND name = 'CABELLO' AND deleted_at IS NULL ORDER BY id LIMIT 1), 'CORTE DAMA', 'Datos de demostración', 80, 45, 1, @u, NOW(), NOW()
FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM services WHERE company_id = @c AND name = 'CORTE DAMA' AND deleted_at IS NULL);
SET @s_corte_dama := (SELECT id FROM services WHERE company_id = @c AND name = 'CORTE DAMA' AND deleted_at IS NULL ORDER BY id LIMIT 1);
INSERT INTO services (company_id, service_category_id, name, description, price, duration_minutes, active, created_by, created_at, updated_at)
SELECT @c, (SELECT id FROM service_categories WHERE company_id = @c AND name = 'CABELLO' AND deleted_at IS NULL ORDER BY id LIMIT 1), 'CORTE CABALLERO', 'Datos de demostración', 50, 30, 1, @u, NOW(), NOW()
FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM services WHERE company_id = @c AND name = 'CORTE CABALLERO' AND deleted_at IS NULL);
SET @s_corte_cab := (SELECT id FROM services WHERE company_id = @c AND name = 'CORTE CABALLERO' AND deleted_at IS NULL ORDER BY id LIMIT 1);
INSERT INTO services (company_id, service_category_id, name, description, price, duration_minutes, active, created_by, created_at, updated_at)
SELECT @c, (SELECT id FROM service_categories WHERE company_id = @c AND name = 'CABELLO' AND deleted_at IS NULL ORDER BY id LIMIT 1), 'BRUSHING', 'Datos de demostración', 60, 45, 1, @u, NOW(), NOW()
FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM services WHERE company_id = @c AND name = 'BRUSHING' AND deleted_at IS NULL);
SET @s_brushing := (SELECT id FROM services WHERE company_id = @c AND name = 'BRUSHING' AND deleted_at IS NULL ORDER BY id LIMIT 1);
INSERT INTO services (company_id, service_category_id, name, description, price, duration_minutes, active, created_by, created_at, updated_at)
SELECT @c, (SELECT id FROM service_categories WHERE company_id = @c AND name = 'CABELLO' AND deleted_at IS NULL ORDER BY id LIMIT 1), 'TINTE COMPLETO', 'Datos de demostración', 250, 120, 1, @u, NOW(), NOW()
FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM services WHERE company_id = @c AND name = 'TINTE COMPLETO' AND deleted_at IS NULL);
SET @s_tinte := (SELECT id FROM services WHERE company_id = @c AND name = 'TINTE COMPLETO' AND deleted_at IS NULL ORDER BY id LIMIT 1);
INSERT INTO services (company_id, service_category_id, name, description, price, duration_minutes, active, created_by, created_at, updated_at)
SELECT @c, (SELECT id FROM service_categories WHERE company_id = @c AND name = 'CABELLO' AND deleted_at IS NULL ORDER BY id LIMIT 1), 'MECHAS / BALAYAGE', 'Datos de demostración', 380, 150, 1, @u, NOW(), NOW()
FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM services WHERE company_id = @c AND name = 'MECHAS / BALAYAGE' AND deleted_at IS NULL);
SET @s_mechas := (SELECT id FROM services WHERE company_id = @c AND name = 'MECHAS / BALAYAGE' AND deleted_at IS NULL ORDER BY id LIMIT 1);
INSERT INTO services (company_id, service_category_id, name, description, price, duration_minutes, active, created_by, created_at, updated_at)
SELECT @c, (SELECT id FROM service_categories WHERE company_id = @c AND name = 'CABELLO' AND deleted_at IS NULL ORDER BY id LIMIT 1), 'TRATAMIENTO DE KERATINA', 'Datos de demostración', 300, 120, 1, @u, NOW(), NOW()
FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM services WHERE company_id = @c AND name = 'TRATAMIENTO DE KERATINA' AND deleted_at IS NULL);
SET @s_keratina := (SELECT id FROM services WHERE company_id = @c AND name = 'TRATAMIENTO DE KERATINA' AND deleted_at IS NULL ORDER BY id LIMIT 1);
INSERT INTO services (company_id, service_category_id, name, description, price, duration_minutes, active, created_by, created_at, updated_at)
SELECT @c, (SELECT id FROM service_categories WHERE company_id = @c AND name = 'UÑAS' AND deleted_at IS NULL ORDER BY id LIMIT 1), 'MANICURE', 'Datos de demostración', 60, 45, 1, @u, NOW(), NOW()
FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM services WHERE company_id = @c AND name = 'MANICURE' AND deleted_at IS NULL);
SET @s_manicure := (SELECT id FROM services WHERE company_id = @c AND name = 'MANICURE' AND deleted_at IS NULL ORDER BY id LIMIT 1);
INSERT INTO services (company_id, service_category_id, name, description, price, duration_minutes, active, created_by, created_at, updated_at)
SELECT @c, (SELECT id FROM service_categories WHERE company_id = @c AND name = 'UÑAS' AND deleted_at IS NULL ORDER BY id LIMIT 1), 'PEDICURE', 'Datos de demostración', 80, 60, 1, @u, NOW(), NOW()
FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM services WHERE company_id = @c AND name = 'PEDICURE' AND deleted_at IS NULL);
SET @s_pedicure := (SELECT id FROM services WHERE company_id = @c AND name = 'PEDICURE' AND deleted_at IS NULL ORDER BY id LIMIT 1);
INSERT INTO services (company_id, service_category_id, name, description, price, duration_minutes, active, created_by, created_at, updated_at)
SELECT @c, (SELECT id FROM service_categories WHERE company_id = @c AND name = 'UÑAS' AND deleted_at IS NULL ORDER BY id LIMIT 1), 'UÑAS SEMIPERMANENTES', 'Datos de demostración', 120, 60, 1, @u, NOW(), NOW()
FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM services WHERE company_id = @c AND name = 'UÑAS SEMIPERMANENTES' AND deleted_at IS NULL);
SET @s_semi := (SELECT id FROM services WHERE company_id = @c AND name = 'UÑAS SEMIPERMANENTES' AND deleted_at IS NULL ORDER BY id LIMIT 1);
INSERT INTO services (company_id, service_category_id, name, description, price, duration_minutes, active, created_by, created_at, updated_at)
SELECT @c, (SELECT id FROM service_categories WHERE company_id = @c AND name = 'UÑAS' AND deleted_at IS NULL ORDER BY id LIMIT 1), 'UÑAS ACRÍLICAS', 'Datos de demostración', 180, 90, 1, @u, NOW(), NOW()
FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM services WHERE company_id = @c AND name = 'UÑAS ACRÍLICAS' AND deleted_at IS NULL);
SET @s_acrilicas := (SELECT id FROM services WHERE company_id = @c AND name = 'UÑAS ACRÍLICAS' AND deleted_at IS NULL ORDER BY id LIMIT 1);
INSERT INTO services (company_id, service_category_id, name, description, price, duration_minutes, active, created_by, created_at, updated_at)
SELECT @c, (SELECT id FROM service_categories WHERE company_id = @c AND name = 'FACIAL Y CEJAS' AND deleted_at IS NULL ORDER BY id LIMIT 1), 'LIMPIEZA FACIAL', 'Datos de demostración', 180, 60, 1, @u, NOW(), NOW()
FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM services WHERE company_id = @c AND name = 'LIMPIEZA FACIAL' AND deleted_at IS NULL);
SET @s_facial := (SELECT id FROM services WHERE company_id = @c AND name = 'LIMPIEZA FACIAL' AND deleted_at IS NULL ORDER BY id LIMIT 1);
INSERT INTO services (company_id, service_category_id, name, description, price, duration_minutes, active, created_by, created_at, updated_at)
SELECT @c, (SELECT id FROM service_categories WHERE company_id = @c AND name = 'FACIAL Y CEJAS' AND deleted_at IS NULL ORDER BY id LIMIT 1), 'DISEÑO DE CEJAS', 'Datos de demostración', 40, 20, 1, @u, NOW(), NOW()
FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM services WHERE company_id = @c AND name = 'DISEÑO DE CEJAS' AND deleted_at IS NULL);
SET @s_cejas := (SELECT id FROM services WHERE company_id = @c AND name = 'DISEÑO DE CEJAS' AND deleted_at IS NULL ORDER BY id LIMIT 1);
INSERT INTO product_categories (company_id, name, description, active, created_by, created_at, updated_at)
SELECT @c, 'CUIDADO PERSONAL', 'Datos de demostración', 1, @u, NOW(), NOW() FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM product_categories WHERE company_id = @c AND name = 'CUIDADO PERSONAL' AND deleted_at IS NULL);
UPDATE products SET deleted_at = NULL, current_stock = 0 WHERE company_id = @c AND sku = 'DEMO2-SHA400' AND description = 'Datos de demostración' AND deleted_at IS NOT NULL AND @ok > 0;
INSERT INTO products (company_id, product_category_id, name, sku, description, unit, cost, price, min_stock, current_stock, active, created_at, updated_at)
SELECT @c, (SELECT id FROM product_categories WHERE company_id = @c AND name = 'CUIDADO PERSONAL' AND deleted_at IS NULL ORDER BY id LIMIT 1), 'SHAMPOO REPARADOR 400ML', 'DEMO2-SHA400', 'Datos de demostración', 'unidad', 45, 85, 5, 0, 1, NOW(), NOW()
FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM products WHERE sku = 'DEMO2-SHA400');
SET @p_shampoo := (SELECT id FROM products WHERE company_id = @c AND sku = 'DEMO2-SHA400' LIMIT 1);
UPDATE products SET deleted_at = NULL, current_stock = 0 WHERE company_id = @c AND sku = 'DEMO2-ACO400' AND description = 'Datos de demostración' AND deleted_at IS NOT NULL AND @ok > 0;
INSERT INTO products (company_id, product_category_id, name, sku, description, unit, cost, price, min_stock, current_stock, active, created_at, updated_at)
SELECT @c, (SELECT id FROM product_categories WHERE company_id = @c AND name = 'CUIDADO PERSONAL' AND deleted_at IS NULL ORDER BY id LIMIT 1), 'ACONDICIONADOR 400ML', 'DEMO2-ACO400', 'Datos de demostración', 'unidad', 45, 85, 5, 0, 1, NOW(), NOW()
FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM products WHERE sku = 'DEMO2-ACO400');
SET @p_acond := (SELECT id FROM products WHERE company_id = @c AND sku = 'DEMO2-ACO400' LIMIT 1);
UPDATE products SET deleted_at = NULL, current_stock = 0 WHERE company_id = @c AND sku = 'DEMO2-MAS250' AND description = 'Datos de demostración' AND deleted_at IS NOT NULL AND @ok > 0;
INSERT INTO products (company_id, product_category_id, name, sku, description, unit, cost, price, min_stock, current_stock, active, created_at, updated_at)
SELECT @c, (SELECT id FROM product_categories WHERE company_id = @c AND name = 'CUIDADO PERSONAL' AND deleted_at IS NULL ORDER BY id LIMIT 1), 'MASCARILLA CAPILAR 250G', 'DEMO2-MAS250', 'Datos de demostración', 'unidad', 60, 120, 5, 0, 1, NOW(), NOW()
FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM products WHERE sku = 'DEMO2-MAS250');
SET @p_mascar := (SELECT id FROM products WHERE company_id = @c AND sku = 'DEMO2-MAS250' LIMIT 1);
UPDATE products SET deleted_at = NULL, current_stock = 0 WHERE company_id = @c AND sku = 'DEMO2-SER060' AND description = 'Datos de demostración' AND deleted_at IS NOT NULL AND @ok > 0;
INSERT INTO products (company_id, product_category_id, name, sku, description, unit, cost, price, min_stock, current_stock, active, created_at, updated_at)
SELECT @c, (SELECT id FROM product_categories WHERE company_id = @c AND name = 'CUIDADO PERSONAL' AND deleted_at IS NULL ORDER BY id LIMIT 1), 'SÉRUM DE ARGÁN 60ML', 'DEMO2-SER060', 'Datos de demostración', 'unidad', 55, 110, 5, 0, 1, NOW(), NOW()
FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM products WHERE sku = 'DEMO2-SER060');
SET @p_serum := (SELECT id FROM products WHERE company_id = @c AND sku = 'DEMO2-SER060' LIMIT 1);
UPDATE products SET deleted_at = NULL, current_stock = 0 WHERE company_id = @c AND sku = 'DEMO2-ESM015' AND description = 'Datos de demostración' AND deleted_at IS NOT NULL AND @ok > 0;
INSERT INTO products (company_id, product_category_id, name, sku, description, unit, cost, price, min_stock, current_stock, active, created_at, updated_at)
SELECT @c, (SELECT id FROM product_categories WHERE company_id = @c AND name = 'CUIDADO PERSONAL' AND deleted_at IS NULL ORDER BY id LIMIT 1), 'ESMALTE DE UÑAS', 'DEMO2-ESM015', 'Datos de demostración', 'unidad', 15, 35, 5, 0, 1, NOW(), NOW()
FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM products WHERE sku = 'DEMO2-ESM015');
SET @p_esmalte := (SELECT id FROM products WHERE company_id = @c AND sku = 'DEMO2-ESM015' LIMIT 1);
UPDATE products SET deleted_at = NULL, current_stock = 0 WHERE company_id = @c AND sku = 'DEMO2-CRM100' AND description = 'Datos de demostración' AND deleted_at IS NOT NULL AND @ok > 0;
INSERT INTO products (company_id, product_category_id, name, sku, description, unit, cost, price, min_stock, current_stock, active, created_at, updated_at)
SELECT @c, (SELECT id FROM product_categories WHERE company_id = @c AND name = 'CUIDADO PERSONAL' AND deleted_at IS NULL ORDER BY id LIMIT 1), 'CREMA DE MANOS', 'DEMO2-CRM100', 'Datos de demostración', 'unidad', 20, 45, 5, 0, 1, NOW(), NOW()
FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM products WHERE sku = 'DEMO2-CRM100');
SET @p_crema := (SELECT id FROM products WHERE company_id = @c AND sku = 'DEMO2-CRM100' LIMIT 1);
INSERT INTO cargos (company_id, role_id, name, description, active, created_at, updated_at)
SELECT @c, (SELECT id FROM roles WHERE slug = 'employee' LIMIT 1), 'ESTILISTA', 'Datos de demostración', 1, NOW(), NOW()
FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM cargos WHERE company_id = @c AND name = 'ESTILISTA');
INSERT INTO cargos (company_id, role_id, name, description, active, created_at, updated_at)
SELECT @c, (SELECT id FROM roles WHERE slug = 'employee' LIMIT 1), 'MANICURISTA', 'Datos de demostración', 1, NOW(), NOW()
FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM cargos WHERE company_id = @c AND name = 'MANICURISTA');
INSERT INTO personals (company_id, cargo_id, full_name, phone, hire_date, notes, active, created_at, updated_at)
SELECT @c, (SELECT id FROM cargos WHERE company_id = @c AND name = 'ESTILISTA' LIMIT 1), 'ANA ROJAS', '77485510', DATE_SUB(CURDATE(), INTERVAL 8 MONTH), 'Datos de demostración', 1, NOW(), NOW()
FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM personals WHERE company_id = @c AND full_name = 'ANA ROJAS' AND deleted_at IS NULL);
SET @st_ana := (SELECT id FROM personals WHERE company_id = @c AND full_name = 'ANA ROJAS' AND deleted_at IS NULL ORDER BY id LIMIT 1);
INSERT INTO personals (company_id, cargo_id, full_name, phone, hire_date, notes, active, created_at, updated_at)
SELECT @c, (SELECT id FROM cargos WHERE company_id = @c AND name = 'ESTILISTA' LIMIT 1), 'CARLA MENDOZA', '75555726', DATE_SUB(CURDATE(), INTERVAL 8 MONTH), 'Datos de demostración', 1, NOW(), NOW()
FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM personals WHERE company_id = @c AND full_name = 'CARLA MENDOZA' AND deleted_at IS NULL);
SET @st_carla := (SELECT id FROM personals WHERE company_id = @c AND full_name = 'CARLA MENDOZA' AND deleted_at IS NULL ORDER BY id LIMIT 1);
INSERT INTO personals (company_id, cargo_id, full_name, phone, hire_date, notes, active, created_at, updated_at)
SELECT @c, (SELECT id FROM cargos WHERE company_id = @c AND name = 'MANICURISTA' LIMIT 1), 'LUCÍA VARGAS', '79365552', DATE_SUB(CURDATE(), INTERVAL 8 MONTH), 'Datos de demostración', 1, NOW(), NOW()
FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM personals WHERE company_id = @c AND full_name = 'LUCÍA VARGAS' AND deleted_at IS NULL);
SET @st_lucia := (SELECT id FROM personals WHERE company_id = @c AND full_name = 'LUCÍA VARGAS' AND deleted_at IS NULL ORDER BY id LIMIT 1);

-- Horario: todos los días de 09:00 a 19:00 (solo a quien no tiene horario)
INSERT INTO staff_schedules (company_id, personal_id, weekday, starts_time, ends_time, created_at, updated_at)
SELECT @c, @st_ana, w.d, '09:00:00', '19:00:00', NOW(), NOW() FROM (SELECT 1 d UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7) w
WHERE @ok > 0 AND @st_ana IS NOT NULL AND NOT EXISTS (SELECT 1 FROM staff_schedules WHERE personal_id = @st_ana);
INSERT INTO staff_schedules (company_id, personal_id, weekday, starts_time, ends_time, created_at, updated_at)
SELECT @c, @st_carla, w.d, '09:00:00', '19:00:00', NOW(), NOW() FROM (SELECT 1 d UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7) w
WHERE @ok > 0 AND @st_carla IS NOT NULL AND NOT EXISTS (SELECT 1 FROM staff_schedules WHERE personal_id = @st_carla);
INSERT INTO staff_schedules (company_id, personal_id, weekday, starts_time, ends_time, created_at, updated_at)
SELECT @c, @st_lucia, w.d, '09:00:00', '19:00:00', NOW(), NOW() FROM (SELECT 1 d UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7) w
WHERE @ok > 0 AND @st_lucia IS NOT NULL AND NOT EXISTS (SELECT 1 FROM staff_schedules WHERE personal_id = @st_lucia);
INSERT INTO staff_schedules (company_id, personal_id, weekday, starts_time, ends_time, created_at, updated_at)
SELECT @c, @pg, w.d, '09:00:00', '19:00:00', NOW(), NOW() FROM (SELECT 1 d UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6 UNION SELECT 7) w
WHERE @ok > 0 AND @pg IS NOT NULL AND NOT EXISTS (SELECT 1 FROM staff_schedules WHERE personal_id = @pg);

INSERT INTO clients (company_id, full_name, phone, source, active, created_by, created_at, updated_at)
SELECT @c, 'MARÍA FERNÁNDEZ', '71975454', 'Datos de demostración', 1, @u, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 49 DAY), '10:00:00'), NOW()
FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM clients WHERE company_id = @c AND full_name = 'MARÍA FERNÁNDEZ' AND deleted_at IS NULL);
SET @c0 := (SELECT id FROM clients WHERE company_id = @c AND full_name = 'MARÍA FERNÁNDEZ' AND deleted_at IS NULL ORDER BY id LIMIT 1);
INSERT INTO clients (company_id, full_name, phone, source, active, created_by, created_at, updated_at)
SELECT @c, 'VALERIA GUTIÉRREZ', '77684526', 'Datos de demostración', 1, @u, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 94 DAY), '10:00:00'), NOW()
FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM clients WHERE company_id = @c AND full_name = 'VALERIA GUTIÉRREZ' AND deleted_at IS NULL);
SET @c1 := (SELECT id FROM clients WHERE company_id = @c AND full_name = 'VALERIA GUTIÉRREZ' AND deleted_at IS NULL ORDER BY id LIMIT 1);
INSERT INTO clients (company_id, full_name, phone, source, active, created_by, created_at, updated_at)
SELECT @c, 'CAMILA ROCHA', '76067435', 'Datos de demostración', 1, @u, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 50 DAY), '10:00:00'), NOW()
FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM clients WHERE company_id = @c AND full_name = 'CAMILA ROCHA' AND deleted_at IS NULL);
SET @c2 := (SELECT id FROM clients WHERE company_id = @c AND full_name = 'CAMILA ROCHA' AND deleted_at IS NULL ORDER BY id LIMIT 1);
INSERT INTO clients (company_id, full_name, phone, source, active, created_by, created_at, updated_at)
SELECT @c, 'DANIELA QUISPE', '79352743', 'Datos de demostración', 1, @u, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 111 DAY), '10:00:00'), NOW()
FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM clients WHERE company_id = @c AND full_name = 'DANIELA QUISPE' AND deleted_at IS NULL);
SET @c3 := (SELECT id FROM clients WHERE company_id = @c AND full_name = 'DANIELA QUISPE' AND deleted_at IS NULL ORDER BY id LIMIT 1);
INSERT INTO clients (company_id, full_name, phone, source, active, created_by, created_at, updated_at)
SELECT @c, 'GABRIELA SUÁREZ', '74195654', 'Datos de demostración', 1, @u, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 103 DAY), '10:00:00'), NOW()
FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM clients WHERE company_id = @c AND full_name = 'GABRIELA SUÁREZ' AND deleted_at IS NULL);
SET @c4 := (SELECT id FROM clients WHERE company_id = @c AND full_name = 'GABRIELA SUÁREZ' AND deleted_at IS NULL ORDER BY id LIMIT 1);
INSERT INTO clients (company_id, full_name, phone, source, active, created_by, created_at, updated_at)
SELECT @c, 'SOFÍA MAMANI', '75801664', 'Datos de demostración', 1, @u, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 66 DAY), '10:00:00'), NOW()
FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM clients WHERE company_id = @c AND full_name = 'SOFÍA MAMANI' AND deleted_at IS NULL);
SET @c5 := (SELECT id FROM clients WHERE company_id = @c AND full_name = 'SOFÍA MAMANI' AND deleted_at IS NULL ORDER BY id LIMIT 1);
INSERT INTO clients (company_id, full_name, phone, source, active, created_by, created_at, updated_at)
SELECT @c, 'PAOLA JUSTINIANO', '78314270', 'Datos de demostración', 1, @u, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 118 DAY), '10:00:00'), NOW()
FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM clients WHERE company_id = @c AND full_name = 'PAOLA JUSTINIANO' AND deleted_at IS NULL);
SET @c6 := (SELECT id FROM clients WHERE company_id = @c AND full_name = 'PAOLA JUSTINIANO' AND deleted_at IS NULL ORDER BY id LIMIT 1);
INSERT INTO clients (company_id, full_name, phone, source, active, created_by, created_at, updated_at)
SELECT @c, 'ANDREA CHÁVEZ', '78247420', 'Datos de demostración', 1, @u, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 35 DAY), '10:00:00'), NOW()
FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM clients WHERE company_id = @c AND full_name = 'ANDREA CHÁVEZ' AND deleted_at IS NULL);
SET @c7 := (SELECT id FROM clients WHERE company_id = @c AND full_name = 'ANDREA CHÁVEZ' AND deleted_at IS NULL ORDER BY id LIMIT 1);
INSERT INTO clients (company_id, full_name, phone, source, active, created_by, created_at, updated_at)
SELECT @c, 'NATALIA ORTIZ', '74785775', 'Datos de demostración', 1, @u, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 88 DAY), '10:00:00'), NOW()
FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM clients WHERE company_id = @c AND full_name = 'NATALIA ORTIZ' AND deleted_at IS NULL);
SET @c8 := (SELECT id FROM clients WHERE company_id = @c AND full_name = 'NATALIA ORTIZ' AND deleted_at IS NULL ORDER BY id LIMIT 1);
INSERT INTO clients (company_id, full_name, phone, source, active, created_by, created_at, updated_at)
SELECT @c, 'FERNANDA VACA', '72130355', 'Datos de demostración', 1, @u, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 75 DAY), '10:00:00'), NOW()
FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM clients WHERE company_id = @c AND full_name = 'FERNANDA VACA' AND deleted_at IS NULL);
SET @c9 := (SELECT id FROM clients WHERE company_id = @c AND full_name = 'FERNANDA VACA' AND deleted_at IS NULL ORDER BY id LIMIT 1);
INSERT INTO clients (company_id, full_name, phone, source, active, created_by, created_at, updated_at)
SELECT @c, 'LORENA AGUILERA', '72846899', 'Datos de demostración', 1, @u, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 58 DAY), '10:00:00'), NOW()
FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM clients WHERE company_id = @c AND full_name = 'LORENA AGUILERA' AND deleted_at IS NULL);
SET @c10 := (SELECT id FROM clients WHERE company_id = @c AND full_name = 'LORENA AGUILERA' AND deleted_at IS NULL ORDER BY id LIMIT 1);
INSERT INTO clients (company_id, full_name, phone, source, active, created_by, created_at, updated_at)
SELECT @c, 'CAROLINA PEÑA', '77574388', 'Datos de demostración', 1, @u, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 49 DAY), '10:00:00'), NOW()
FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM clients WHERE company_id = @c AND full_name = 'CAROLINA PEÑA' AND deleted_at IS NULL);
SET @c11 := (SELECT id FROM clients WHERE company_id = @c AND full_name = 'CAROLINA PEÑA' AND deleted_at IS NULL ORDER BY id LIMIT 1);
INSERT INTO clients (company_id, full_name, phone, source, active, created_by, created_at, updated_at)
SELECT @c, 'JIMENA SALAZAR', '76981164', 'Datos de demostración', 1, @u, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 111 DAY), '10:00:00'), NOW()
FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM clients WHERE company_id = @c AND full_name = 'JIMENA SALAZAR' AND deleted_at IS NULL);
SET @c12 := (SELECT id FROM clients WHERE company_id = @c AND full_name = 'JIMENA SALAZAR' AND deleted_at IS NULL ORDER BY id LIMIT 1);
INSERT INTO clients (company_id, full_name, phone, source, active, created_by, created_at, updated_at)
SELECT @c, 'ROXANA FLORES', '71429268', 'Datos de demostración', 1, @u, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 48 DAY), '10:00:00'), NOW()
FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM clients WHERE company_id = @c AND full_name = 'ROXANA FLORES' AND deleted_at IS NULL);
SET @c13 := (SELECT id FROM clients WHERE company_id = @c AND full_name = 'ROXANA FLORES' AND deleted_at IS NULL ORDER BY id LIMIT 1);
INSERT INTO clients (company_id, full_name, phone, source, active, created_by, created_at, updated_at)
SELECT @c, 'PATRICIA MORALES', '75221778', 'Datos de demostración', 1, @u, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 119 DAY), '10:00:00'), NOW()
FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM clients WHERE company_id = @c AND full_name = 'PATRICIA MORALES' AND deleted_at IS NULL);
SET @c14 := (SELECT id FROM clients WHERE company_id = @c AND full_name = 'PATRICIA MORALES' AND deleted_at IS NULL ORDER BY id LIMIT 1);
INSERT INTO clients (company_id, full_name, phone, source, active, created_by, created_at, updated_at)
SELECT @c, 'ISABEL CUÉLLAR', '77099251', 'Datos de demostración', 1, @u, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 46 DAY), '10:00:00'), NOW()
FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM clients WHERE company_id = @c AND full_name = 'ISABEL CUÉLLAR' AND deleted_at IS NULL);
SET @c15 := (SELECT id FROM clients WHERE company_id = @c AND full_name = 'ISABEL CUÉLLAR' AND deleted_at IS NULL ORDER BY id LIMIT 1);
INSERT INTO clients (company_id, full_name, phone, source, active, created_by, created_at, updated_at)
SELECT @c, 'MÓNICA RIBERA', '74537594', 'Datos de demostración', 1, @u, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 22 DAY), '10:00:00'), NOW()
FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM clients WHERE company_id = @c AND full_name = 'MÓNICA RIBERA' AND deleted_at IS NULL);
SET @c16 := (SELECT id FROM clients WHERE company_id = @c AND full_name = 'MÓNICA RIBERA' AND deleted_at IS NULL ORDER BY id LIMIT 1);
INSERT INTO clients (company_id, full_name, phone, source, active, created_by, created_at, updated_at)
SELECT @c, 'VERÓNICA TORREZ', '72301248', 'Datos de demostración', 1, @u, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 86 DAY), '10:00:00'), NOW()
FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM clients WHERE company_id = @c AND full_name = 'VERÓNICA TORREZ' AND deleted_at IS NULL);
SET @c17 := (SELECT id FROM clients WHERE company_id = @c AND full_name = 'VERÓNICA TORREZ' AND deleted_at IS NULL ORDER BY id LIMIT 1);
INSERT INTO clients (company_id, full_name, phone, source, active, created_by, created_at, updated_at)
SELECT @c, 'ALEJANDRA LIMA', '76020415', 'Datos de demostración', 1, @u, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '10:00:00'), NOW()
FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM clients WHERE company_id = @c AND full_name = 'ALEJANDRA LIMA' AND deleted_at IS NULL);
SET @c18 := (SELECT id FROM clients WHERE company_id = @c AND full_name = 'ALEJANDRA LIMA' AND deleted_at IS NULL ORDER BY id LIMIT 1);
INSERT INTO clients (company_id, full_name, phone, source, active, created_by, created_at, updated_at)
SELECT @c, 'KARLA MONTAÑO', '73824028', 'Datos de demostración', 1, @u, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 6 DAY), '10:00:00'), NOW()
FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM clients WHERE company_id = @c AND full_name = 'KARLA MONTAÑO' AND deleted_at IS NULL);
SET @c19 := (SELECT id FROM clients WHERE company_id = @c AND full_name = 'KARLA MONTAÑO' AND deleted_at IS NULL ORDER BY id LIMIT 1);
INSERT INTO clients (company_id, full_name, phone, source, active, created_by, created_at, updated_at)
SELECT @c, 'DIEGO ARANCIBIA', '79157580', 'Datos de demostración', 1, @u, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 7 DAY), '10:00:00'), NOW()
FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM clients WHERE company_id = @c AND full_name = 'DIEGO ARANCIBIA' AND deleted_at IS NULL);
SET @c20 := (SELECT id FROM clients WHERE company_id = @c AND full_name = 'DIEGO ARANCIBIA' AND deleted_at IS NULL ORDER BY id LIMIT 1);
INSERT INTO clients (company_id, full_name, phone, source, active, created_by, created_at, updated_at)
SELECT @c, 'JORGE PARADA', '71780859', 'Datos de demostración', 1, @u, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 9 DAY), '10:00:00'), NOW()
FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM clients WHERE company_id = @c AND full_name = 'JORGE PARADA' AND deleted_at IS NULL);
SET @c21 := (SELECT id FROM clients WHERE company_id = @c AND full_name = 'JORGE PARADA' AND deleted_at IS NULL ORDER BY id LIMIT 1);
INSERT INTO clients (company_id, full_name, phone, source, active, created_by, created_at, updated_at)
SELECT @c, 'LUIS ANTELO', '76320127', 'Datos de demostración', 1, @u, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 8 DAY), '10:00:00'), NOW()
FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM clients WHERE company_id = @c AND full_name = 'LUIS ANTELO' AND deleted_at IS NULL);
SET @c22 := (SELECT id FROM clients WHERE company_id = @c AND full_name = 'LUIS ANTELO' AND deleted_at IS NULL ORDER BY id LIMIT 1);
INSERT INTO clients (company_id, full_name, phone, source, active, created_by, created_at, updated_at)
SELECT @c, 'RICARDO SORIA', '78833171', 'Datos de demostración', 1, @u, TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 2 DAY), '10:00:00'), NOW()
FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM clients WHERE company_id = @c AND full_name = 'RICARDO SORIA' AND deleted_at IS NULL);
SET @c23 := (SELECT id FROM clients WHERE company_id = @c AND full_name = 'RICARDO SORIA' AND deleted_at IS NULL ORDER BY id LIMIT 1);
INSERT INTO cash_registers (company_id, name, code, active, created_by, created_at, updated_at)
SELECT @c, 'CAJA PRINCIPAL', 'CJ-01', 1, @u, NOW(), NOW() FROM DUAL WHERE @ok > 0 AND NOT EXISTS (SELECT 1 FROM cash_registers WHERE company_id = @c AND name = 'CAJA PRINCIPAL' AND deleted_at IS NULL);
SET @caja := (SELECT id FROM cash_registers WHERE company_id = @c AND name = 'CAJA PRINCIPAL' AND deleted_at IS NULL ORDER BY id LIMIT 1);
-- Si la caja ya tiene un turno abierto (real), no se inserta la actividad para no mezclar arqueos.
SET @run := IF(@run = 1 AND NOT EXISTS (SELECT 1 FROM cash_sessions WHERE cash_register_id = @caja AND status = 'open'), 1, 0);

-- Inventario inicial: 30 unidades de cada producto
UPDATE products SET current_stock = current_stock + 30 WHERE id = @p_shampoo AND @run = 1;
INSERT INTO inventory_movements (company_id, product_id, user_id, type, quantity, unit_cost, reference, notes, movement_date, created_at, updated_at)
SELECT @c, @p_shampoo, @u, 'entrada', 30, 45, 'STOCK INICIAL', 'Datos de demostración', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '09:00:00'), NOW(), NOW() FROM DUAL WHERE @run = 1;
UPDATE products SET current_stock = current_stock + 30 WHERE id = @p_acond AND @run = 1;
INSERT INTO inventory_movements (company_id, product_id, user_id, type, quantity, unit_cost, reference, notes, movement_date, created_at, updated_at)
SELECT @c, @p_acond, @u, 'entrada', 30, 45, 'STOCK INICIAL', 'Datos de demostración', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '09:00:00'), NOW(), NOW() FROM DUAL WHERE @run = 1;
UPDATE products SET current_stock = current_stock + 30 WHERE id = @p_mascar AND @run = 1;
INSERT INTO inventory_movements (company_id, product_id, user_id, type, quantity, unit_cost, reference, notes, movement_date, created_at, updated_at)
SELECT @c, @p_mascar, @u, 'entrada', 30, 60, 'STOCK INICIAL', 'Datos de demostración', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '09:00:00'), NOW(), NOW() FROM DUAL WHERE @run = 1;
UPDATE products SET current_stock = current_stock + 30 WHERE id = @p_serum AND @run = 1;
INSERT INTO inventory_movements (company_id, product_id, user_id, type, quantity, unit_cost, reference, notes, movement_date, created_at, updated_at)
SELECT @c, @p_serum, @u, 'entrada', 30, 55, 'STOCK INICIAL', 'Datos de demostración', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '09:00:00'), NOW(), NOW() FROM DUAL WHERE @run = 1;
UPDATE products SET current_stock = current_stock + 30 WHERE id = @p_esmalte AND @run = 1;
INSERT INTO inventory_movements (company_id, product_id, user_id, type, quantity, unit_cost, reference, notes, movement_date, created_at, updated_at)
SELECT @c, @p_esmalte, @u, 'entrada', 30, 15, 'STOCK INICIAL', 'Datos de demostración', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '09:00:00'), NOW(), NOW() FROM DUAL WHERE @run = 1;
UPDATE products SET current_stock = current_stock + 30 WHERE id = @p_crema AND @run = 1;
INSERT INTO inventory_movements (company_id, product_id, user_id, type, quantity, unit_cost, reference, notes, movement_date, created_at, updated_at)
SELECT @c, @p_crema, @u, 'entrada', 30, 20, 'STOCK INICIAL', 'Datos de demostración', TIMESTAMP(DATE_SUB(CURDATE(), INTERVAL 12 DAY), '09:00:00'), NOW(), NOW() FROM DUAL WHERE @run = 1;

-- ── Día CURDATE() - 10 ──
SET @d := DATE_SUB(CURDATE(), INTERVAL 10 DAY);
INSERT INTO cash_sessions (cash_register_id, personal_id, opening_amount, status, opened_at, notes, opened_by, created_at, updated_at)
SELECT @caja, @pg, 200, 'open', TIMESTAMP(@d, '08:50:00'), 'Datos de demostración', @u, TIMESTAMP(@d, '08:50:00'), TIMESTAMP(@d, '08:50:00') FROM DUAL WHERE @run = 1;
SET @cs := LAST_INSERT_ID();
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c11, @st_ana, @s_corte_dama, TIMESTAMP(@d, '09:00:00'), TIMESTAMP(@d, '09:45:00'), 'completada', 0, 80, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 3 DAY), '12:00:00'), TIMESTAMP(@d, '09:45:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c3, @st_carla, @s_keratina, TIMESTAMP(@d, '09:00:00'), TIMESTAMP(@d, '11:00:00'), 'no_show', 0, 300, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 4 DAY), '12:00:00'), TIMESTAMP(@d, '11:00:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c21, @st_lucia, @s_facial, TIMESTAMP(@d, '09:30:00'), TIMESTAMP(@d, '10:30:00'), 'completada', 0, 180, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 4 DAY), '12:00:00'), TIMESTAMP(@d, '10:30:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c16, @st_ana, @s_corte_dama, TIMESTAMP(@d, '10:30:00'), TIMESTAMP(@d, '11:15:00'), 'completada', 0, 80, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 4 DAY), '12:00:00'), TIMESTAMP(@d, '11:15:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c11, @st_lucia, @s_cejas, TIMESTAMP(@d, '11:15:00'), TIMESTAMP(@d, '11:35:00'), 'completada', 0, 40, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 4 DAY), '12:00:00'), TIMESTAMP(@d, '11:35:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c10, @st_carla, @s_cejas, TIMESTAMP(@d, '11:45:00'), TIMESTAMP(@d, '12:05:00'), 'completada', 0, 40, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 2 DAY), '12:00:00'), TIMESTAMP(@d, '12:05:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c6, @st_ana, @s_keratina, TIMESTAMP(@d, '12:15:00'), TIMESTAMP(@d, '14:15:00'), 'completada', 0, 300, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 1 DAY), '12:00:00'), TIMESTAMP(@d, '14:15:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c11, @st_ana, @cs, @num, TIMESTAMP(@d, '09:50:00'), 80.00, 0, 0, 80.00, 0.00, 'transferencia', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '09:50:00'), TIMESTAMP(@d, '09:50:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_corte_dama, NULL, @st_ana, 'CORTE DAMA', 1, 80.00, 0, 80.00, TIMESTAMP(@d, '09:50:00'), TIMESTAMP(@d, '09:50:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 80.00, CONCAT('Venta ', @num), @d, 'transferencia', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '09:50:00'), TIMESTAMP(@d, '09:50:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c21, @st_lucia, @cs, @num, TIMESTAMP(@d, '10:35:00'), 265.00, 0, 0, 265.00, 0.00, 'tarjeta', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '10:35:00'), TIMESTAMP(@d, '10:35:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_facial, NULL, @st_lucia, 'LIMPIEZA FACIAL', 1, 180.00, 0, 180.00, TIMESTAMP(@d, '10:35:00'), TIMESTAMP(@d, '10:35:00') FROM DUAL WHERE @run = 1;
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, NULL, @p_acond, @st_lucia, 'ACONDICIONADOR 400ML', 1, 85.00, 0, 85.00, TIMESTAMP(@d, '10:35:00'), TIMESTAMP(@d, '10:35:00') FROM DUAL WHERE @run = 1;
UPDATE products SET current_stock = current_stock - 1 WHERE id = @p_acond AND @run = 1;
INSERT INTO inventory_movements (company_id, product_id, user_id, type, quantity, unit_cost, reference, notes, movement_date, created_at, updated_at)
SELECT @c, @p_acond, @u, 'salida', 1, 45, @num, CONCAT('Venta ', @num, ' · ', 'Datos de demostración'), TIMESTAMP(@d, '10:35:00'), NOW(), NOW() FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 265.00, CONCAT('Venta ', @num), @d, 'tarjeta', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '10:35:00'), TIMESTAMP(@d, '10:35:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c16, @st_ana, @cs, @num, TIMESTAMP(@d, '11:20:00'), 80.00, 0, 0, 80.00, 10.00, 'tarjeta', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '11:20:00'), TIMESTAMP(@d, '11:20:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_corte_dama, NULL, @st_ana, 'CORTE DAMA', 1, 80.00, 0, 80.00, TIMESTAMP(@d, '11:20:00'), TIMESTAMP(@d, '11:20:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 80.00, CONCAT('Venta ', @num), @d, 'tarjeta', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '11:20:00'), TIMESTAMP(@d, '11:20:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, category, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 10.00, CONCAT('Propina venta ', @num), 'Propinas', @d, 'tarjeta', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '11:20:00'), TIMESTAMP(@d, '11:20:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c11, @st_lucia, @cs, @num, TIMESTAMP(@d, '11:40:00'), 40.00, 0, 0, 40.00, 0.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '11:40:00'), TIMESTAMP(@d, '11:40:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_cejas, NULL, @st_lucia, 'DISEÑO DE CEJAS', 1, 40.00, 0, 40.00, TIMESTAMP(@d, '11:40:00'), TIMESTAMP(@d, '11:40:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 40.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '11:40:00'), TIMESTAMP(@d, '11:40:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c15, NULL, @cs, @num, TIMESTAMP(@d, '12:00:00'), 170.00, 0, 0, 170.00, 0.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '12:00:00'), TIMESTAMP(@d, '12:00:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, NULL, @p_acond, NULL, 'ACONDICIONADOR 400ML', 2, 85.00, 0, 170.00, TIMESTAMP(@d, '12:00:00'), TIMESTAMP(@d, '12:00:00') FROM DUAL WHERE @run = 1;
UPDATE products SET current_stock = current_stock - 2 WHERE id = @p_acond AND @run = 1;
INSERT INTO inventory_movements (company_id, product_id, user_id, type, quantity, unit_cost, reference, notes, movement_date, created_at, updated_at)
SELECT @c, @p_acond, @u, 'salida', 2, 45, @num, CONCAT('Venta ', @num, ' · ', 'Datos de demostración'), TIMESTAMP(@d, '12:00:00'), NOW(), NOW() FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 170.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '12:00:00'), TIMESTAMP(@d, '12:00:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c10, @st_carla, @cs, @num, TIMESTAMP(@d, '12:10:00'), 40.00, 0, 0, 40.00, 0.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '12:10:00'), TIMESTAMP(@d, '12:10:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_cejas, NULL, @st_carla, 'DISEÑO DE CEJAS', 1, 40.00, 0, 40.00, TIMESTAMP(@d, '12:10:00'), TIMESTAMP(@d, '12:10:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 40.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '12:10:00'), TIMESTAMP(@d, '12:10:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c6, @st_ana, @cs, @num, TIMESTAMP(@d, '14:20:00'), 300.00, 0, 0, 300.00, 0.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '14:20:00'), TIMESTAMP(@d, '14:20:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_keratina, NULL, @st_ana, 'TRATAMIENTO DE KERATINA', 1, 300.00, 0, 300.00, TIMESTAMP(@d, '14:20:00'), TIMESTAMP(@d, '14:20:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 300.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '14:20:00'), TIMESTAMP(@d, '14:20:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, category, movement_date, payment_method, created_by, created_at, updated_at)
SELECT @c, @cs, 'egreso', 25.00, 'Taxi para compra de insumos', 'Transporte', @d, 'efectivo', @u, TIMESTAMP(@d, '13:00:00'), TIMESTAMP(@d, '13:00:00') FROM DUAL WHERE @run = 1;
UPDATE cash_sessions SET opened_at = opened_at, status = 'closed', closed_at = TIMESTAMP(@d, '19:20:00'), expected_amount = 1160.00, closing_amount = 1160.00, difference = 0.00, closed_by = @u, updated_at = TIMESTAMP(@d, '19:20:00') WHERE id = @cs AND @run = 1;
INSERT INTO attendances (company_id, personal_id, work_date, check_in, check_out, in_source, out_source, in_marked_by, out_marked_by, scheduled_start, late_minutes, worked_minutes, notes, created_at, updated_at)
SELECT @c, @st_ana, @d, TIMESTAMP(@d, '08:57:00'), TIMESTAMP(@d, '19:10:00'), 'self', 'self', @u, @u, '09:00:00', 0, 613, 'Datos de demostración', TIMESTAMP(@d, '08:57:00'), TIMESTAMP(@d, '19:10:00') FROM DUAL WHERE @run = 1 AND @st_ana IS NOT NULL;
INSERT INTO attendances (company_id, personal_id, work_date, check_in, check_out, in_source, out_source, in_marked_by, out_marked_by, scheduled_start, late_minutes, worked_minutes, notes, created_at, updated_at)
SELECT @c, @st_lucia, @d, TIMESTAMP(@d, '09:20:00'), TIMESTAMP(@d, '19:12:00'), 'self', 'self', @u, @u, '09:00:00', 20, 592, 'Datos de demostración', TIMESTAMP(@d, '09:20:00'), TIMESTAMP(@d, '19:12:00') FROM DUAL WHERE @run = 1 AND @st_lucia IS NOT NULL;
INSERT INTO attendances (company_id, personal_id, work_date, check_in, check_out, in_source, out_source, in_marked_by, out_marked_by, scheduled_start, late_minutes, worked_minutes, notes, created_at, updated_at)
SELECT @c, @pg, @d, TIMESTAMP(@d, '09:18:00'), TIMESTAMP(@d, '19:01:00'), 'self', 'self', @u, @u, '09:00:00', 18, 583, 'Datos de demostración', TIMESTAMP(@d, '09:18:00'), TIMESTAMP(@d, '19:01:00') FROM DUAL WHERE @run = 1 AND @pg IS NOT NULL;

-- ── Día CURDATE() - 9 ──
SET @d := DATE_SUB(CURDATE(), INTERVAL 9 DAY);
INSERT INTO cash_sessions (cash_register_id, personal_id, opening_amount, status, opened_at, notes, opened_by, created_at, updated_at)
SELECT @caja, @pg, 200, 'open', TIMESTAMP(@d, '08:50:00'), 'Datos de demostración', @u, TIMESTAMP(@d, '08:50:00'), TIMESTAMP(@d, '08:50:00') FROM DUAL WHERE @run = 1;
SET @cs := LAST_INSERT_ID();
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c14, @st_lucia, @s_pedicure, TIMESTAMP(@d, '09:00:00'), TIMESTAMP(@d, '10:00:00'), 'completada', 0, 80, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 5 DAY), '12:00:00'), TIMESTAMP(@d, '10:00:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c5, @st_carla, @s_facial, TIMESTAMP(@d, '09:15:00'), TIMESTAMP(@d, '10:15:00'), 'completada', 0, 180, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 4 DAY), '12:00:00'), TIMESTAMP(@d, '10:15:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c5, @st_ana, @s_corte_dama, TIMESTAMP(@d, '09:30:00'), TIMESTAMP(@d, '10:15:00'), 'completada', 0, 80, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 1 DAY), '12:00:00'), TIMESTAMP(@d, '10:15:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c21, @st_ana, @s_mechas, TIMESTAMP(@d, '10:15:00'), TIMESTAMP(@d, '12:45:00'), 'completada', 0, 380, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 1 DAY), '12:00:00'), TIMESTAMP(@d, '12:45:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c21, @st_carla, @s_cejas, TIMESTAMP(@d, '10:30:00'), TIMESTAMP(@d, '10:50:00'), 'completada', 0, 40, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 3 DAY), '12:00:00'), TIMESTAMP(@d, '10:50:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c10, @st_carla, @s_mechas, TIMESTAMP(@d, '10:50:00'), TIMESTAMP(@d, '13:20:00'), 'completada', 0, 380, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 4 DAY), '12:00:00'), TIMESTAMP(@d, '13:20:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c19, @st_lucia, @s_cejas, TIMESTAMP(@d, '11:00:00'), TIMESTAMP(@d, '11:20:00'), 'completada', 0, 40, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 4 DAY), '12:00:00'), TIMESTAMP(@d, '11:20:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c19, @st_lucia, @s_semi, TIMESTAMP(@d, '11:20:00'), TIMESTAMP(@d, '12:20:00'), 'cancelada', 0, 120, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 4 DAY), '12:00:00'), TIMESTAMP(@d, '12:20:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c22, @st_ana, @s_mechas, TIMESTAMP(@d, '13:30:00'), TIMESTAMP(@d, '16:00:00'), 'completada', 0, 380, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 3 DAY), '12:00:00'), TIMESTAMP(@d, '16:00:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c14, @st_lucia, @cs, @num, TIMESTAMP(@d, '10:05:00'), 80.00, 0, 0, 80.00, 0.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '10:05:00'), TIMESTAMP(@d, '10:05:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_pedicure, NULL, @st_lucia, 'PEDICURE', 1, 80.00, 0, 80.00, TIMESTAMP(@d, '10:05:00'), TIMESTAMP(@d, '10:05:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 80.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '10:05:00'), TIMESTAMP(@d, '10:05:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c5, @st_carla, @cs, @num, TIMESTAMP(@d, '10:20:00'), 300.00, 0, 0, 300.00, 0.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '10:20:00'), TIMESTAMP(@d, '10:20:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_facial, NULL, @st_carla, 'LIMPIEZA FACIAL', 1, 180.00, 0, 180.00, TIMESTAMP(@d, '10:20:00'), TIMESTAMP(@d, '10:20:00') FROM DUAL WHERE @run = 1;
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, NULL, @p_mascar, @st_carla, 'MASCARILLA CAPILAR 250G', 1, 120.00, 0, 120.00, TIMESTAMP(@d, '10:20:00'), TIMESTAMP(@d, '10:20:00') FROM DUAL WHERE @run = 1;
UPDATE products SET current_stock = current_stock - 1 WHERE id = @p_mascar AND @run = 1;
INSERT INTO inventory_movements (company_id, product_id, user_id, type, quantity, unit_cost, reference, notes, movement_date, created_at, updated_at)
SELECT @c, @p_mascar, @u, 'salida', 1, 60, @num, CONCAT('Venta ', @num, ' · ', 'Datos de demostración'), TIMESTAMP(@d, '10:20:00'), NOW(), NOW() FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 300.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '10:20:00'), TIMESTAMP(@d, '10:20:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c5, @st_ana, @cs, @num, TIMESTAMP(@d, '10:20:00'), 80.00, 0, 0, 80.00, 0.00, 'qr', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '10:20:00'), TIMESTAMP(@d, '10:20:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_corte_dama, NULL, @st_ana, 'CORTE DAMA', 1, 80.00, 0, 80.00, TIMESTAMP(@d, '10:20:00'), TIMESTAMP(@d, '10:20:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 80.00, CONCAT('Venta ', @num), @d, 'qr', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '10:20:00'), TIMESTAMP(@d, '10:20:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c21, @st_carla, @cs, @num, TIMESTAMP(@d, '10:55:00'), 125.00, 0, 0, 125.00, 20.00, 'transferencia', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '10:55:00'), TIMESTAMP(@d, '10:55:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_cejas, NULL, @st_carla, 'DISEÑO DE CEJAS', 1, 40.00, 0, 40.00, TIMESTAMP(@d, '10:55:00'), TIMESTAMP(@d, '10:55:00') FROM DUAL WHERE @run = 1;
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, NULL, @p_acond, @st_carla, 'ACONDICIONADOR 400ML', 1, 85.00, 0, 85.00, TIMESTAMP(@d, '10:55:00'), TIMESTAMP(@d, '10:55:00') FROM DUAL WHERE @run = 1;
UPDATE products SET current_stock = current_stock - 1 WHERE id = @p_acond AND @run = 1;
INSERT INTO inventory_movements (company_id, product_id, user_id, type, quantity, unit_cost, reference, notes, movement_date, created_at, updated_at)
SELECT @c, @p_acond, @u, 'salida', 1, 45, @num, CONCAT('Venta ', @num, ' · ', 'Datos de demostración'), TIMESTAMP(@d, '10:55:00'), NOW(), NOW() FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 125.00, CONCAT('Venta ', @num), @d, 'transferencia', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '10:55:00'), TIMESTAMP(@d, '10:55:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, category, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 20.00, CONCAT('Propina venta ', @num), 'Propinas', @d, 'transferencia', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '10:55:00'), TIMESTAMP(@d, '10:55:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c19, @st_lucia, @cs, @num, TIMESTAMP(@d, '11:25:00'), 40.00, 0, 0, 40.00, 0.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '11:25:00'), TIMESTAMP(@d, '11:25:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_cejas, NULL, @st_lucia, 'DISEÑO DE CEJAS', 1, 40.00, 0, 40.00, TIMESTAMP(@d, '11:25:00'), TIMESTAMP(@d, '11:25:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 40.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '11:25:00'), TIMESTAMP(@d, '11:25:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c21, @st_ana, @cs, @num, TIMESTAMP(@d, '12:50:00'), 425.00, 0, 0, 425.00, 0.00, 'transferencia', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '12:50:00'), TIMESTAMP(@d, '12:50:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_mechas, NULL, @st_ana, 'MECHAS / BALAYAGE', 1, 380.00, 0, 380.00, TIMESTAMP(@d, '12:50:00'), TIMESTAMP(@d, '12:50:00') FROM DUAL WHERE @run = 1;
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, NULL, @p_crema, @st_ana, 'CREMA DE MANOS', 1, 45.00, 0, 45.00, TIMESTAMP(@d, '12:50:00'), TIMESTAMP(@d, '12:50:00') FROM DUAL WHERE @run = 1;
UPDATE products SET current_stock = current_stock - 1 WHERE id = @p_crema AND @run = 1;
INSERT INTO inventory_movements (company_id, product_id, user_id, type, quantity, unit_cost, reference, notes, movement_date, created_at, updated_at)
SELECT @c, @p_crema, @u, 'salida', 1, 20, @num, CONCAT('Venta ', @num, ' · ', 'Datos de demostración'), TIMESTAMP(@d, '12:50:00'), NOW(), NOW() FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 425.00, CONCAT('Venta ', @num), @d, 'transferencia', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '12:50:00'), TIMESTAMP(@d, '12:50:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c10, @st_carla, @cs, @num, TIMESTAMP(@d, '13:25:00'), 380.00, 0, 0, 380.00, 0.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '13:25:00'), TIMESTAMP(@d, '13:25:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_mechas, NULL, @st_carla, 'MECHAS / BALAYAGE', 1, 380.00, 0, 380.00, TIMESTAMP(@d, '13:25:00'), TIMESTAMP(@d, '13:25:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 380.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '13:25:00'), TIMESTAMP(@d, '13:25:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c22, @st_ana, @cs, @num, TIMESTAMP(@d, '16:05:00'), 380.00, 0, 0, 380.00, 20.00, 'tarjeta', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '16:05:00'), TIMESTAMP(@d, '16:05:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_mechas, NULL, @st_ana, 'MECHAS / BALAYAGE', 1, 380.00, 0, 380.00, TIMESTAMP(@d, '16:05:00'), TIMESTAMP(@d, '16:05:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 380.00, CONCAT('Venta ', @num), @d, 'tarjeta', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '16:05:00'), TIMESTAMP(@d, '16:05:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, category, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 20.00, CONCAT('Propina venta ', @num), 'Propinas', @d, 'tarjeta', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '16:05:00'), TIMESTAMP(@d, '16:05:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, category, movement_date, payment_method, created_by, created_at, updated_at)
SELECT @c, @cs, 'egreso', 60.00, 'Algodón y toallas desechables', 'Insumos', @d, 'efectivo', @u, TIMESTAMP(@d, '17:00:00'), TIMESTAMP(@d, '17:00:00') FROM DUAL WHERE @run = 1;
UPDATE cash_sessions SET opened_at = opened_at, status = 'closed', closed_at = TIMESTAMP(@d, '19:20:00'), expected_amount = 1990.00, closing_amount = 1990.00, difference = 0.00, closed_by = @u, updated_at = TIMESTAMP(@d, '19:20:00') WHERE id = @cs AND @run = 1;
INSERT INTO attendances (company_id, personal_id, work_date, check_in, check_out, in_source, out_source, in_marked_by, out_marked_by, scheduled_start, late_minutes, worked_minutes, notes, created_at, updated_at)
SELECT @c, @st_ana, @d, TIMESTAMP(@d, '09:16:00'), TIMESTAMP(@d, '19:02:00'), 'self', 'self', @u, @u, '09:00:00', 16, 586, 'Datos de demostración', TIMESTAMP(@d, '09:16:00'), TIMESTAMP(@d, '19:02:00') FROM DUAL WHERE @run = 1 AND @st_ana IS NOT NULL;
INSERT INTO attendances (company_id, personal_id, work_date, check_in, check_out, in_source, out_source, in_marked_by, out_marked_by, scheduled_start, late_minutes, worked_minutes, notes, created_at, updated_at)
SELECT @c, @st_carla, @d, TIMESTAMP(@d, '08:46:00'), TIMESTAMP(@d, '19:20:00'), 'self', 'self', @u, @u, '09:00:00', 0, 634, 'Datos de demostración', TIMESTAMP(@d, '08:46:00'), TIMESTAMP(@d, '19:20:00') FROM DUAL WHERE @run = 1 AND @st_carla IS NOT NULL;
INSERT INTO attendances (company_id, personal_id, work_date, check_in, check_out, in_source, out_source, in_marked_by, out_marked_by, scheduled_start, late_minutes, worked_minutes, notes, created_at, updated_at)
SELECT @c, @st_lucia, @d, TIMESTAMP(@d, '09:17:00'), TIMESTAMP(@d, '19:18:00'), 'self', 'self', @u, @u, '09:00:00', 17, 601, 'Datos de demostración', TIMESTAMP(@d, '09:17:00'), TIMESTAMP(@d, '19:18:00') FROM DUAL WHERE @run = 1 AND @st_lucia IS NOT NULL;
INSERT INTO attendances (company_id, personal_id, work_date, check_in, check_out, in_source, out_source, in_marked_by, out_marked_by, scheduled_start, late_minutes, worked_minutes, notes, created_at, updated_at)
SELECT @c, @pg, @d, TIMESTAMP(@d, '09:08:00'), TIMESTAMP(@d, '19:10:00'), 'self', 'self', @u, @u, '09:00:00', 0, 602, 'Datos de demostración', TIMESTAMP(@d, '09:08:00'), TIMESTAMP(@d, '19:10:00') FROM DUAL WHERE @run = 1 AND @pg IS NOT NULL;

-- ── Día CURDATE() - 8 ──
SET @d := DATE_SUB(CURDATE(), INTERVAL 8 DAY);
INSERT INTO cash_sessions (cash_register_id, personal_id, opening_amount, status, opened_at, notes, opened_by, created_at, updated_at)
SELECT @caja, @pg, 200, 'open', TIMESTAMP(@d, '08:50:00'), 'Datos de demostración', @u, TIMESTAMP(@d, '08:50:00'), TIMESTAMP(@d, '08:50:00') FROM DUAL WHERE @run = 1;
SET @cs := LAST_INSERT_ID();
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c20, @st_ana, @s_mechas, TIMESTAMP(@d, '09:15:00'), TIMESTAMP(@d, '11:45:00'), 'cancelada', 0, 380, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 2 DAY), '12:00:00'), TIMESTAMP(@d, '11:45:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c22, @st_carla, @s_facial, TIMESTAMP(@d, '09:15:00'), TIMESTAMP(@d, '10:15:00'), 'completada', 0, 180, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 1 DAY), '12:00:00'), TIMESTAMP(@d, '10:15:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c13, @st_lucia, @s_pedicure, TIMESTAMP(@d, '09:15:00'), TIMESTAMP(@d, '10:15:00'), 'completada', 0, 80, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 2 DAY), '12:00:00'), TIMESTAMP(@d, '10:15:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c19, @st_lucia, @s_cejas, TIMESTAMP(@d, '10:15:00'), TIMESTAMP(@d, '10:35:00'), 'completada', 0, 40, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 4 DAY), '12:00:00'), TIMESTAMP(@d, '10:35:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c19, @st_carla, @s_corte_cab, TIMESTAMP(@d, '10:30:00'), TIMESTAMP(@d, '11:00:00'), 'completada', 0, 50, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 3 DAY), '12:00:00'), TIMESTAMP(@d, '11:00:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c21, @st_ana, @s_tinte, TIMESTAMP(@d, '11:45:00'), TIMESTAMP(@d, '13:45:00'), 'no_show', 0, 250, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 1 DAY), '12:00:00'), TIMESTAMP(@d, '13:45:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c16, @st_carla, @s_facial, TIMESTAMP(@d, '11:45:00'), TIMESTAMP(@d, '12:45:00'), 'completada', 0, 180, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 1 DAY), '12:00:00'), TIMESTAMP(@d, '12:45:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c7, @st_carla, @s_tinte, TIMESTAMP(@d, '13:45:00'), TIMESTAMP(@d, '15:45:00'), 'completada', 0, 250, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 2 DAY), '12:00:00'), TIMESTAMP(@d, '15:45:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c22, @st_ana, @s_mechas, TIMESTAMP(@d, '14:00:00'), TIMESTAMP(@d, '16:30:00'), 'no_show', 0, 380, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 5 DAY), '12:00:00'), TIMESTAMP(@d, '16:30:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c22, @st_carla, @cs, @num, TIMESTAMP(@d, '10:20:00'), 180.00, 0, 0, 180.00, 20.00, 'qr', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '10:20:00'), TIMESTAMP(@d, '10:20:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_facial, NULL, @st_carla, 'LIMPIEZA FACIAL', 1, 180.00, 0, 180.00, TIMESTAMP(@d, '10:20:00'), TIMESTAMP(@d, '10:20:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 180.00, CONCAT('Venta ', @num), @d, 'qr', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '10:20:00'), TIMESTAMP(@d, '10:20:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, category, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 20.00, CONCAT('Propina venta ', @num), 'Propinas', @d, 'qr', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '10:20:00'), TIMESTAMP(@d, '10:20:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c13, @st_lucia, @cs, @num, TIMESTAMP(@d, '10:20:00'), 80.00, 0, 0, 80.00, 0.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '10:20:00'), TIMESTAMP(@d, '10:20:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_pedicure, NULL, @st_lucia, 'PEDICURE', 1, 80.00, 0, 80.00, TIMESTAMP(@d, '10:20:00'), TIMESTAMP(@d, '10:20:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 80.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '10:20:00'), TIMESTAMP(@d, '10:20:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c19, @st_lucia, @cs, @num, TIMESTAMP(@d, '10:40:00'), 40.00, 0, 0, 40.00, 20.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '10:40:00'), TIMESTAMP(@d, '10:40:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_cejas, NULL, @st_lucia, 'DISEÑO DE CEJAS', 1, 40.00, 0, 40.00, TIMESTAMP(@d, '10:40:00'), TIMESTAMP(@d, '10:40:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 40.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '10:40:00'), TIMESTAMP(@d, '10:40:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, category, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 20.00, CONCAT('Propina venta ', @num), 'Propinas', @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '10:40:00'), TIMESTAMP(@d, '10:40:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c19, @st_carla, @cs, @num, TIMESTAMP(@d, '11:05:00'), 170.00, 0, 0, 170.00, 0.00, 'transferencia', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '11:05:00'), TIMESTAMP(@d, '11:05:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_corte_cab, NULL, @st_carla, 'CORTE CABALLERO', 1, 50.00, 0, 50.00, TIMESTAMP(@d, '11:05:00'), TIMESTAMP(@d, '11:05:00') FROM DUAL WHERE @run = 1;
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, NULL, @p_mascar, @st_carla, 'MASCARILLA CAPILAR 250G', 1, 120.00, 0, 120.00, TIMESTAMP(@d, '11:05:00'), TIMESTAMP(@d, '11:05:00') FROM DUAL WHERE @run = 1;
UPDATE products SET current_stock = current_stock - 1 WHERE id = @p_mascar AND @run = 1;
INSERT INTO inventory_movements (company_id, product_id, user_id, type, quantity, unit_cost, reference, notes, movement_date, created_at, updated_at)
SELECT @c, @p_mascar, @u, 'salida', 1, 60, @num, CONCAT('Venta ', @num, ' · ', 'Datos de demostración'), TIMESTAMP(@d, '11:05:00'), NOW(), NOW() FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 170.00, CONCAT('Venta ', @num), @d, 'transferencia', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '11:05:00'), TIMESTAMP(@d, '11:05:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c16, @st_carla, @cs, @num, TIMESTAMP(@d, '12:50:00'), 180.00, 0, 0, 180.00, 0.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '12:50:00'), TIMESTAMP(@d, '12:50:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_facial, NULL, @st_carla, 'LIMPIEZA FACIAL', 1, 180.00, 0, 180.00, TIMESTAMP(@d, '12:50:00'), TIMESTAMP(@d, '12:50:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 180.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '12:50:00'), TIMESTAMP(@d, '12:50:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, NULL, NULL, @cs, @num, TIMESTAMP(@d, '15:45:00'), 120.00, 0, 0, 120.00, 0.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '15:45:00'), TIMESTAMP(@d, '15:45:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, NULL, @p_mascar, NULL, 'MASCARILLA CAPILAR 250G', 1, 120.00, 0, 120.00, TIMESTAMP(@d, '15:45:00'), TIMESTAMP(@d, '15:45:00') FROM DUAL WHERE @run = 1;
UPDATE products SET current_stock = current_stock - 1 WHERE id = @p_mascar AND @run = 1;
INSERT INTO inventory_movements (company_id, product_id, user_id, type, quantity, unit_cost, reference, notes, movement_date, created_at, updated_at)
SELECT @c, @p_mascar, @u, 'salida', 1, 60, @num, CONCAT('Venta ', @num, ' · ', 'Datos de demostración'), TIMESTAMP(@d, '15:45:00'), NOW(), NOW() FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 120.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '15:45:00'), TIMESTAMP(@d, '15:45:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c7, @st_carla, @cs, @num, TIMESTAMP(@d, '15:50:00'), 250.00, 0, 0, 250.00, 0.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '15:50:00'), TIMESTAMP(@d, '15:50:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_tinte, NULL, @st_carla, 'TINTE COMPLETO', 1, 250.00, 0, 250.00, TIMESTAMP(@d, '15:50:00'), TIMESTAMP(@d, '15:50:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 250.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '15:50:00'), TIMESTAMP(@d, '15:50:00') FROM DUAL WHERE @run = 1;
UPDATE cash_sessions SET opened_at = opened_at, status = 'closed', closed_at = TIMESTAMP(@d, '19:20:00'), expected_amount = 1260.00, closing_amount = 1260.00, difference = 0.00, closed_by = @u, updated_at = TIMESTAMP(@d, '19:20:00') WHERE id = @cs AND @run = 1;
INSERT INTO attendances (company_id, personal_id, work_date, check_in, check_out, in_source, out_source, in_marked_by, out_marked_by, scheduled_start, late_minutes, worked_minutes, notes, created_at, updated_at)
SELECT @c, @st_ana, @d, TIMESTAMP(@d, '09:05:00'), TIMESTAMP(@d, '19:07:00'), 'self', 'self', @u, @u, '09:00:00', 0, 602, 'Datos de demostración', TIMESTAMP(@d, '09:05:00'), TIMESTAMP(@d, '19:07:00') FROM DUAL WHERE @run = 1 AND @st_ana IS NOT NULL;
INSERT INTO attendances (company_id, personal_id, work_date, check_in, check_out, in_source, out_source, in_marked_by, out_marked_by, scheduled_start, late_minutes, worked_minutes, notes, created_at, updated_at)
SELECT @c, @st_carla, @d, TIMESTAMP(@d, '09:06:00'), TIMESTAMP(@d, '19:07:00'), 'self', 'self', @u, @u, '09:00:00', 0, 601, 'Datos de demostración', TIMESTAMP(@d, '09:06:00'), TIMESTAMP(@d, '19:07:00') FROM DUAL WHERE @run = 1 AND @st_carla IS NOT NULL;
INSERT INTO attendances (company_id, personal_id, work_date, check_in, check_out, in_source, out_source, in_marked_by, out_marked_by, scheduled_start, late_minutes, worked_minutes, notes, created_at, updated_at)
SELECT @c, @st_lucia, @d, TIMESTAMP(@d, '09:15:00'), TIMESTAMP(@d, '19:21:00'), 'self', 'self', @u, @u, '09:00:00', 15, 606, 'Datos de demostración', TIMESTAMP(@d, '09:15:00'), TIMESTAMP(@d, '19:21:00') FROM DUAL WHERE @run = 1 AND @st_lucia IS NOT NULL;
INSERT INTO attendances (company_id, personal_id, work_date, check_in, check_out, in_source, out_source, in_marked_by, out_marked_by, scheduled_start, late_minutes, worked_minutes, notes, created_at, updated_at)
SELECT @c, @pg, @d, TIMESTAMP(@d, '09:17:00'), TIMESTAMP(@d, '19:24:00'), 'self', 'self', @u, @u, '09:00:00', 17, 607, 'Datos de demostración', TIMESTAMP(@d, '09:17:00'), TIMESTAMP(@d, '19:24:00') FROM DUAL WHERE @run = 1 AND @pg IS NOT NULL;

-- ── Día CURDATE() - 7 ──
SET @d := DATE_SUB(CURDATE(), INTERVAL 7 DAY);
INSERT INTO cash_sessions (cash_register_id, personal_id, opening_amount, status, opened_at, notes, opened_by, created_at, updated_at)
SELECT @caja, @pg, 200, 'open', TIMESTAMP(@d, '08:50:00'), 'Datos de demostración', @u, TIMESTAMP(@d, '08:50:00'), TIMESTAMP(@d, '08:50:00') FROM DUAL WHERE @run = 1;
SET @cs := LAST_INSERT_ID();
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c2, @st_carla, @s_cejas, TIMESTAMP(@d, '09:00:00'), TIMESTAMP(@d, '09:20:00'), 'completada', 0, 40, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 2 DAY), '12:00:00'), TIMESTAMP(@d, '09:20:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c10, @st_lucia, @s_facial, TIMESTAMP(@d, '09:15:00'), TIMESTAMP(@d, '10:15:00'), 'cancelada', 0, 180, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 3 DAY), '12:00:00'), TIMESTAMP(@d, '10:15:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c18, @st_carla, @s_cejas, TIMESTAMP(@d, '09:20:00'), TIMESTAMP(@d, '09:40:00'), 'completada', 0, 40, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 4 DAY), '12:00:00'), TIMESTAMP(@d, '09:40:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c22, @st_ana, @s_brushing, TIMESTAMP(@d, '09:30:00'), TIMESTAMP(@d, '10:15:00'), 'no_show', 0, 60, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 3 DAY), '12:00:00'), TIMESTAMP(@d, '10:15:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c17, @st_carla, @s_facial, TIMESTAMP(@d, '10:40:00'), TIMESTAMP(@d, '11:40:00'), 'completada', 0, 180, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 1 DAY), '12:00:00'), TIMESTAMP(@d, '11:40:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c9, @st_ana, @s_corte_dama, TIMESTAMP(@d, '11:00:00'), TIMESTAMP(@d, '11:45:00'), 'completada', 0, 80, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 2 DAY), '12:00:00'), TIMESTAMP(@d, '11:45:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c10, @st_lucia, @s_cejas, TIMESTAMP(@d, '11:00:00'), TIMESTAMP(@d, '11:20:00'), 'completada', 0, 40, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 4 DAY), '12:00:00'), TIMESTAMP(@d, '11:20:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c5, @st_carla, @s_corte_cab, TIMESTAMP(@d, '11:40:00'), TIMESTAMP(@d, '12:10:00'), 'completada', 0, 50, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 1 DAY), '12:00:00'), TIMESTAMP(@d, '12:10:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c0, @st_lucia, @s_acrilicas, TIMESTAMP(@d, '12:05:00'), TIMESTAMP(@d, '13:35:00'), 'completada', 0, 180, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 1 DAY), '12:00:00'), TIMESTAMP(@d, '13:35:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c13, @st_ana, @s_mechas, TIMESTAMP(@d, '12:15:00'), TIMESTAMP(@d, '14:45:00'), 'completada', 0, 380, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 4 DAY), '12:00:00'), TIMESTAMP(@d, '14:45:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c2, @st_carla, @cs, @num, TIMESTAMP(@d, '09:25:00'), 40.00, 0, 0, 40.00, 0.00, 'transferencia', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '09:25:00'), TIMESTAMP(@d, '09:25:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_cejas, NULL, @st_carla, 'DISEÑO DE CEJAS', 1, 40.00, 0, 40.00, TIMESTAMP(@d, '09:25:00'), TIMESTAMP(@d, '09:25:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 40.00, CONCAT('Venta ', @num), @d, 'transferencia', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '09:25:00'), TIMESTAMP(@d, '09:25:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c18, @st_carla, @cs, @num, TIMESTAMP(@d, '09:45:00'), 40.00, 0, 0, 40.00, 0.00, 'transferencia', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '09:45:00'), TIMESTAMP(@d, '09:45:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_cejas, NULL, @st_carla, 'DISEÑO DE CEJAS', 1, 40.00, 0, 40.00, TIMESTAMP(@d, '09:45:00'), TIMESTAMP(@d, '09:45:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 40.00, CONCAT('Venta ', @num), @d, 'transferencia', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '09:45:00'), TIMESTAMP(@d, '09:45:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c10, @st_lucia, @cs, @num, TIMESTAMP(@d, '11:25:00'), 40.00, 0, 0, 40.00, 0.00, 'qr', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '11:25:00'), TIMESTAMP(@d, '11:25:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_cejas, NULL, @st_lucia, 'DISEÑO DE CEJAS', 1, 40.00, 0, 40.00, TIMESTAMP(@d, '11:25:00'), TIMESTAMP(@d, '11:25:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 40.00, CONCAT('Venta ', @num), @d, 'qr', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '11:25:00'), TIMESTAMP(@d, '11:25:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c17, @st_carla, @cs, @num, TIMESTAMP(@d, '11:45:00'), 180.00, 0, 0, 180.00, 0.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '11:45:00'), TIMESTAMP(@d, '11:45:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_facial, NULL, @st_carla, 'LIMPIEZA FACIAL', 1, 180.00, 0, 180.00, TIMESTAMP(@d, '11:45:00'), TIMESTAMP(@d, '11:45:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 180.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '11:45:00'), TIMESTAMP(@d, '11:45:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c9, @st_ana, @cs, @num, TIMESTAMP(@d, '11:50:00'), 80.00, 0, 0, 80.00, 0.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '11:50:00'), TIMESTAMP(@d, '11:50:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_corte_dama, NULL, @st_ana, 'CORTE DAMA', 1, 80.00, 0, 80.00, TIMESTAMP(@d, '11:50:00'), TIMESTAMP(@d, '11:50:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 80.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '11:50:00'), TIMESTAMP(@d, '11:50:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c5, @st_carla, @cs, @num, TIMESTAMP(@d, '12:15:00'), 50.00, 0, 0, 50.00, 0.00, 'qr', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '12:15:00'), TIMESTAMP(@d, '12:15:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_corte_cab, NULL, @st_carla, 'CORTE CABALLERO', 1, 50.00, 0, 50.00, TIMESTAMP(@d, '12:15:00'), TIMESTAMP(@d, '12:15:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 50.00, CONCAT('Venta ', @num), @d, 'qr', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '12:15:00'), TIMESTAMP(@d, '12:15:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c0, @st_lucia, @cs, @num, TIMESTAMP(@d, '13:40:00'), 215.00, 0, 0, 215.00, 10.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '13:40:00'), TIMESTAMP(@d, '13:40:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_acrilicas, NULL, @st_lucia, 'UÑAS ACRÍLICAS', 1, 180.00, 0, 180.00, TIMESTAMP(@d, '13:40:00'), TIMESTAMP(@d, '13:40:00') FROM DUAL WHERE @run = 1;
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, NULL, @p_esmalte, @st_lucia, 'ESMALTE DE UÑAS', 1, 35.00, 0, 35.00, TIMESTAMP(@d, '13:40:00'), TIMESTAMP(@d, '13:40:00') FROM DUAL WHERE @run = 1;
UPDATE products SET current_stock = current_stock - 1 WHERE id = @p_esmalte AND @run = 1;
INSERT INTO inventory_movements (company_id, product_id, user_id, type, quantity, unit_cost, reference, notes, movement_date, created_at, updated_at)
SELECT @c, @p_esmalte, @u, 'salida', 1, 15, @num, CONCAT('Venta ', @num, ' · ', 'Datos de demostración'), TIMESTAMP(@d, '13:40:00'), NOW(), NOW() FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 215.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '13:40:00'), TIMESTAMP(@d, '13:40:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, category, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 10.00, CONCAT('Propina venta ', @num), 'Propinas', @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '13:40:00'), TIMESTAMP(@d, '13:40:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c13, @st_ana, @cs, @num, TIMESTAMP(@d, '14:50:00'), 380.00, 0, 0, 380.00, 0.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '14:50:00'), TIMESTAMP(@d, '14:50:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_mechas, NULL, @st_ana, 'MECHAS / BALAYAGE', 1, 380.00, 0, 380.00, TIMESTAMP(@d, '14:50:00'), TIMESTAMP(@d, '14:50:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 380.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '14:50:00'), TIMESTAMP(@d, '14:50:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, category, movement_date, payment_method, created_by, created_at, updated_at)
SELECT @c, @cs, 'egreso', 20.00, 'Bolsas y papel', 'Insumos', @d, 'efectivo', @u, TIMESTAMP(@d, '10:00:00'), TIMESTAMP(@d, '10:00:00') FROM DUAL WHERE @run = 1;
UPDATE cash_sessions SET opened_at = opened_at, status = 'closed', closed_at = TIMESTAMP(@d, '19:20:00'), expected_amount = 1215.00, closing_amount = 1215.00, difference = 0.00, closed_by = @u, updated_at = TIMESTAMP(@d, '19:20:00') WHERE id = @cs AND @run = 1;
INSERT INTO attendances (company_id, personal_id, work_date, check_in, check_out, in_source, out_source, in_marked_by, out_marked_by, scheduled_start, late_minutes, worked_minutes, notes, created_at, updated_at)
SELECT @c, @st_ana, @d, TIMESTAMP(@d, '08:58:00'), TIMESTAMP(@d, '19:24:00'), 'self', 'self', @u, @u, '09:00:00', 0, 626, 'Datos de demostración', TIMESTAMP(@d, '08:58:00'), TIMESTAMP(@d, '19:24:00') FROM DUAL WHERE @run = 1 AND @st_ana IS NOT NULL;
INSERT INTO attendances (company_id, personal_id, work_date, check_in, check_out, in_source, out_source, in_marked_by, out_marked_by, scheduled_start, late_minutes, worked_minutes, notes, created_at, updated_at)
SELECT @c, @st_carla, @d, TIMESTAMP(@d, '09:18:00'), TIMESTAMP(@d, '19:17:00'), 'self', 'self', @u, @u, '09:00:00', 18, 599, 'Datos de demostración', TIMESTAMP(@d, '09:18:00'), TIMESTAMP(@d, '19:17:00') FROM DUAL WHERE @run = 1 AND @st_carla IS NOT NULL;
INSERT INTO attendances (company_id, personal_id, work_date, check_in, check_out, in_source, out_source, in_marked_by, out_marked_by, scheduled_start, late_minutes, worked_minutes, notes, created_at, updated_at)
SELECT @c, @st_lucia, @d, TIMESTAMP(@d, '08:45:00'), TIMESTAMP(@d, '19:24:00'), 'self', 'self', @u, @u, '09:00:00', 0, 639, 'Datos de demostración', TIMESTAMP(@d, '08:45:00'), TIMESTAMP(@d, '19:24:00') FROM DUAL WHERE @run = 1 AND @st_lucia IS NOT NULL;
INSERT INTO attendances (company_id, personal_id, work_date, check_in, check_out, in_source, out_source, in_marked_by, out_marked_by, scheduled_start, late_minutes, worked_minutes, notes, created_at, updated_at)
SELECT @c, @pg, @d, TIMESTAMP(@d, '08:48:00'), TIMESTAMP(@d, '19:19:00'), 'self', 'self', @u, @u, '09:00:00', 0, 631, 'Datos de demostración', TIMESTAMP(@d, '08:48:00'), TIMESTAMP(@d, '19:19:00') FROM DUAL WHERE @run = 1 AND @pg IS NOT NULL;

-- ── Día CURDATE() - 6 ──
SET @d := DATE_SUB(CURDATE(), INTERVAL 6 DAY);
INSERT INTO cash_sessions (cash_register_id, personal_id, opening_amount, status, opened_at, notes, opened_by, created_at, updated_at)
SELECT @caja, @pg, 200, 'open', TIMESTAMP(@d, '08:50:00'), 'Datos de demostración', @u, TIMESTAMP(@d, '08:50:00'), TIMESTAMP(@d, '08:50:00') FROM DUAL WHERE @run = 1;
SET @cs := LAST_INSERT_ID();
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c1, @st_ana, @s_corte_dama, TIMESTAMP(@d, '09:00:00'), TIMESTAMP(@d, '09:45:00'), 'completada', 0, 80, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 2 DAY), '12:00:00'), TIMESTAMP(@d, '09:45:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c0, @st_carla, @s_corte_cab, TIMESTAMP(@d, '09:00:00'), TIMESTAMP(@d, '09:30:00'), 'completada', 0, 50, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 2 DAY), '12:00:00'), TIMESTAMP(@d, '09:30:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c8, @st_lucia, @s_cejas, TIMESTAMP(@d, '09:30:00'), TIMESTAMP(@d, '09:50:00'), 'completada', 0, 40, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 5 DAY), '12:00:00'), TIMESTAMP(@d, '09:50:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c13, @st_lucia, @s_cejas, TIMESTAMP(@d, '10:05:00'), TIMESTAMP(@d, '10:25:00'), 'no_show', 0, 40, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 4 DAY), '12:00:00'), TIMESTAMP(@d, '10:25:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c16, @st_carla, @s_corte_cab, TIMESTAMP(@d, '10:15:00'), TIMESTAMP(@d, '10:45:00'), 'completada', 0, 50, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 1 DAY), '12:00:00'), TIMESTAMP(@d, '10:45:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c11, @st_ana, @s_keratina, TIMESTAMP(@d, '10:30:00'), TIMESTAMP(@d, '12:30:00'), 'completada', 0, 300, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 1 DAY), '12:00:00'), TIMESTAMP(@d, '12:30:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c9, @st_lucia, @s_acrilicas, TIMESTAMP(@d, '11:25:00'), TIMESTAMP(@d, '12:55:00'), 'completada', 0, 180, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 1 DAY), '12:00:00'), TIMESTAMP(@d, '12:55:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c23, @st_carla, @s_facial, TIMESTAMP(@d, '11:30:00'), TIMESTAMP(@d, '12:30:00'), 'completada', 0, 180, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 1 DAY), '12:00:00'), TIMESTAMP(@d, '12:30:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c3, @st_carla, @s_brushing, TIMESTAMP(@d, '12:45:00'), TIMESTAMP(@d, '13:30:00'), 'completada', 0, 60, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 3 DAY), '12:00:00'), TIMESTAMP(@d, '13:30:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c13, @st_ana, @s_brushing, TIMESTAMP(@d, '13:15:00'), TIMESTAMP(@d, '14:00:00'), 'completada', 0, 60, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 5 DAY), '12:00:00'), TIMESTAMP(@d, '14:00:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c21, @st_ana, @s_brushing, TIMESTAMP(@d, '15:00:00'), TIMESTAMP(@d, '15:45:00'), 'completada', 0, 60, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 3 DAY), '12:00:00'), TIMESTAMP(@d, '15:45:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c0, @st_carla, @cs, @num, TIMESTAMP(@d, '09:35:00'), 135.00, 0, 0, 135.00, 0.00, 'qr', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '09:35:00'), TIMESTAMP(@d, '09:35:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_corte_cab, NULL, @st_carla, 'CORTE CABALLERO', 1, 50.00, 0, 50.00, TIMESTAMP(@d, '09:35:00'), TIMESTAMP(@d, '09:35:00') FROM DUAL WHERE @run = 1;
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, NULL, @p_acond, @st_carla, 'ACONDICIONADOR 400ML', 1, 85.00, 0, 85.00, TIMESTAMP(@d, '09:35:00'), TIMESTAMP(@d, '09:35:00') FROM DUAL WHERE @run = 1;
UPDATE products SET current_stock = current_stock - 1 WHERE id = @p_acond AND @run = 1;
INSERT INTO inventory_movements (company_id, product_id, user_id, type, quantity, unit_cost, reference, notes, movement_date, created_at, updated_at)
SELECT @c, @p_acond, @u, 'salida', 1, 45, @num, CONCAT('Venta ', @num, ' · ', 'Datos de demostración'), TIMESTAMP(@d, '09:35:00'), NOW(), NOW() FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 135.00, CONCAT('Venta ', @num), @d, 'qr', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '09:35:00'), TIMESTAMP(@d, '09:35:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c1, @st_ana, @cs, @num, TIMESTAMP(@d, '09:50:00'), 80.00, 0, 0, 80.00, 0.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '09:50:00'), TIMESTAMP(@d, '09:50:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_corte_dama, NULL, @st_ana, 'CORTE DAMA', 1, 80.00, 0, 80.00, TIMESTAMP(@d, '09:50:00'), TIMESTAMP(@d, '09:50:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 80.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '09:50:00'), TIMESTAMP(@d, '09:50:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c8, @st_lucia, @cs, @num, TIMESTAMP(@d, '09:55:00'), 75.00, 0, 0, 75.00, 0.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '09:55:00'), TIMESTAMP(@d, '09:55:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_cejas, NULL, @st_lucia, 'DISEÑO DE CEJAS', 1, 40.00, 0, 40.00, TIMESTAMP(@d, '09:55:00'), TIMESTAMP(@d, '09:55:00') FROM DUAL WHERE @run = 1;
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, NULL, @p_esmalte, @st_lucia, 'ESMALTE DE UÑAS', 1, 35.00, 0, 35.00, TIMESTAMP(@d, '09:55:00'), TIMESTAMP(@d, '09:55:00') FROM DUAL WHERE @run = 1;
UPDATE products SET current_stock = current_stock - 1 WHERE id = @p_esmalte AND @run = 1;
INSERT INTO inventory_movements (company_id, product_id, user_id, type, quantity, unit_cost, reference, notes, movement_date, created_at, updated_at)
SELECT @c, @p_esmalte, @u, 'salida', 1, 15, @num, CONCAT('Venta ', @num, ' · ', 'Datos de demostración'), TIMESTAMP(@d, '09:55:00'), NOW(), NOW() FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 75.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '09:55:00'), TIMESTAMP(@d, '09:55:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c16, @st_carla, @cs, @num, TIMESTAMP(@d, '10:50:00'), 50.00, 0, 0, 50.00, 0.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '10:50:00'), TIMESTAMP(@d, '10:50:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_corte_cab, NULL, @st_carla, 'CORTE CABALLERO', 1, 50.00, 0, 50.00, TIMESTAMP(@d, '10:50:00'), TIMESTAMP(@d, '10:50:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 50.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '10:50:00'), TIMESTAMP(@d, '10:50:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c11, NULL, @cs, @num, TIMESTAMP(@d, '11:15:00'), 35.00, 0, 0, 35.00, 0.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '11:15:00'), TIMESTAMP(@d, '11:15:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, NULL, @p_esmalte, NULL, 'ESMALTE DE UÑAS', 1, 35.00, 0, 35.00, TIMESTAMP(@d, '11:15:00'), TIMESTAMP(@d, '11:15:00') FROM DUAL WHERE @run = 1;
UPDATE products SET current_stock = current_stock - 1 WHERE id = @p_esmalte AND @run = 1;
INSERT INTO inventory_movements (company_id, product_id, user_id, type, quantity, unit_cost, reference, notes, movement_date, created_at, updated_at)
SELECT @c, @p_esmalte, @u, 'salida', 1, 15, @num, CONCAT('Venta ', @num, ' · ', 'Datos de demostración'), TIMESTAMP(@d, '11:15:00'), NOW(), NOW() FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 35.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '11:15:00'), TIMESTAMP(@d, '11:15:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c11, @st_ana, @cs, @num, TIMESTAMP(@d, '12:35:00'), 300.00, 0, 0, 300.00, 0.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '12:35:00'), TIMESTAMP(@d, '12:35:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_keratina, NULL, @st_ana, 'TRATAMIENTO DE KERATINA', 1, 300.00, 0, 300.00, TIMESTAMP(@d, '12:35:00'), TIMESTAMP(@d, '12:35:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 300.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '12:35:00'), TIMESTAMP(@d, '12:35:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c23, @st_carla, @cs, @num, TIMESTAMP(@d, '12:35:00'), 180.00, 0, 0, 180.00, 0.00, 'qr', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '12:35:00'), TIMESTAMP(@d, '12:35:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_facial, NULL, @st_carla, 'LIMPIEZA FACIAL', 1, 180.00, 0, 180.00, TIMESTAMP(@d, '12:35:00'), TIMESTAMP(@d, '12:35:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 180.00, CONCAT('Venta ', @num), @d, 'qr', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '12:35:00'), TIMESTAMP(@d, '12:35:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c9, @st_lucia, @cs, @num, TIMESTAMP(@d, '13:00:00'), 180.00, 0, 0, 180.00, 0.00, 'transferencia', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '13:00:00'), TIMESTAMP(@d, '13:00:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_acrilicas, NULL, @st_lucia, 'UÑAS ACRÍLICAS', 1, 180.00, 0, 180.00, TIMESTAMP(@d, '13:00:00'), TIMESTAMP(@d, '13:00:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 180.00, CONCAT('Venta ', @num), @d, 'transferencia', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '13:00:00'), TIMESTAMP(@d, '13:00:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c3, @st_carla, @cs, @num, TIMESTAMP(@d, '13:35:00'), 60.00, 0, 0, 60.00, 10.00, 'qr', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '13:35:00'), TIMESTAMP(@d, '13:35:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_brushing, NULL, @st_carla, 'BRUSHING', 1, 60.00, 0, 60.00, TIMESTAMP(@d, '13:35:00'), TIMESTAMP(@d, '13:35:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 60.00, CONCAT('Venta ', @num), @d, 'qr', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '13:35:00'), TIMESTAMP(@d, '13:35:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, category, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 10.00, CONCAT('Propina venta ', @num), 'Propinas', @d, 'qr', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '13:35:00'), TIMESTAMP(@d, '13:35:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c13, @st_ana, @cs, @num, TIMESTAMP(@d, '14:05:00'), 60.00, 0, 0, 60.00, 10.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '14:05:00'), TIMESTAMP(@d, '14:05:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_brushing, NULL, @st_ana, 'BRUSHING', 1, 60.00, 0, 60.00, TIMESTAMP(@d, '14:05:00'), TIMESTAMP(@d, '14:05:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 60.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '14:05:00'), TIMESTAMP(@d, '14:05:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, category, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 10.00, CONCAT('Propina venta ', @num), 'Propinas', @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '14:05:00'), TIMESTAMP(@d, '14:05:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c21, @st_ana, @cs, @num, TIMESTAMP(@d, '15:50:00'), 95.00, 0, 0, 95.00, 10.00, 'qr', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '15:50:00'), TIMESTAMP(@d, '15:50:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_brushing, NULL, @st_ana, 'BRUSHING', 1, 60.00, 0, 60.00, TIMESTAMP(@d, '15:50:00'), TIMESTAMP(@d, '15:50:00') FROM DUAL WHERE @run = 1;
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, NULL, @p_esmalte, @st_ana, 'ESMALTE DE UÑAS', 1, 35.00, 0, 35.00, TIMESTAMP(@d, '15:50:00'), TIMESTAMP(@d, '15:50:00') FROM DUAL WHERE @run = 1;
UPDATE products SET current_stock = current_stock - 1 WHERE id = @p_esmalte AND @run = 1;
INSERT INTO inventory_movements (company_id, product_id, user_id, type, quantity, unit_cost, reference, notes, movement_date, created_at, updated_at)
SELECT @c, @p_esmalte, @u, 'salida', 1, 15, @num, CONCAT('Venta ', @num, ' · ', 'Datos de demostración'), TIMESTAMP(@d, '15:50:00'), NOW(), NOW() FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 95.00, CONCAT('Venta ', @num), @d, 'qr', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '15:50:00'), TIMESTAMP(@d, '15:50:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, category, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 10.00, CONCAT('Propina venta ', @num), 'Propinas', @d, 'qr', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '15:50:00'), TIMESTAMP(@d, '15:50:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c12, NULL, @cs, @num, TIMESTAMP(@d, '18:30:00'), 220.00, 0, 0, 220.00, 0.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '18:30:00'), TIMESTAMP(@d, '18:30:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, NULL, @p_serum, NULL, 'SÉRUM DE ARGÁN 60ML', 2, 110.00, 0, 220.00, TIMESTAMP(@d, '18:30:00'), TIMESTAMP(@d, '18:30:00') FROM DUAL WHERE @run = 1;
UPDATE products SET current_stock = current_stock - 2 WHERE id = @p_serum AND @run = 1;
INSERT INTO inventory_movements (company_id, product_id, user_id, type, quantity, unit_cost, reference, notes, movement_date, created_at, updated_at)
SELECT @c, @p_serum, @u, 'salida', 2, 55, @num, CONCAT('Venta ', @num, ' · ', 'Datos de demostración'), TIMESTAMP(@d, '18:30:00'), NOW(), NOW() FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 220.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '18:30:00'), TIMESTAMP(@d, '18:30:00') FROM DUAL WHERE @run = 1;
UPDATE cash_sessions SET opened_at = opened_at, status = 'closed', closed_at = TIMESTAMP(@d, '19:20:00'), expected_amount = 1700.00, closing_amount = 1700.00, difference = 0.00, closed_by = @u, updated_at = TIMESTAMP(@d, '19:20:00') WHERE id = @cs AND @run = 1;
INSERT INTO attendances (company_id, personal_id, work_date, check_in, check_out, in_source, out_source, in_marked_by, out_marked_by, scheduled_start, late_minutes, worked_minutes, notes, created_at, updated_at)
SELECT @c, @st_ana, @d, TIMESTAMP(@d, '09:19:00'), TIMESTAMP(@d, '19:16:00'), 'self', 'self', @u, @u, '09:00:00', 19, 597, 'Datos de demostración', TIMESTAMP(@d, '09:19:00'), TIMESTAMP(@d, '19:16:00') FROM DUAL WHERE @run = 1 AND @st_ana IS NOT NULL;
INSERT INTO attendances (company_id, personal_id, work_date, check_in, check_out, in_source, out_source, in_marked_by, out_marked_by, scheduled_start, late_minutes, worked_minutes, notes, created_at, updated_at)
SELECT @c, @st_carla, @d, TIMESTAMP(@d, '08:47:00'), TIMESTAMP(@d, '19:25:00'), 'self', 'self', @u, @u, '09:00:00', 0, 638, 'Datos de demostración', TIMESTAMP(@d, '08:47:00'), TIMESTAMP(@d, '19:25:00') FROM DUAL WHERE @run = 1 AND @st_carla IS NOT NULL;

-- ── Día CURDATE() - 5 ──
SET @d := DATE_SUB(CURDATE(), INTERVAL 5 DAY);
INSERT INTO cash_sessions (cash_register_id, personal_id, opening_amount, status, opened_at, notes, opened_by, created_at, updated_at)
SELECT @caja, @pg, 200, 'open', TIMESTAMP(@d, '08:50:00'), 'Datos de demostración', @u, TIMESTAMP(@d, '08:50:00'), TIMESTAMP(@d, '08:50:00') FROM DUAL WHERE @run = 1;
SET @cs := LAST_INSERT_ID();
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c2, @st_carla, @s_keratina, TIMESTAMP(@d, '09:00:00'), TIMESTAMP(@d, '11:00:00'), 'no_show', 0, 300, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 3 DAY), '12:00:00'), TIMESTAMP(@d, '11:00:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c8, @st_ana, @s_keratina, TIMESTAMP(@d, '09:15:00'), TIMESTAMP(@d, '11:15:00'), 'completada', 0, 300, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 5 DAY), '12:00:00'), TIMESTAMP(@d, '11:15:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c17, @st_lucia, @s_acrilicas, TIMESTAMP(@d, '09:30:00'), TIMESTAMP(@d, '11:00:00'), 'completada', 0, 180, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 1 DAY), '12:00:00'), TIMESTAMP(@d, '11:00:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c19, @st_ana, @s_tinte, TIMESTAMP(@d, '11:30:00'), TIMESTAMP(@d, '13:30:00'), 'completada', 0, 250, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 4 DAY), '12:00:00'), TIMESTAMP(@d, '13:30:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c1, @st_carla, @s_brushing, TIMESTAMP(@d, '11:30:00'), TIMESTAMP(@d, '12:15:00'), 'completada', 0, 60, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 5 DAY), '12:00:00'), TIMESTAMP(@d, '12:15:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c5, @st_lucia, @s_facial, TIMESTAMP(@d, '11:45:00'), TIMESTAMP(@d, '12:45:00'), 'completada', 0, 180, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 1 DAY), '12:00:00'), TIMESTAMP(@d, '12:45:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c21, @st_carla, @s_brushing, TIMESTAMP(@d, '12:15:00'), TIMESTAMP(@d, '13:00:00'), 'completada', 0, 60, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 2 DAY), '12:00:00'), TIMESTAMP(@d, '13:00:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c9, @st_ana, @s_mechas, TIMESTAMP(@d, '14:15:00'), TIMESTAMP(@d, '16:45:00'), 'completada', 0, 380, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 1 DAY), '12:00:00'), TIMESTAMP(@d, '16:45:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c17, @st_lucia, @cs, @num, TIMESTAMP(@d, '11:05:00'), 180.00, 0, 0, 180.00, 0.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '11:05:00'), TIMESTAMP(@d, '11:05:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_acrilicas, NULL, @st_lucia, 'UÑAS ACRÍLICAS', 1, 180.00, 0, 180.00, TIMESTAMP(@d, '11:05:00'), TIMESTAMP(@d, '11:05:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 180.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '11:05:00'), TIMESTAMP(@d, '11:05:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c8, @st_ana, @cs, @num, TIMESTAMP(@d, '11:20:00'), 300.00, 0, 0, 300.00, 0.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '11:20:00'), TIMESTAMP(@d, '11:20:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_keratina, NULL, @st_ana, 'TRATAMIENTO DE KERATINA', 1, 300.00, 0, 300.00, TIMESTAMP(@d, '11:20:00'), TIMESTAMP(@d, '11:20:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 300.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '11:20:00'), TIMESTAMP(@d, '11:20:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c1, @st_carla, @cs, @num, TIMESTAMP(@d, '12:20:00'), 145.00, 0, 0, 145.00, 0.00, 'tarjeta', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '12:20:00'), TIMESTAMP(@d, '12:20:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_brushing, NULL, @st_carla, 'BRUSHING', 1, 60.00, 0, 60.00, TIMESTAMP(@d, '12:20:00'), TIMESTAMP(@d, '12:20:00') FROM DUAL WHERE @run = 1;
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, NULL, @p_shampoo, @st_carla, 'SHAMPOO REPARADOR 400ML', 1, 85.00, 0, 85.00, TIMESTAMP(@d, '12:20:00'), TIMESTAMP(@d, '12:20:00') FROM DUAL WHERE @run = 1;
UPDATE products SET current_stock = current_stock - 1 WHERE id = @p_shampoo AND @run = 1;
INSERT INTO inventory_movements (company_id, product_id, user_id, type, quantity, unit_cost, reference, notes, movement_date, created_at, updated_at)
SELECT @c, @p_shampoo, @u, 'salida', 1, 45, @num, CONCAT('Venta ', @num, ' · ', 'Datos de demostración'), TIMESTAMP(@d, '12:20:00'), NOW(), NOW() FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 145.00, CONCAT('Venta ', @num), @d, 'tarjeta', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '12:20:00'), TIMESTAMP(@d, '12:20:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c5, @st_lucia, @cs, @num, TIMESTAMP(@d, '12:50:00'), 180.00, 0, 0, 180.00, 0.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '12:50:00'), TIMESTAMP(@d, '12:50:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_facial, NULL, @st_lucia, 'LIMPIEZA FACIAL', 1, 180.00, 0, 180.00, TIMESTAMP(@d, '12:50:00'), TIMESTAMP(@d, '12:50:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 180.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '12:50:00'), TIMESTAMP(@d, '12:50:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c21, @st_carla, @cs, @num, TIMESTAMP(@d, '13:05:00'), 180.00, 0, 0, 180.00, 0.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '13:05:00'), TIMESTAMP(@d, '13:05:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_brushing, NULL, @st_carla, 'BRUSHING', 1, 60.00, 0, 60.00, TIMESTAMP(@d, '13:05:00'), TIMESTAMP(@d, '13:05:00') FROM DUAL WHERE @run = 1;
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, NULL, @p_mascar, @st_carla, 'MASCARILLA CAPILAR 250G', 1, 120.00, 0, 120.00, TIMESTAMP(@d, '13:05:00'), TIMESTAMP(@d, '13:05:00') FROM DUAL WHERE @run = 1;
UPDATE products SET current_stock = current_stock - 1 WHERE id = @p_mascar AND @run = 1;
INSERT INTO inventory_movements (company_id, product_id, user_id, type, quantity, unit_cost, reference, notes, movement_date, created_at, updated_at)
SELECT @c, @p_mascar, @u, 'salida', 1, 60, @num, CONCAT('Venta ', @num, ' · ', 'Datos de demostración'), TIMESTAMP(@d, '13:05:00'), NOW(), NOW() FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 180.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '13:05:00'), TIMESTAMP(@d, '13:05:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c19, @st_ana, @cs, @num, TIMESTAMP(@d, '13:35:00'), 285.00, 0, 0, 285.00, 0.00, 'qr', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '13:35:00'), TIMESTAMP(@d, '13:35:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_tinte, NULL, @st_ana, 'TINTE COMPLETO', 1, 250.00, 0, 250.00, TIMESTAMP(@d, '13:35:00'), TIMESTAMP(@d, '13:35:00') FROM DUAL WHERE @run = 1;
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, NULL, @p_esmalte, @st_ana, 'ESMALTE DE UÑAS', 1, 35.00, 0, 35.00, TIMESTAMP(@d, '13:35:00'), TIMESTAMP(@d, '13:35:00') FROM DUAL WHERE @run = 1;
UPDATE products SET current_stock = current_stock - 1 WHERE id = @p_esmalte AND @run = 1;
INSERT INTO inventory_movements (company_id, product_id, user_id, type, quantity, unit_cost, reference, notes, movement_date, created_at, updated_at)
SELECT @c, @p_esmalte, @u, 'salida', 1, 15, @num, CONCAT('Venta ', @num, ' · ', 'Datos de demostración'), TIMESTAMP(@d, '13:35:00'), NOW(), NOW() FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 285.00, CONCAT('Venta ', @num), @d, 'qr', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '13:35:00'), TIMESTAMP(@d, '13:35:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c9, @st_ana, @cs, @num, TIMESTAMP(@d, '16:50:00'), 380.00, 0, 0, 380.00, 0.00, 'qr', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '16:50:00'), TIMESTAMP(@d, '16:50:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_mechas, NULL, @st_ana, 'MECHAS / BALAYAGE', 1, 380.00, 0, 380.00, TIMESTAMP(@d, '16:50:00'), TIMESTAMP(@d, '16:50:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 380.00, CONCAT('Venta ', @num), @d, 'qr', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '16:50:00'), TIMESTAMP(@d, '16:50:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, NULL, NULL, @cs, @num, TIMESTAMP(@d, '17:15:00'), 110.00, 0, 0, 110.00, 0.00, 'qr', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '17:15:00'), TIMESTAMP(@d, '17:15:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, NULL, @p_serum, NULL, 'SÉRUM DE ARGÁN 60ML', 1, 110.00, 0, 110.00, TIMESTAMP(@d, '17:15:00'), TIMESTAMP(@d, '17:15:00') FROM DUAL WHERE @run = 1;
UPDATE products SET current_stock = current_stock - 1 WHERE id = @p_serum AND @run = 1;
INSERT INTO inventory_movements (company_id, product_id, user_id, type, quantity, unit_cost, reference, notes, movement_date, created_at, updated_at)
SELECT @c, @p_serum, @u, 'salida', 1, 55, @num, CONCAT('Venta ', @num, ' · ', 'Datos de demostración'), TIMESTAMP(@d, '17:15:00'), NOW(), NOW() FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 110.00, CONCAT('Venta ', @num), @d, 'qr', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '17:15:00'), TIMESTAMP(@d, '17:15:00') FROM DUAL WHERE @run = 1;
UPDATE cash_sessions SET opened_at = opened_at, status = 'closed', closed_at = TIMESTAMP(@d, '19:20:00'), expected_amount = 1960.00, closing_amount = 1960.00, difference = 0.00, closed_by = @u, updated_at = TIMESTAMP(@d, '19:20:00') WHERE id = @cs AND @run = 1;
INSERT INTO attendances (company_id, personal_id, work_date, check_in, check_out, in_source, out_source, in_marked_by, out_marked_by, scheduled_start, late_minutes, worked_minutes, notes, created_at, updated_at)
SELECT @c, @st_ana, @d, TIMESTAMP(@d, '09:03:00'), TIMESTAMP(@d, '19:15:00'), 'self', 'self', @u, @u, '09:00:00', 0, 612, 'Datos de demostración', TIMESTAMP(@d, '09:03:00'), TIMESTAMP(@d, '19:15:00') FROM DUAL WHERE @run = 1 AND @st_ana IS NOT NULL;
INSERT INTO attendances (company_id, personal_id, work_date, check_in, check_out, in_source, out_source, in_marked_by, out_marked_by, scheduled_start, late_minutes, worked_minutes, notes, created_at, updated_at)
SELECT @c, @st_carla, @d, TIMESTAMP(@d, '09:03:00'), TIMESTAMP(@d, '19:03:00'), 'self', 'self', @u, @u, '09:00:00', 0, 600, 'Datos de demostración', TIMESTAMP(@d, '09:03:00'), TIMESTAMP(@d, '19:03:00') FROM DUAL WHERE @run = 1 AND @st_carla IS NOT NULL;
INSERT INTO attendances (company_id, personal_id, work_date, check_in, check_out, in_source, out_source, in_marked_by, out_marked_by, scheduled_start, late_minutes, worked_minutes, notes, created_at, updated_at)
SELECT @c, @st_lucia, @d, TIMESTAMP(@d, '09:08:00'), TIMESTAMP(@d, '19:02:00'), 'self', 'self', @u, @u, '09:00:00', 0, 594, 'Datos de demostración', TIMESTAMP(@d, '09:08:00'), TIMESTAMP(@d, '19:02:00') FROM DUAL WHERE @run = 1 AND @st_lucia IS NOT NULL;
INSERT INTO attendances (company_id, personal_id, work_date, check_in, check_out, in_source, out_source, in_marked_by, out_marked_by, scheduled_start, late_minutes, worked_minutes, notes, created_at, updated_at)
SELECT @c, @pg, @d, TIMESTAMP(@d, '09:13:00'), TIMESTAMP(@d, '19:09:00'), 'self', 'self', @u, @u, '09:00:00', 13, 596, 'Datos de demostración', TIMESTAMP(@d, '09:13:00'), TIMESTAMP(@d, '19:09:00') FROM DUAL WHERE @run = 1 AND @pg IS NOT NULL;

-- ── Día CURDATE() - 4 ──
SET @d := DATE_SUB(CURDATE(), INTERVAL 4 DAY);
INSERT INTO cash_sessions (cash_register_id, personal_id, opening_amount, status, opened_at, notes, opened_by, created_at, updated_at)
SELECT @caja, @pg, 200, 'open', TIMESTAMP(@d, '08:50:00'), 'Datos de demostración', @u, TIMESTAMP(@d, '08:50:00'), TIMESTAMP(@d, '08:50:00') FROM DUAL WHERE @run = 1;
SET @cs := LAST_INSERT_ID();
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c17, @st_ana, @s_mechas, TIMESTAMP(@d, '09:00:00'), TIMESTAMP(@d, '11:30:00'), 'completada', 0, 380, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 4 DAY), '12:00:00'), TIMESTAMP(@d, '11:30:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c13, @st_carla, @s_facial, TIMESTAMP(@d, '09:30:00'), TIMESTAMP(@d, '10:30:00'), 'completada', 0, 180, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 4 DAY), '12:00:00'), TIMESTAMP(@d, '10:30:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c2, @st_lucia, @s_acrilicas, TIMESTAMP(@d, '09:30:00'), TIMESTAMP(@d, '11:00:00'), 'completada', 0, 180, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 2 DAY), '12:00:00'), TIMESTAMP(@d, '11:00:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c18, @st_carla, @s_corte_cab, TIMESTAMP(@d, '10:45:00'), TIMESTAMP(@d, '11:15:00'), 'completada', 0, 50, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 3 DAY), '12:00:00'), TIMESTAMP(@d, '11:15:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c7, @st_lucia, @s_acrilicas, TIMESTAMP(@d, '11:30:00'), TIMESTAMP(@d, '13:00:00'), 'completada', 0, 180, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 5 DAY), '12:00:00'), TIMESTAMP(@d, '13:00:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c1, @st_ana, @s_brushing, TIMESTAMP(@d, '11:45:00'), TIMESTAMP(@d, '12:30:00'), 'completada', 0, 60, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 4 DAY), '12:00:00'), TIMESTAMP(@d, '12:30:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c18, @st_carla, @s_corte_dama, TIMESTAMP(@d, '12:15:00'), TIMESTAMP(@d, '13:00:00'), 'completada', 0, 80, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 5 DAY), '12:00:00'), TIMESTAMP(@d, '13:00:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c2, @st_ana, @s_mechas, TIMESTAMP(@d, '13:15:00'), TIMESTAMP(@d, '15:45:00'), 'completada', 0, 380, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 5 DAY), '12:00:00'), TIMESTAMP(@d, '15:45:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c16, @st_lucia, @s_cejas, TIMESTAMP(@d, '13:30:00'), TIMESTAMP(@d, '13:50:00'), 'completada', 0, 40, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 4 DAY), '12:00:00'), TIMESTAMP(@d, '13:50:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c12, @st_lucia, @s_acrilicas, TIMESTAMP(@d, '13:50:00'), TIMESTAMP(@d, '15:20:00'), 'completada', 0, 180, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 4 DAY), '12:00:00'), TIMESTAMP(@d, '15:20:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c16, @st_ana, @s_corte_dama, TIMESTAMP(@d, '15:45:00'), TIMESTAMP(@d, '16:30:00'), 'completada', 0, 80, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 3 DAY), '12:00:00'), TIMESTAMP(@d, '16:30:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c13, @st_carla, @cs, @num, TIMESTAMP(@d, '10:35:00'), 180.00, 0, 0, 180.00, 0.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '10:35:00'), TIMESTAMP(@d, '10:35:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_facial, NULL, @st_carla, 'LIMPIEZA FACIAL', 1, 180.00, 0, 180.00, TIMESTAMP(@d, '10:35:00'), TIMESTAMP(@d, '10:35:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 180.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '10:35:00'), TIMESTAMP(@d, '10:35:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c2, @st_lucia, @cs, @num, TIMESTAMP(@d, '11:05:00'), 180.00, 0, 0, 180.00, 10.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '11:05:00'), TIMESTAMP(@d, '11:05:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_acrilicas, NULL, @st_lucia, 'UÑAS ACRÍLICAS', 1, 180.00, 0, 180.00, TIMESTAMP(@d, '11:05:00'), TIMESTAMP(@d, '11:05:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 180.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '11:05:00'), TIMESTAMP(@d, '11:05:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, category, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 10.00, CONCAT('Propina venta ', @num), 'Propinas', @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '11:05:00'), TIMESTAMP(@d, '11:05:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c18, @st_carla, @cs, @num, TIMESTAMP(@d, '11:20:00'), 50.00, 0, 0, 50.00, 0.00, 'qr', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '11:20:00'), TIMESTAMP(@d, '11:20:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_corte_cab, NULL, @st_carla, 'CORTE CABALLERO', 1, 50.00, 0, 50.00, TIMESTAMP(@d, '11:20:00'), TIMESTAMP(@d, '11:20:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 50.00, CONCAT('Venta ', @num), @d, 'qr', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '11:20:00'), TIMESTAMP(@d, '11:20:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c17, @st_ana, @cs, @num, TIMESTAMP(@d, '11:35:00'), 415.00, 0, 0, 415.00, 20.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '11:35:00'), TIMESTAMP(@d, '11:35:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_mechas, NULL, @st_ana, 'MECHAS / BALAYAGE', 1, 380.00, 0, 380.00, TIMESTAMP(@d, '11:35:00'), TIMESTAMP(@d, '11:35:00') FROM DUAL WHERE @run = 1;
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, NULL, @p_esmalte, @st_ana, 'ESMALTE DE UÑAS', 1, 35.00, 0, 35.00, TIMESTAMP(@d, '11:35:00'), TIMESTAMP(@d, '11:35:00') FROM DUAL WHERE @run = 1;
UPDATE products SET current_stock = current_stock - 1 WHERE id = @p_esmalte AND @run = 1;
INSERT INTO inventory_movements (company_id, product_id, user_id, type, quantity, unit_cost, reference, notes, movement_date, created_at, updated_at)
SELECT @c, @p_esmalte, @u, 'salida', 1, 15, @num, CONCAT('Venta ', @num, ' · ', 'Datos de demostración'), TIMESTAMP(@d, '11:35:00'), NOW(), NOW() FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 415.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '11:35:00'), TIMESTAMP(@d, '11:35:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, category, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 20.00, CONCAT('Propina venta ', @num), 'Propinas', @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '11:35:00'), TIMESTAMP(@d, '11:35:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c1, @st_ana, @cs, @num, TIMESTAMP(@d, '12:35:00'), 60.00, 0, 0, 60.00, 10.00, 'transferencia', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '12:35:00'), TIMESTAMP(@d, '12:35:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_brushing, NULL, @st_ana, 'BRUSHING', 1, 60.00, 0, 60.00, TIMESTAMP(@d, '12:35:00'), TIMESTAMP(@d, '12:35:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 60.00, CONCAT('Venta ', @num), @d, 'transferencia', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '12:35:00'), TIMESTAMP(@d, '12:35:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, category, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 10.00, CONCAT('Propina venta ', @num), 'Propinas', @d, 'transferencia', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '12:35:00'), TIMESTAMP(@d, '12:35:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c7, @st_lucia, @cs, @num, TIMESTAMP(@d, '13:05:00'), 180.00, 0, 0, 180.00, 0.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '13:05:00'), TIMESTAMP(@d, '13:05:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_acrilicas, NULL, @st_lucia, 'UÑAS ACRÍLICAS', 1, 180.00, 0, 180.00, TIMESTAMP(@d, '13:05:00'), TIMESTAMP(@d, '13:05:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 180.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '13:05:00'), TIMESTAMP(@d, '13:05:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c18, @st_carla, @cs, @num, TIMESTAMP(@d, '13:05:00'), 125.00, 0, 0, 125.00, 0.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '13:05:00'), TIMESTAMP(@d, '13:05:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_corte_dama, NULL, @st_carla, 'CORTE DAMA', 1, 80.00, 0, 80.00, TIMESTAMP(@d, '13:05:00'), TIMESTAMP(@d, '13:05:00') FROM DUAL WHERE @run = 1;
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, NULL, @p_crema, @st_carla, 'CREMA DE MANOS', 1, 45.00, 0, 45.00, TIMESTAMP(@d, '13:05:00'), TIMESTAMP(@d, '13:05:00') FROM DUAL WHERE @run = 1;
UPDATE products SET current_stock = current_stock - 1 WHERE id = @p_crema AND @run = 1;
INSERT INTO inventory_movements (company_id, product_id, user_id, type, quantity, unit_cost, reference, notes, movement_date, created_at, updated_at)
SELECT @c, @p_crema, @u, 'salida', 1, 20, @num, CONCAT('Venta ', @num, ' · ', 'Datos de demostración'), TIMESTAMP(@d, '13:05:00'), NOW(), NOW() FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 125.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '13:05:00'), TIMESTAMP(@d, '13:05:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c16, @st_lucia, @cs, @num, TIMESTAMP(@d, '13:55:00'), 125.00, 0, 0, 125.00, 0.00, 'qr', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '13:55:00'), TIMESTAMP(@d, '13:55:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_cejas, NULL, @st_lucia, 'DISEÑO DE CEJAS', 1, 40.00, 0, 40.00, TIMESTAMP(@d, '13:55:00'), TIMESTAMP(@d, '13:55:00') FROM DUAL WHERE @run = 1;
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, NULL, @p_acond, @st_lucia, 'ACONDICIONADOR 400ML', 1, 85.00, 0, 85.00, TIMESTAMP(@d, '13:55:00'), TIMESTAMP(@d, '13:55:00') FROM DUAL WHERE @run = 1;
UPDATE products SET current_stock = current_stock - 1 WHERE id = @p_acond AND @run = 1;
INSERT INTO inventory_movements (company_id, product_id, user_id, type, quantity, unit_cost, reference, notes, movement_date, created_at, updated_at)
SELECT @c, @p_acond, @u, 'salida', 1, 45, @num, CONCAT('Venta ', @num, ' · ', 'Datos de demostración'), TIMESTAMP(@d, '13:55:00'), NOW(), NOW() FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 125.00, CONCAT('Venta ', @num), @d, 'qr', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '13:55:00'), TIMESTAMP(@d, '13:55:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, NULL, NULL, @cs, @num, TIMESTAMP(@d, '15:00:00'), 35.00, 0, 0, 35.00, 0.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '15:00:00'), TIMESTAMP(@d, '15:00:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, NULL, @p_esmalte, NULL, 'ESMALTE DE UÑAS', 1, 35.00, 0, 35.00, TIMESTAMP(@d, '15:00:00'), TIMESTAMP(@d, '15:00:00') FROM DUAL WHERE @run = 1;
UPDATE products SET current_stock = current_stock - 1 WHERE id = @p_esmalte AND @run = 1;
INSERT INTO inventory_movements (company_id, product_id, user_id, type, quantity, unit_cost, reference, notes, movement_date, created_at, updated_at)
SELECT @c, @p_esmalte, @u, 'salida', 1, 15, @num, CONCAT('Venta ', @num, ' · ', 'Datos de demostración'), TIMESTAMP(@d, '15:00:00'), NOW(), NOW() FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 35.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '15:00:00'), TIMESTAMP(@d, '15:00:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c12, @st_lucia, @cs, @num, TIMESTAMP(@d, '15:25:00'), 180.00, 0, 0, 180.00, 0.00, 'tarjeta', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '15:25:00'), TIMESTAMP(@d, '15:25:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_acrilicas, NULL, @st_lucia, 'UÑAS ACRÍLICAS', 1, 180.00, 0, 180.00, TIMESTAMP(@d, '15:25:00'), TIMESTAMP(@d, '15:25:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 180.00, CONCAT('Venta ', @num), @d, 'tarjeta', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '15:25:00'), TIMESTAMP(@d, '15:25:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c2, @st_ana, @cs, @num, TIMESTAMP(@d, '15:50:00'), 380.00, 0, 0, 380.00, 0.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '15:50:00'), TIMESTAMP(@d, '15:50:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_mechas, NULL, @st_ana, 'MECHAS / BALAYAGE', 1, 380.00, 0, 380.00, TIMESTAMP(@d, '15:50:00'), TIMESTAMP(@d, '15:50:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 380.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '15:50:00'), TIMESTAMP(@d, '15:50:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c16, @st_ana, @cs, @num, TIMESTAMP(@d, '16:35:00'), 165.00, 0, 0, 165.00, 0.00, 'tarjeta', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '16:35:00'), TIMESTAMP(@d, '16:35:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_corte_dama, NULL, @st_ana, 'CORTE DAMA', 1, 80.00, 0, 80.00, TIMESTAMP(@d, '16:35:00'), TIMESTAMP(@d, '16:35:00') FROM DUAL WHERE @run = 1;
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, NULL, @p_acond, @st_ana, 'ACONDICIONADOR 400ML', 1, 85.00, 0, 85.00, TIMESTAMP(@d, '16:35:00'), TIMESTAMP(@d, '16:35:00') FROM DUAL WHERE @run = 1;
UPDATE products SET current_stock = current_stock - 1 WHERE id = @p_acond AND @run = 1;
INSERT INTO inventory_movements (company_id, product_id, user_id, type, quantity, unit_cost, reference, notes, movement_date, created_at, updated_at)
SELECT @c, @p_acond, @u, 'salida', 1, 45, @num, CONCAT('Venta ', @num, ' · ', 'Datos de demostración'), TIMESTAMP(@d, '16:35:00'), NOW(), NOW() FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 165.00, CONCAT('Venta ', @num), @d, 'tarjeta', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '16:35:00'), TIMESTAMP(@d, '16:35:00') FROM DUAL WHERE @run = 1;
UPDATE cash_sessions SET opened_at = opened_at, status = 'closed', closed_at = TIMESTAMP(@d, '19:20:00'), expected_amount = 2315.00, closing_amount = 2305.00, difference = -10.00, closed_by = @u, updated_at = TIMESTAMP(@d, '19:20:00') WHERE id = @cs AND @run = 1;
INSERT INTO attendances (company_id, personal_id, work_date, check_in, check_out, in_source, out_source, in_marked_by, out_marked_by, scheduled_start, late_minutes, worked_minutes, notes, created_at, updated_at)
SELECT @c, @st_ana, @d, TIMESTAMP(@d, '09:09:00'), TIMESTAMP(@d, '19:23:00'), 'self', 'self', @u, @u, '09:00:00', 0, 614, 'Datos de demostración', TIMESTAMP(@d, '09:09:00'), TIMESTAMP(@d, '19:23:00') FROM DUAL WHERE @run = 1 AND @st_ana IS NOT NULL;
INSERT INTO attendances (company_id, personal_id, work_date, check_in, check_out, in_source, out_source, in_marked_by, out_marked_by, scheduled_start, late_minutes, worked_minutes, notes, created_at, updated_at)
SELECT @c, @st_carla, @d, TIMESTAMP(@d, '09:00:00'), TIMESTAMP(@d, '19:14:00'), 'self', 'self', @u, @u, '09:00:00', 0, 614, 'Datos de demostración', TIMESTAMP(@d, '09:00:00'), TIMESTAMP(@d, '19:14:00') FROM DUAL WHERE @run = 1 AND @st_carla IS NOT NULL;
INSERT INTO attendances (company_id, personal_id, work_date, check_in, check_out, in_source, out_source, in_marked_by, out_marked_by, scheduled_start, late_minutes, worked_minutes, notes, created_at, updated_at)
SELECT @c, @st_lucia, @d, TIMESTAMP(@d, '09:10:00'), TIMESTAMP(@d, '19:17:00'), 'self', 'self', @u, @u, '09:00:00', 0, 607, 'Datos de demostración', TIMESTAMP(@d, '09:10:00'), TIMESTAMP(@d, '19:17:00') FROM DUAL WHERE @run = 1 AND @st_lucia IS NOT NULL;
INSERT INTO attendances (company_id, personal_id, work_date, check_in, check_out, in_source, out_source, in_marked_by, out_marked_by, scheduled_start, late_minutes, worked_minutes, notes, created_at, updated_at)
SELECT @c, @pg, @d, TIMESTAMP(@d, '09:19:00'), TIMESTAMP(@d, '19:04:00'), 'self', 'self', @u, @u, '09:00:00', 19, 585, 'Datos de demostración', TIMESTAMP(@d, '09:19:00'), TIMESTAMP(@d, '19:04:00') FROM DUAL WHERE @run = 1 AND @pg IS NOT NULL;

-- ── Día CURDATE() - 3 ──
SET @d := DATE_SUB(CURDATE(), INTERVAL 3 DAY);
INSERT INTO cash_sessions (cash_register_id, personal_id, opening_amount, status, opened_at, notes, opened_by, created_at, updated_at)
SELECT @caja, @pg, 200, 'open', TIMESTAMP(@d, '08:50:00'), 'Datos de demostración', @u, TIMESTAMP(@d, '08:50:00'), TIMESTAMP(@d, '08:50:00') FROM DUAL WHERE @run = 1;
SET @cs := LAST_INSERT_ID();
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c11, @st_carla, @s_facial, TIMESTAMP(@d, '09:00:00'), TIMESTAMP(@d, '10:00:00'), 'cancelada', 0, 180, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 3 DAY), '12:00:00'), TIMESTAMP(@d, '10:00:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c3, @st_ana, @s_keratina, TIMESTAMP(@d, '09:15:00'), TIMESTAMP(@d, '11:15:00'), 'completada', 0, 300, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 1 DAY), '12:00:00'), TIMESTAMP(@d, '11:15:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c23, @st_lucia, @s_facial, TIMESTAMP(@d, '09:15:00'), TIMESTAMP(@d, '10:15:00'), 'completada', 0, 180, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 2 DAY), '12:00:00'), TIMESTAMP(@d, '10:15:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c6, @st_lucia, @s_semi, TIMESTAMP(@d, '10:15:00'), TIMESTAMP(@d, '11:15:00'), 'completada', 0, 120, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 4 DAY), '12:00:00'), TIMESTAMP(@d, '11:15:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c5, @st_carla, @s_corte_dama, TIMESTAMP(@d, '11:00:00'), TIMESTAMP(@d, '11:45:00'), 'completada', 0, 80, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 4 DAY), '12:00:00'), TIMESTAMP(@d, '11:45:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c11, @st_ana, @s_mechas, TIMESTAMP(@d, '11:15:00'), TIMESTAMP(@d, '13:45:00'), 'completada', 0, 380, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 3 DAY), '12:00:00'), TIMESTAMP(@d, '13:45:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c21, @st_ana, @s_tinte, TIMESTAMP(@d, '13:45:00'), TIMESTAMP(@d, '15:45:00'), 'completada', 0, 250, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 4 DAY), '12:00:00'), TIMESTAMP(@d, '15:45:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c23, @st_lucia, @cs, @num, TIMESTAMP(@d, '10:20:00'), 180.00, 0, 0, 180.00, 10.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '10:20:00'), TIMESTAMP(@d, '10:20:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_facial, NULL, @st_lucia, 'LIMPIEZA FACIAL', 1, 180.00, 0, 180.00, TIMESTAMP(@d, '10:20:00'), TIMESTAMP(@d, '10:20:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 180.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '10:20:00'), TIMESTAMP(@d, '10:20:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, category, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 10.00, CONCAT('Propina venta ', @num), 'Propinas', @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '10:20:00'), TIMESTAMP(@d, '10:20:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c3, @st_ana, @cs, @num, TIMESTAMP(@d, '11:20:00'), 420.00, 0, 0, 420.00, 10.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '11:20:00'), TIMESTAMP(@d, '11:20:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_keratina, NULL, @st_ana, 'TRATAMIENTO DE KERATINA', 1, 300.00, 0, 300.00, TIMESTAMP(@d, '11:20:00'), TIMESTAMP(@d, '11:20:00') FROM DUAL WHERE @run = 1;
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, NULL, @p_mascar, @st_ana, 'MASCARILLA CAPILAR 250G', 1, 120.00, 0, 120.00, TIMESTAMP(@d, '11:20:00'), TIMESTAMP(@d, '11:20:00') FROM DUAL WHERE @run = 1;
UPDATE products SET current_stock = current_stock - 1 WHERE id = @p_mascar AND @run = 1;
INSERT INTO inventory_movements (company_id, product_id, user_id, type, quantity, unit_cost, reference, notes, movement_date, created_at, updated_at)
SELECT @c, @p_mascar, @u, 'salida', 1, 60, @num, CONCAT('Venta ', @num, ' · ', 'Datos de demostración'), TIMESTAMP(@d, '11:20:00'), NOW(), NOW() FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 420.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '11:20:00'), TIMESTAMP(@d, '11:20:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, category, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 10.00, CONCAT('Propina venta ', @num), 'Propinas', @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '11:20:00'), TIMESTAMP(@d, '11:20:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c6, @st_lucia, @cs, @num, TIMESTAMP(@d, '11:20:00'), 120.00, 0, 0, 120.00, 0.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '11:20:00'), TIMESTAMP(@d, '11:20:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_semi, NULL, @st_lucia, 'UÑAS SEMIPERMANENTES', 1, 120.00, 0, 120.00, TIMESTAMP(@d, '11:20:00'), TIMESTAMP(@d, '11:20:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 120.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '11:20:00'), TIMESTAMP(@d, '11:20:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c5, @st_carla, @cs, @num, TIMESTAMP(@d, '11:50:00'), 80.00, 0, 0, 80.00, 0.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '11:50:00'), TIMESTAMP(@d, '11:50:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_corte_dama, NULL, @st_carla, 'CORTE DAMA', 1, 80.00, 0, 80.00, TIMESTAMP(@d, '11:50:00'), TIMESTAMP(@d, '11:50:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 80.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '11:50:00'), TIMESTAMP(@d, '11:50:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c11, @st_ana, @cs, @num, TIMESTAMP(@d, '13:50:00'), 380.00, 0, 0, 380.00, 0.00, 'tarjeta', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '13:50:00'), TIMESTAMP(@d, '13:50:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_mechas, NULL, @st_ana, 'MECHAS / BALAYAGE', 1, 380.00, 0, 380.00, TIMESTAMP(@d, '13:50:00'), TIMESTAMP(@d, '13:50:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 380.00, CONCAT('Venta ', @num), @d, 'tarjeta', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '13:50:00'), TIMESTAMP(@d, '13:50:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c21, @st_ana, @cs, @num, TIMESTAMP(@d, '15:50:00'), 250.00, 0, 0, 250.00, 10.00, 'qr', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '15:50:00'), TIMESTAMP(@d, '15:50:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_tinte, NULL, @st_ana, 'TINTE COMPLETO', 1, 250.00, 0, 250.00, TIMESTAMP(@d, '15:50:00'), TIMESTAMP(@d, '15:50:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 250.00, CONCAT('Venta ', @num), @d, 'qr', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '15:50:00'), TIMESTAMP(@d, '15:50:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, category, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 10.00, CONCAT('Propina venta ', @num), 'Propinas', @d, 'qr', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '15:50:00'), TIMESTAMP(@d, '15:50:00') FROM DUAL WHERE @run = 1;
UPDATE cash_sessions SET opened_at = opened_at, status = 'closed', closed_at = TIMESTAMP(@d, '19:20:00'), expected_amount = 1660.00, closing_amount = 1665.00, difference = 5.00, closed_by = @u, updated_at = TIMESTAMP(@d, '19:20:00') WHERE id = @cs AND @run = 1;
INSERT INTO attendances (company_id, personal_id, work_date, check_in, check_out, in_source, out_source, in_marked_by, out_marked_by, scheduled_start, late_minutes, worked_minutes, notes, created_at, updated_at)
SELECT @c, @st_ana, @d, TIMESTAMP(@d, '09:10:00'), TIMESTAMP(@d, '19:01:00'), 'self', 'self', @u, @u, '09:00:00', 0, 591, 'Datos de demostración', TIMESTAMP(@d, '09:10:00'), TIMESTAMP(@d, '19:01:00') FROM DUAL WHERE @run = 1 AND @st_ana IS NOT NULL;
INSERT INTO attendances (company_id, personal_id, work_date, check_in, check_out, in_source, out_source, in_marked_by, out_marked_by, scheduled_start, late_minutes, worked_minutes, notes, created_at, updated_at)
SELECT @c, @st_carla, @d, TIMESTAMP(@d, '09:00:00'), TIMESTAMP(@d, '19:09:00'), 'self', 'self', @u, @u, '09:00:00', 0, 609, 'Datos de demostración', TIMESTAMP(@d, '09:00:00'), TIMESTAMP(@d, '19:09:00') FROM DUAL WHERE @run = 1 AND @st_carla IS NOT NULL;
INSERT INTO attendances (company_id, personal_id, work_date, check_in, check_out, in_source, out_source, in_marked_by, out_marked_by, scheduled_start, late_minutes, worked_minutes, notes, created_at, updated_at)
SELECT @c, @st_lucia, @d, TIMESTAMP(@d, '09:14:00'), TIMESTAMP(@d, '19:24:00'), 'self', 'self', @u, @u, '09:00:00', 14, 610, 'Datos de demostración', TIMESTAMP(@d, '09:14:00'), TIMESTAMP(@d, '19:24:00') FROM DUAL WHERE @run = 1 AND @st_lucia IS NOT NULL;
INSERT INTO attendances (company_id, personal_id, work_date, check_in, check_out, in_source, out_source, in_marked_by, out_marked_by, scheduled_start, late_minutes, worked_minutes, notes, created_at, updated_at)
SELECT @c, @pg, @d, TIMESTAMP(@d, '09:06:00'), TIMESTAMP(@d, '19:07:00'), 'self', 'self', @u, @u, '09:00:00', 0, 601, 'Datos de demostración', TIMESTAMP(@d, '09:06:00'), TIMESTAMP(@d, '19:07:00') FROM DUAL WHERE @run = 1 AND @pg IS NOT NULL;

-- ── Día CURDATE() - 2 ──
SET @d := DATE_SUB(CURDATE(), INTERVAL 2 DAY);
INSERT INTO cash_sessions (cash_register_id, personal_id, opening_amount, status, opened_at, notes, opened_by, created_at, updated_at)
SELECT @caja, @pg, 200, 'open', TIMESTAMP(@d, '08:50:00'), 'Datos de demostración', @u, TIMESTAMP(@d, '08:50:00'), TIMESTAMP(@d, '08:50:00') FROM DUAL WHERE @run = 1;
SET @cs := LAST_INSERT_ID();
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c1, @st_ana, @s_corte_dama, TIMESTAMP(@d, '09:00:00'), TIMESTAMP(@d, '09:45:00'), 'completada', 0, 80, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 3 DAY), '12:00:00'), TIMESTAMP(@d, '09:45:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c9, @st_carla, @s_facial, TIMESTAMP(@d, '09:00:00'), TIMESTAMP(@d, '10:00:00'), 'completada', 0, 180, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 1 DAY), '12:00:00'), TIMESTAMP(@d, '10:00:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c1, @st_lucia, @s_acrilicas, TIMESTAMP(@d, '09:00:00'), TIMESTAMP(@d, '10:30:00'), 'completada', 0, 180, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 1 DAY), '12:00:00'), TIMESTAMP(@d, '10:30:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c17, @st_ana, @s_mechas, TIMESTAMP(@d, '10:15:00'), TIMESTAMP(@d, '12:45:00'), 'completada', 0, 380, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 5 DAY), '12:00:00'), TIMESTAMP(@d, '12:45:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c8, @st_carla, @s_corte_cab, TIMESTAMP(@d, '10:15:00'), TIMESTAMP(@d, '10:45:00'), 'completada', 0, 50, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 4 DAY), '12:00:00'), TIMESTAMP(@d, '10:45:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c1, @st_carla, @s_facial, TIMESTAMP(@d, '10:45:00'), TIMESTAMP(@d, '11:45:00'), 'completada', 0, 180, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 3 DAY), '12:00:00'), TIMESTAMP(@d, '11:45:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c19, @st_lucia, @s_cejas, TIMESTAMP(@d, '11:15:00'), TIMESTAMP(@d, '11:35:00'), 'completada', 0, 40, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 1 DAY), '12:00:00'), TIMESTAMP(@d, '11:35:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c1, @st_ana, @cs, @num, TIMESTAMP(@d, '09:50:00'), 80.00, 0, 0, 80.00, 0.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '09:50:00'), TIMESTAMP(@d, '09:50:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_corte_dama, NULL, @st_ana, 'CORTE DAMA', 1, 80.00, 0, 80.00, TIMESTAMP(@d, '09:50:00'), TIMESTAMP(@d, '09:50:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 80.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '09:50:00'), TIMESTAMP(@d, '09:50:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c9, @st_carla, @cs, @num, TIMESTAMP(@d, '10:05:00'), 180.00, 0, 0, 180.00, 20.00, 'transferencia', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '10:05:00'), TIMESTAMP(@d, '10:05:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_facial, NULL, @st_carla, 'LIMPIEZA FACIAL', 1, 180.00, 0, 180.00, TIMESTAMP(@d, '10:05:00'), TIMESTAMP(@d, '10:05:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 180.00, CONCAT('Venta ', @num), @d, 'transferencia', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '10:05:00'), TIMESTAMP(@d, '10:05:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, category, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 20.00, CONCAT('Propina venta ', @num), 'Propinas', @d, 'transferencia', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '10:05:00'), TIMESTAMP(@d, '10:05:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c1, @st_lucia, @cs, @num, TIMESTAMP(@d, '10:35:00'), 180.00, 0, 0, 180.00, 0.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '10:35:00'), TIMESTAMP(@d, '10:35:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_acrilicas, NULL, @st_lucia, 'UÑAS ACRÍLICAS', 1, 180.00, 0, 180.00, TIMESTAMP(@d, '10:35:00'), TIMESTAMP(@d, '10:35:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 180.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '10:35:00'), TIMESTAMP(@d, '10:35:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c8, @st_carla, @cs, @num, TIMESTAMP(@d, '10:50:00'), 135.00, 0, 0, 135.00, 0.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '10:50:00'), TIMESTAMP(@d, '10:50:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_corte_cab, NULL, @st_carla, 'CORTE CABALLERO', 1, 50.00, 0, 50.00, TIMESTAMP(@d, '10:50:00'), TIMESTAMP(@d, '10:50:00') FROM DUAL WHERE @run = 1;
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, NULL, @p_shampoo, @st_carla, 'SHAMPOO REPARADOR 400ML', 1, 85.00, 0, 85.00, TIMESTAMP(@d, '10:50:00'), TIMESTAMP(@d, '10:50:00') FROM DUAL WHERE @run = 1;
UPDATE products SET current_stock = current_stock - 1 WHERE id = @p_shampoo AND @run = 1;
INSERT INTO inventory_movements (company_id, product_id, user_id, type, quantity, unit_cost, reference, notes, movement_date, created_at, updated_at)
SELECT @c, @p_shampoo, @u, 'salida', 1, 45, @num, CONCAT('Venta ', @num, ' · ', 'Datos de demostración'), TIMESTAMP(@d, '10:50:00'), NOW(), NOW() FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 135.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '10:50:00'), TIMESTAMP(@d, '10:50:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c19, @st_lucia, @cs, @num, TIMESTAMP(@d, '11:40:00'), 75.00, 0, 0, 75.00, 0.00, 'transferencia', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '11:40:00'), TIMESTAMP(@d, '11:40:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_cejas, NULL, @st_lucia, 'DISEÑO DE CEJAS', 1, 40.00, 0, 40.00, TIMESTAMP(@d, '11:40:00'), TIMESTAMP(@d, '11:40:00') FROM DUAL WHERE @run = 1;
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, NULL, @p_esmalte, @st_lucia, 'ESMALTE DE UÑAS', 1, 35.00, 0, 35.00, TIMESTAMP(@d, '11:40:00'), TIMESTAMP(@d, '11:40:00') FROM DUAL WHERE @run = 1;
UPDATE products SET current_stock = current_stock - 1 WHERE id = @p_esmalte AND @run = 1;
INSERT INTO inventory_movements (company_id, product_id, user_id, type, quantity, unit_cost, reference, notes, movement_date, created_at, updated_at)
SELECT @c, @p_esmalte, @u, 'salida', 1, 15, @num, CONCAT('Venta ', @num, ' · ', 'Datos de demostración'), TIMESTAMP(@d, '11:40:00'), NOW(), NOW() FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 75.00, CONCAT('Venta ', @num), @d, 'transferencia', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '11:40:00'), TIMESTAMP(@d, '11:40:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c1, @st_carla, @cs, @num, TIMESTAMP(@d, '11:50:00'), 300.00, 0, 0, 300.00, 20.00, 'tarjeta', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '11:50:00'), TIMESTAMP(@d, '11:50:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_facial, NULL, @st_carla, 'LIMPIEZA FACIAL', 1, 180.00, 0, 180.00, TIMESTAMP(@d, '11:50:00'), TIMESTAMP(@d, '11:50:00') FROM DUAL WHERE @run = 1;
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, NULL, @p_mascar, @st_carla, 'MASCARILLA CAPILAR 250G', 1, 120.00, 0, 120.00, TIMESTAMP(@d, '11:50:00'), TIMESTAMP(@d, '11:50:00') FROM DUAL WHERE @run = 1;
UPDATE products SET current_stock = current_stock - 1 WHERE id = @p_mascar AND @run = 1;
INSERT INTO inventory_movements (company_id, product_id, user_id, type, quantity, unit_cost, reference, notes, movement_date, created_at, updated_at)
SELECT @c, @p_mascar, @u, 'salida', 1, 60, @num, CONCAT('Venta ', @num, ' · ', 'Datos de demostración'), TIMESTAMP(@d, '11:50:00'), NOW(), NOW() FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 300.00, CONCAT('Venta ', @num), @d, 'tarjeta', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '11:50:00'), TIMESTAMP(@d, '11:50:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, category, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 20.00, CONCAT('Propina venta ', @num), 'Propinas', @d, 'tarjeta', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '11:50:00'), TIMESTAMP(@d, '11:50:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c17, @st_ana, @cs, @num, TIMESTAMP(@d, '12:50:00'), 380.00, 0, 0, 380.00, 0.00, 'transferencia', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '12:50:00'), TIMESTAMP(@d, '12:50:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_mechas, NULL, @st_ana, 'MECHAS / BALAYAGE', 1, 380.00, 0, 380.00, TIMESTAMP(@d, '12:50:00'), TIMESTAMP(@d, '12:50:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 380.00, CONCAT('Venta ', @num), @d, 'transferencia', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '12:50:00'), TIMESTAMP(@d, '12:50:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, NULL, NULL, @cs, @num, TIMESTAMP(@d, '13:15:00'), 85.00, 0, 0, 85.00, 0.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '13:15:00'), TIMESTAMP(@d, '13:15:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, NULL, @p_shampoo, NULL, 'SHAMPOO REPARADOR 400ML', 1, 85.00, 0, 85.00, TIMESTAMP(@d, '13:15:00'), TIMESTAMP(@d, '13:15:00') FROM DUAL WHERE @run = 1;
UPDATE products SET current_stock = current_stock - 1 WHERE id = @p_shampoo AND @run = 1;
INSERT INTO inventory_movements (company_id, product_id, user_id, type, quantity, unit_cost, reference, notes, movement_date, created_at, updated_at)
SELECT @c, @p_shampoo, @u, 'salida', 1, 45, @num, CONCAT('Venta ', @num, ' · ', 'Datos de demostración'), TIMESTAMP(@d, '13:15:00'), NOW(), NOW() FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 85.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '13:15:00'), TIMESTAMP(@d, '13:15:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, category, movement_date, payment_method, created_by, created_at, updated_at)
SELECT @c, @cs, 'egreso', 50.00, 'Recarga de crédito celular', 'Servicios', @d, 'efectivo', @u, TIMESTAMP(@d, '13:00:00'), TIMESTAMP(@d, '13:00:00') FROM DUAL WHERE @run = 1;
UPDATE cash_sessions SET opened_at = opened_at, status = 'closed', closed_at = TIMESTAMP(@d, '19:20:00'), expected_amount = 1605.00, closing_amount = 1600.00, difference = -5.00, closed_by = @u, updated_at = TIMESTAMP(@d, '19:20:00') WHERE id = @cs AND @run = 1;
INSERT INTO attendances (company_id, personal_id, work_date, check_in, check_out, in_source, out_source, in_marked_by, out_marked_by, scheduled_start, late_minutes, worked_minutes, notes, created_at, updated_at)
SELECT @c, @st_ana, @d, TIMESTAMP(@d, '08:54:00'), TIMESTAMP(@d, '19:23:00'), 'self', 'self', @u, @u, '09:00:00', 0, 629, 'Datos de demostración', TIMESTAMP(@d, '08:54:00'), TIMESTAMP(@d, '19:23:00') FROM DUAL WHERE @run = 1 AND @st_ana IS NOT NULL;
INSERT INTO attendances (company_id, personal_id, work_date, check_in, check_out, in_source, out_source, in_marked_by, out_marked_by, scheduled_start, late_minutes, worked_minutes, notes, created_at, updated_at)
SELECT @c, @st_lucia, @d, TIMESTAMP(@d, '08:55:00'), TIMESTAMP(@d, '19:14:00'), 'self', 'self', @u, @u, '09:00:00', 0, 619, 'Datos de demostración', TIMESTAMP(@d, '08:55:00'), TIMESTAMP(@d, '19:14:00') FROM DUAL WHERE @run = 1 AND @st_lucia IS NOT NULL;
INSERT INTO attendances (company_id, personal_id, work_date, check_in, check_out, in_source, out_source, in_marked_by, out_marked_by, scheduled_start, late_minutes, worked_minutes, notes, created_at, updated_at)
SELECT @c, @pg, @d, TIMESTAMP(@d, '09:01:00'), TIMESTAMP(@d, '19:14:00'), 'self', 'self', @u, @u, '09:00:00', 0, 613, 'Datos de demostración', TIMESTAMP(@d, '09:01:00'), TIMESTAMP(@d, '19:14:00') FROM DUAL WHERE @run = 1 AND @pg IS NOT NULL;

-- ── Día CURDATE() - 1 ──
SET @d := DATE_SUB(CURDATE(), INTERVAL 1 DAY);
INSERT INTO cash_sessions (cash_register_id, personal_id, opening_amount, status, opened_at, notes, opened_by, created_at, updated_at)
SELECT @caja, @pg, 200, 'open', TIMESTAMP(@d, '08:50:00'), 'Datos de demostración', @u, TIMESTAMP(@d, '08:50:00'), TIMESTAMP(@d, '08:50:00') FROM DUAL WHERE @run = 1;
SET @cs := LAST_INSERT_ID();
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c0, @st_ana, @s_tinte, TIMESTAMP(@d, '09:00:00'), TIMESTAMP(@d, '11:00:00'), 'completada', 0, 250, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 3 DAY), '12:00:00'), TIMESTAMP(@d, '11:00:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c10, @st_carla, @s_cejas, TIMESTAMP(@d, '09:30:00'), TIMESTAMP(@d, '09:50:00'), 'completada', 0, 40, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 1 DAY), '12:00:00'), TIMESTAMP(@d, '09:50:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c21, @st_lucia, @s_semi, TIMESTAMP(@d, '09:30:00'), TIMESTAMP(@d, '10:30:00'), 'completada', 0, 120, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 2 DAY), '12:00:00'), TIMESTAMP(@d, '10:30:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c6, @st_carla, @s_keratina, TIMESTAMP(@d, '10:20:00'), TIMESTAMP(@d, '12:20:00'), 'cancelada', 0, 300, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 4 DAY), '12:00:00'), TIMESTAMP(@d, '12:20:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c16, @st_lucia, @s_facial, TIMESTAMP(@d, '11:15:00'), TIMESTAMP(@d, '12:15:00'), 'completada', 0, 180, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 2 DAY), '12:00:00'), TIMESTAMP(@d, '12:15:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c17, @st_ana, @s_corte_cab, TIMESTAMP(@d, '11:30:00'), TIMESTAMP(@d, '12:00:00'), 'completada', 0, 50, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 5 DAY), '12:00:00'), TIMESTAMP(@d, '12:00:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c10, @st_ana, @s_brushing, TIMESTAMP(@d, '12:30:00'), TIMESTAMP(@d, '13:15:00'), 'completada', 0, 60, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 3 DAY), '12:00:00'), TIMESTAMP(@d, '13:15:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c23, @st_carla, @s_cejas, TIMESTAMP(@d, '12:50:00'), TIMESTAMP(@d, '13:10:00'), 'completada', 0, 40, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 5 DAY), '12:00:00'), TIMESTAMP(@d, '13:10:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c8, @st_lucia, @s_facial, TIMESTAMP(@d, '13:00:00'), TIMESTAMP(@d, '14:00:00'), 'completada', 0, 180, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 5 DAY), '12:00:00'), TIMESTAMP(@d, '14:00:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c10, @st_carla, @cs, @num, TIMESTAMP(@d, '09:55:00'), 40.00, 0, 0, 40.00, 20.00, 'tarjeta', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '09:55:00'), TIMESTAMP(@d, '09:55:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_cejas, NULL, @st_carla, 'DISEÑO DE CEJAS', 1, 40.00, 0, 40.00, TIMESTAMP(@d, '09:55:00'), TIMESTAMP(@d, '09:55:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 40.00, CONCAT('Venta ', @num), @d, 'tarjeta', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '09:55:00'), TIMESTAMP(@d, '09:55:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, category, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 20.00, CONCAT('Propina venta ', @num), 'Propinas', @d, 'tarjeta', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '09:55:00'), TIMESTAMP(@d, '09:55:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c21, @st_lucia, @cs, @num, TIMESTAMP(@d, '10:35:00'), 120.00, 0, 0, 120.00, 10.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '10:35:00'), TIMESTAMP(@d, '10:35:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_semi, NULL, @st_lucia, 'UÑAS SEMIPERMANENTES', 1, 120.00, 0, 120.00, TIMESTAMP(@d, '10:35:00'), TIMESTAMP(@d, '10:35:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 120.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '10:35:00'), TIMESTAMP(@d, '10:35:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, category, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 10.00, CONCAT('Propina venta ', @num), 'Propinas', @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '10:35:00'), TIMESTAMP(@d, '10:35:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c0, @st_ana, @cs, @num, TIMESTAMP(@d, '11:05:00'), 250.00, 0, 0, 250.00, 0.00, 'qr', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '11:05:00'), TIMESTAMP(@d, '11:05:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_tinte, NULL, @st_ana, 'TINTE COMPLETO', 1, 250.00, 0, 250.00, TIMESTAMP(@d, '11:05:00'), TIMESTAMP(@d, '11:05:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 250.00, CONCAT('Venta ', @num), @d, 'qr', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '11:05:00'), TIMESTAMP(@d, '11:05:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c17, @st_ana, @cs, @num, TIMESTAMP(@d, '12:05:00'), 50.00, 0, 0, 50.00, 10.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '12:05:00'), TIMESTAMP(@d, '12:05:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_corte_cab, NULL, @st_ana, 'CORTE CABALLERO', 1, 50.00, 0, 50.00, TIMESTAMP(@d, '12:05:00'), TIMESTAMP(@d, '12:05:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 50.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '12:05:00'), TIMESTAMP(@d, '12:05:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, category, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 10.00, CONCAT('Propina venta ', @num), 'Propinas', @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '12:05:00'), TIMESTAMP(@d, '12:05:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c16, @st_lucia, @cs, @num, TIMESTAMP(@d, '12:20:00'), 180.00, 0, 0, 180.00, 0.00, 'qr', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '12:20:00'), TIMESTAMP(@d, '12:20:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_facial, NULL, @st_lucia, 'LIMPIEZA FACIAL', 1, 180.00, 0, 180.00, TIMESTAMP(@d, '12:20:00'), TIMESTAMP(@d, '12:20:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 180.00, CONCAT('Venta ', @num), @d, 'qr', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '12:20:00'), TIMESTAMP(@d, '12:20:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c8, NULL, @cs, @num, TIMESTAMP(@d, '13:00:00'), 110.00, 0, 0, 110.00, 0.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '13:00:00'), TIMESTAMP(@d, '13:00:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, NULL, @p_serum, NULL, 'SÉRUM DE ARGÁN 60ML', 1, 110.00, 0, 110.00, TIMESTAMP(@d, '13:00:00'), TIMESTAMP(@d, '13:00:00') FROM DUAL WHERE @run = 1;
UPDATE products SET current_stock = current_stock - 1 WHERE id = @p_serum AND @run = 1;
INSERT INTO inventory_movements (company_id, product_id, user_id, type, quantity, unit_cost, reference, notes, movement_date, created_at, updated_at)
SELECT @c, @p_serum, @u, 'salida', 1, 55, @num, CONCAT('Venta ', @num, ' · ', 'Datos de demostración'), TIMESTAMP(@d, '13:00:00'), NOW(), NOW() FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 110.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '13:00:00'), TIMESTAMP(@d, '13:00:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c23, @st_carla, @cs, @num, TIMESTAMP(@d, '13:15:00'), 40.00, 0, 0, 40.00, 20.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '13:15:00'), TIMESTAMP(@d, '13:15:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_cejas, NULL, @st_carla, 'DISEÑO DE CEJAS', 1, 40.00, 0, 40.00, TIMESTAMP(@d, '13:15:00'), TIMESTAMP(@d, '13:15:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 40.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '13:15:00'), TIMESTAMP(@d, '13:15:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, category, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 20.00, CONCAT('Propina venta ', @num), 'Propinas', @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '13:15:00'), TIMESTAMP(@d, '13:15:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c10, @st_ana, @cs, @num, TIMESTAMP(@d, '13:20:00'), 60.00, 0, 0, 60.00, 0.00, 'tarjeta', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '13:20:00'), TIMESTAMP(@d, '13:20:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_brushing, NULL, @st_ana, 'BRUSHING', 1, 60.00, 0, 60.00, TIMESTAMP(@d, '13:20:00'), TIMESTAMP(@d, '13:20:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 60.00, CONCAT('Venta ', @num), @d, 'tarjeta', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '13:20:00'), TIMESTAMP(@d, '13:20:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c8, @st_lucia, @cs, @num, TIMESTAMP(@d, '14:05:00'), 180.00, 0, 0, 180.00, 0.00, 'qr', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '14:05:00'), TIMESTAMP(@d, '14:05:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_facial, NULL, @st_lucia, 'LIMPIEZA FACIAL', 1, 180.00, 0, 180.00, TIMESTAMP(@d, '14:05:00'), TIMESTAMP(@d, '14:05:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 180.00, CONCAT('Venta ', @num), @d, 'qr', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '14:05:00'), TIMESTAMP(@d, '14:05:00') FROM DUAL WHERE @run = 1;
UPDATE cash_sessions SET opened_at = opened_at, status = 'closed', closed_at = TIMESTAMP(@d, '19:20:00'), expected_amount = 1290.00, closing_amount = 1280.00, difference = -10.00, closed_by = @u, updated_at = TIMESTAMP(@d, '19:20:00') WHERE id = @cs AND @run = 1;
INSERT INTO attendances (company_id, personal_id, work_date, check_in, check_out, in_source, out_source, in_marked_by, out_marked_by, scheduled_start, late_minutes, worked_minutes, notes, created_at, updated_at)
SELECT @c, @st_ana, @d, TIMESTAMP(@d, '08:47:00'), TIMESTAMP(@d, '19:09:00'), 'self', 'self', @u, @u, '09:00:00', 0, 622, 'Datos de demostración', TIMESTAMP(@d, '08:47:00'), TIMESTAMP(@d, '19:09:00') FROM DUAL WHERE @run = 1 AND @st_ana IS NOT NULL;
INSERT INTO attendances (company_id, personal_id, work_date, check_in, check_out, in_source, out_source, in_marked_by, out_marked_by, scheduled_start, late_minutes, worked_minutes, notes, created_at, updated_at)
SELECT @c, @st_carla, @d, TIMESTAMP(@d, '08:53:00'), TIMESTAMP(@d, '19:20:00'), 'self', 'self', @u, @u, '09:00:00', 0, 627, 'Datos de demostración', TIMESTAMP(@d, '08:53:00'), TIMESTAMP(@d, '19:20:00') FROM DUAL WHERE @run = 1 AND @st_carla IS NOT NULL;
INSERT INTO attendances (company_id, personal_id, work_date, check_in, check_out, in_source, out_source, in_marked_by, out_marked_by, scheduled_start, late_minutes, worked_minutes, notes, created_at, updated_at)
SELECT @c, @st_lucia, @d, TIMESTAMP(@d, '09:05:00'), TIMESTAMP(@d, '19:13:00'), 'self', 'self', @u, @u, '09:00:00', 0, 608, 'Datos de demostración', TIMESTAMP(@d, '09:05:00'), TIMESTAMP(@d, '19:13:00') FROM DUAL WHERE @run = 1 AND @st_lucia IS NOT NULL;
INSERT INTO attendances (company_id, personal_id, work_date, check_in, check_out, in_source, out_source, in_marked_by, out_marked_by, scheduled_start, late_minutes, worked_minutes, notes, created_at, updated_at)
SELECT @c, @pg, @d, TIMESTAMP(@d, '08:45:00'), TIMESTAMP(@d, '19:19:00'), 'self', 'self', @u, @u, '09:00:00', 0, 634, 'Datos de demostración', TIMESTAMP(@d, '08:45:00'), TIMESTAMP(@d, '19:19:00') FROM DUAL WHERE @run = 1 AND @pg IS NOT NULL;

-- ── Día CURDATE() - 0 (hoy: caja abierta y citas de la tarde por atender) ──
SET @d := DATE_SUB(CURDATE(), INTERVAL 0 DAY);
INSERT INTO cash_sessions (cash_register_id, personal_id, opening_amount, status, opened_at, notes, opened_by, created_at, updated_at)
SELECT @caja, @pg, 200, 'open', TIMESTAMP(@d, '08:50:00'), 'Datos de demostración', @u, TIMESTAMP(@d, '08:50:00'), TIMESTAMP(@d, '08:50:00') FROM DUAL WHERE @run = 1;
SET @cs := LAST_INSERT_ID();
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c4, @st_carla, @s_brushing, TIMESTAMP(@d, '09:15:00'), TIMESTAMP(@d, '10:00:00'), 'completada', 0, 60, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 3 DAY), '12:00:00'), TIMESTAMP(@d, '10:00:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c17, @st_ana, @s_keratina, TIMESTAMP(@d, '09:30:00'), TIMESTAMP(@d, '11:30:00'), 'completada', 0, 300, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 5 DAY), '12:00:00'), TIMESTAMP(@d, '11:30:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c20, @st_lucia, @s_manicure, TIMESTAMP(@d, '09:30:00'), TIMESTAMP(@d, '10:15:00'), 'completada', 0, 60, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 5 DAY), '12:00:00'), TIMESTAMP(@d, '10:15:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c6, @st_carla, @s_corte_cab, TIMESTAMP(@d, '10:15:00'), TIMESTAMP(@d, '10:45:00'), 'completada', 0, 50, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 1 DAY), '12:00:00'), TIMESTAMP(@d, '10:45:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c5, @st_carla, @s_facial, TIMESTAMP(@d, '11:15:00'), TIMESTAMP(@d, '12:15:00'), 'completada', 0, 180, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 4 DAY), '12:00:00'), TIMESTAMP(@d, '12:15:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c9, @st_lucia, @s_semi, TIMESTAMP(@d, '11:15:00'), TIMESTAMP(@d, '12:15:00'), 'completada', 0, 120, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 5 DAY), '12:00:00'), TIMESTAMP(@d, '12:15:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c14, @st_ana, @s_corte_dama, TIMESTAMP(@d, '11:45:00'), TIMESTAMP(@d, '12:30:00'), 'completada', 0, 80, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 3 DAY), '12:00:00'), TIMESTAMP(@d, '12:30:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c6, @st_lucia, @s_cejas, TIMESTAMP(@d, '12:15:00'), TIMESTAMP(@d, '12:35:00'), 'confirmada', 0, 40, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 4 DAY), '12:00:00'), TIMESTAMP(@d, '12:35:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c11, @st_lucia, @s_acrilicas, TIMESTAMP(@d, '12:50:00'), TIMESTAMP(@d, '14:20:00'), 'pendiente', 0, 180, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 1 DAY), '12:00:00'), TIMESTAMP(@d, '14:20:00') FROM DUAL WHERE @run = 1;
INSERT INTO appointments (company_id, client_id, personal_id, service_id, starts_at, ends_at, status, outside_schedule, price, notes, created_by, created_at, updated_at)
SELECT @c, @c7, @st_carla, @s_mechas, TIMESTAMP(@d, '13:15:00'), TIMESTAMP(@d, '15:45:00'), 'pendiente', 0, 380, 'Datos de demostración', @u, TIMESTAMP(DATE_SUB(@d, INTERVAL 3 DAY), '12:00:00'), TIMESTAMP(@d, '15:45:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c4, @st_carla, @cs, @num, TIMESTAMP(@d, '10:05:00'), 60.00, 0, 0, 60.00, 20.00, 'tarjeta', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '10:05:00'), TIMESTAMP(@d, '10:05:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_brushing, NULL, @st_carla, 'BRUSHING', 1, 60.00, 0, 60.00, TIMESTAMP(@d, '10:05:00'), TIMESTAMP(@d, '10:05:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 60.00, CONCAT('Venta ', @num), @d, 'tarjeta', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '10:05:00'), TIMESTAMP(@d, '10:05:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, category, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 20.00, CONCAT('Propina venta ', @num), 'Propinas', @d, 'tarjeta', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '10:05:00'), TIMESTAMP(@d, '10:05:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c20, @st_lucia, @cs, @num, TIMESTAMP(@d, '10:20:00'), 170.00, 0, 0, 170.00, 0.00, 'transferencia', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '10:20:00'), TIMESTAMP(@d, '10:20:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_manicure, NULL, @st_lucia, 'MANICURE', 1, 60.00, 0, 60.00, TIMESTAMP(@d, '10:20:00'), TIMESTAMP(@d, '10:20:00') FROM DUAL WHERE @run = 1;
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, NULL, @p_serum, @st_lucia, 'SÉRUM DE ARGÁN 60ML', 1, 110.00, 0, 110.00, TIMESTAMP(@d, '10:20:00'), TIMESTAMP(@d, '10:20:00') FROM DUAL WHERE @run = 1;
UPDATE products SET current_stock = current_stock - 1 WHERE id = @p_serum AND @run = 1;
INSERT INTO inventory_movements (company_id, product_id, user_id, type, quantity, unit_cost, reference, notes, movement_date, created_at, updated_at)
SELECT @c, @p_serum, @u, 'salida', 1, 55, @num, CONCAT('Venta ', @num, ' · ', 'Datos de demostración'), TIMESTAMP(@d, '10:20:00'), NOW(), NOW() FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 170.00, CONCAT('Venta ', @num), @d, 'transferencia', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '10:20:00'), TIMESTAMP(@d, '10:20:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c6, @st_carla, @cs, @num, TIMESTAMP(@d, '10:50:00'), 50.00, 0, 0, 50.00, 0.00, 'qr', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '10:50:00'), TIMESTAMP(@d, '10:50:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_corte_cab, NULL, @st_carla, 'CORTE CABALLERO', 1, 50.00, 0, 50.00, TIMESTAMP(@d, '10:50:00'), TIMESTAMP(@d, '10:50:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 50.00, CONCAT('Venta ', @num), @d, 'qr', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '10:50:00'), TIMESTAMP(@d, '10:50:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c17, @st_ana, @cs, @num, TIMESTAMP(@d, '11:35:00'), 300.00, 0, 0, 300.00, 0.00, 'qr', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '11:35:00'), TIMESTAMP(@d, '11:35:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_keratina, NULL, @st_ana, 'TRATAMIENTO DE KERATINA', 1, 300.00, 0, 300.00, TIMESTAMP(@d, '11:35:00'), TIMESTAMP(@d, '11:35:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 300.00, CONCAT('Venta ', @num), @d, 'qr', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '11:35:00'), TIMESTAMP(@d, '11:35:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c9, NULL, @cs, @num, TIMESTAMP(@d, '11:45:00'), 220.00, 0, 0, 220.00, 0.00, 'tarjeta', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '11:45:00'), TIMESTAMP(@d, '11:45:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, NULL, @p_serum, NULL, 'SÉRUM DE ARGÁN 60ML', 2, 110.00, 0, 220.00, TIMESTAMP(@d, '11:45:00'), TIMESTAMP(@d, '11:45:00') FROM DUAL WHERE @run = 1;
UPDATE products SET current_stock = current_stock - 2 WHERE id = @p_serum AND @run = 1;
INSERT INTO inventory_movements (company_id, product_id, user_id, type, quantity, unit_cost, reference, notes, movement_date, created_at, updated_at)
SELECT @c, @p_serum, @u, 'salida', 2, 55, @num, CONCAT('Venta ', @num, ' · ', 'Datos de demostración'), TIMESTAMP(@d, '11:45:00'), NOW(), NOW() FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 220.00, CONCAT('Venta ', @num), @d, 'tarjeta', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '11:45:00'), TIMESTAMP(@d, '11:45:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c5, @st_carla, @cs, @num, TIMESTAMP(@d, '12:20:00'), 265.00, 0, 0, 265.00, 0.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '12:20:00'), TIMESTAMP(@d, '12:20:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_facial, NULL, @st_carla, 'LIMPIEZA FACIAL', 1, 180.00, 0, 180.00, TIMESTAMP(@d, '12:20:00'), TIMESTAMP(@d, '12:20:00') FROM DUAL WHERE @run = 1;
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, NULL, @p_acond, @st_carla, 'ACONDICIONADOR 400ML', 1, 85.00, 0, 85.00, TIMESTAMP(@d, '12:20:00'), TIMESTAMP(@d, '12:20:00') FROM DUAL WHERE @run = 1;
UPDATE products SET current_stock = current_stock - 1 WHERE id = @p_acond AND @run = 1;
INSERT INTO inventory_movements (company_id, product_id, user_id, type, quantity, unit_cost, reference, notes, movement_date, created_at, updated_at)
SELECT @c, @p_acond, @u, 'salida', 1, 45, @num, CONCAT('Venta ', @num, ' · ', 'Datos de demostración'), TIMESTAMP(@d, '12:20:00'), NOW(), NOW() FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 265.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '12:20:00'), TIMESTAMP(@d, '12:20:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c9, @st_lucia, @cs, @num, TIMESTAMP(@d, '12:20:00'), 155.00, 0, 0, 155.00, 0.00, 'tarjeta', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '12:20:00'), TIMESTAMP(@d, '12:20:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_semi, NULL, @st_lucia, 'UÑAS SEMIPERMANENTES', 1, 120.00, 0, 120.00, TIMESTAMP(@d, '12:20:00'), TIMESTAMP(@d, '12:20:00') FROM DUAL WHERE @run = 1;
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, NULL, @p_esmalte, @st_lucia, 'ESMALTE DE UÑAS', 1, 35.00, 0, 35.00, TIMESTAMP(@d, '12:20:00'), TIMESTAMP(@d, '12:20:00') FROM DUAL WHERE @run = 1;
UPDATE products SET current_stock = current_stock - 1 WHERE id = @p_esmalte AND @run = 1;
INSERT INTO inventory_movements (company_id, product_id, user_id, type, quantity, unit_cost, reference, notes, movement_date, created_at, updated_at)
SELECT @c, @p_esmalte, @u, 'salida', 1, 15, @num, CONCAT('Venta ', @num, ' · ', 'Datos de demostración'), TIMESTAMP(@d, '12:20:00'), NOW(), NOW() FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 155.00, CONCAT('Venta ', @num), @d, 'tarjeta', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '12:20:00'), TIMESTAMP(@d, '12:20:00') FROM DUAL WHERE @run = 1;
SET @n := @n + @run; SET @num := CONCAT(@prefix, LPAD(@n, @pad, '0'));
INSERT INTO sales (company_id, client_id, personal_id, cash_session_id, sale_number, sale_date, subtotal, tax, discount, total, tip, payment_method, status, notes, created_by, created_at, updated_at)
SELECT @c, @c14, @st_ana, @cs, @num, TIMESTAMP(@d, '12:35:00'), 200.00, 0, 0, 200.00, 20.00, 'efectivo', 'completada', 'Datos de demostración', @u, TIMESTAMP(@d, '12:35:00'), TIMESTAMP(@d, '12:35:00') FROM DUAL WHERE @run = 1;
SET @sale := LAST_INSERT_ID();
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, @s_corte_dama, NULL, @st_ana, 'CORTE DAMA', 1, 80.00, 0, 80.00, TIMESTAMP(@d, '12:35:00'), TIMESTAMP(@d, '12:35:00') FROM DUAL WHERE @run = 1;
INSERT INTO sale_details (sale_id, service_id, product_id, personal_id, description, quantity, unit_price, discount, total, created_at, updated_at)
SELECT @sale, NULL, @p_mascar, @st_ana, 'MASCARILLA CAPILAR 250G', 1, 120.00, 0, 120.00, TIMESTAMP(@d, '12:35:00'), TIMESTAMP(@d, '12:35:00') FROM DUAL WHERE @run = 1;
UPDATE products SET current_stock = current_stock - 1 WHERE id = @p_mascar AND @run = 1;
INSERT INTO inventory_movements (company_id, product_id, user_id, type, quantity, unit_cost, reference, notes, movement_date, created_at, updated_at)
SELECT @c, @p_mascar, @u, 'salida', 1, 60, @num, CONCAT('Venta ', @num, ' · ', 'Datos de demostración'), TIMESTAMP(@d, '12:35:00'), NOW(), NOW() FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 200.00, CONCAT('Venta ', @num), @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '12:35:00'), TIMESTAMP(@d, '12:35:00') FROM DUAL WHERE @run = 1;
INSERT INTO cash_movements (company_id, cash_session_id, type, amount, concept, category, movement_date, payment_method, reference, created_by, created_at, updated_at)
SELECT @c, @cs, 'ingreso', 20.00, CONCAT('Propina venta ', @num), 'Propinas', @d, 'efectivo', CAST(@sale AS CHAR), @u, TIMESTAMP(@d, '12:35:00'), TIMESTAMP(@d, '12:35:00') FROM DUAL WHERE @run = 1;
INSERT INTO attendances (company_id, personal_id, work_date, check_in, check_out, in_source, out_source, in_marked_by, out_marked_by, scheduled_start, late_minutes, worked_minutes, notes, created_at, updated_at)
SELECT @c, @st_ana, @d, TIMESTAMP(@d, '08:53:00'), NULL, 'self', NULL, @u, NULL, '09:00:00', 0, 0, 'Datos de demostración', TIMESTAMP(@d, '08:53:00'), TIMESTAMP(@d, '08:53:00') FROM DUAL WHERE @run = 1 AND @st_ana IS NOT NULL;
INSERT INTO attendances (company_id, personal_id, work_date, check_in, check_out, in_source, out_source, in_marked_by, out_marked_by, scheduled_start, late_minutes, worked_minutes, notes, created_at, updated_at)
SELECT @c, @st_carla, @d, TIMESTAMP(@d, '09:17:00'), NULL, 'self', NULL, @u, NULL, '09:00:00', 17, 0, 'Datos de demostración', TIMESTAMP(@d, '09:17:00'), TIMESTAMP(@d, '09:17:00') FROM DUAL WHERE @run = 1 AND @st_carla IS NOT NULL;
INSERT INTO attendances (company_id, personal_id, work_date, check_in, check_out, in_source, out_source, in_marked_by, out_marked_by, scheduled_start, late_minutes, worked_minutes, notes, created_at, updated_at)
SELECT @c, @st_lucia, @d, TIMESTAMP(@d, '08:49:00'), NULL, 'self', NULL, @u, NULL, '09:00:00', 0, 0, 'Datos de demostración', TIMESTAMP(@d, '08:49:00'), TIMESTAMP(@d, '08:49:00') FROM DUAL WHERE @run = 1 AND @st_lucia IS NOT NULL;
INSERT INTO attendances (company_id, personal_id, work_date, check_in, check_out, in_source, out_source, in_marked_by, out_marked_by, scheduled_start, late_minutes, worked_minutes, notes, created_at, updated_at)
SELECT @c, @pg, @d, TIMESTAMP(@d, '09:01:00'), NULL, 'self', NULL, @u, NULL, '09:00:00', 0, 0, 'Datos de demostración', TIMESTAMP(@d, '09:01:00'), TIMESTAMP(@d, '09:01:00') FROM DUAL WHERE @run = 1 AND @pg IS NOT NULL;

-- ── Sucursal (y su almacén, obligatorio) ─────────────────────────────────
-- Se crean solo si no existen (se buscan por código). Luego se asigna la
-- sucursal a la actividad de demostración que aún no tenga una.
SET @c := 2;
SET @okb := (SELECT COUNT(*) FROM companies WHERE id = @c);

INSERT INTO warehouses (company_id, name, code, location, description, active, created_at, updated_at)
SELECT @c, 'ALMACÉN CENTRAL', 'DEMO2-WH01', 'Sucursal central', 'Datos de demostración', 1, NOW(), NOW()
FROM DUAL WHERE @okb > 0 AND NOT EXISTS (SELECT 1 FROM warehouses WHERE code = 'DEMO2-WH01');
UPDATE warehouses SET deleted_at = NULL WHERE company_id = @c AND code = 'DEMO2-WH01' AND deleted_at IS NOT NULL;
SET @wh := (SELECT id FROM warehouses WHERE company_id = @c AND code = 'DEMO2-WH01' LIMIT 1);

INSERT INTO branches (company_id, warehouse_id, name, code, phone, address, manager_name, active, created_at, updated_at)
SELECT @c, @wh, 'SUCURSAL CENTRAL', 'DEMO2-SC01', '33445566', 'Av. Principal #123', 'GERENTE_1', 1, NOW(), NOW()
FROM DUAL WHERE @okb > 0 AND @wh IS NOT NULL AND NOT EXISTS (SELECT 1 FROM branches WHERE code = 'DEMO2-SC01');
UPDATE branches SET deleted_at = NULL WHERE company_id = @c AND code = 'DEMO2-SC01' AND deleted_at IS NOT NULL;
SET @br := (SELECT id FROM branches WHERE company_id = @c AND code = 'DEMO2-SC01' LIMIT 1);

-- Actividad de demostración sin sucursal → sucursal central (no toca datos reales).
UPDATE sales SET branch_id = @br WHERE company_id = @c AND notes = 'Datos de demostración' AND branch_id IS NULL AND @br IS NOT NULL;
UPDATE appointments SET branch_id = @br WHERE company_id = @c AND notes = 'Datos de demostración' AND branch_id IS NULL AND @br IS NOT NULL;
UPDATE inventory_movements SET branch_id = @br, warehouse_id = COALESCE(warehouse_id, @wh)
WHERE company_id = @c AND (notes = 'Datos de demostración' OR notes LIKE '% · Datos de demostración') AND branch_id IS NULL AND @br IS NOT NULL;
UPDATE cash_registers SET branch_id = @br WHERE company_id = @c AND name = 'CAJA PRINCIPAL' AND code = 'CJ-01' AND branch_id IS NULL AND @br IS NOT NULL;

SELECT IF(@br IS NULL, 'No se pudo crear la sucursal (¿existe la empresa 2?).', 'Sucursal central lista y asignada a los datos de demostración.') AS sucursal,
       (SELECT COUNT(*) FROM sales WHERE company_id = @c AND branch_id = @br) AS ventas_en_sucursal,
       (SELECT COUNT(*) FROM appointments WHERE company_id = @c AND branch_id = @br) AS citas_en_sucursal;

-- ── Resumen ──────────────────────────────────────────────────────────────
SELECT IF(@run = 1, 'Listo: datos de demostración insertados.', 'Sin cambios en la actividad (ya existía o faltan requisitos).') AS resultado,
       (SELECT COUNT(*) FROM sales WHERE company_id = @c AND notes = 'Datos de demostración') AS ventas_demo,
       (SELECT COUNT(*) FROM appointments WHERE company_id = @c AND notes = 'Datos de demostración') AS citas_demo,
       (SELECT COALESCE(SUM(total), 0) FROM sales WHERE company_id = @c AND notes = 'Datos de demostración') AS total_ventas_demo;
