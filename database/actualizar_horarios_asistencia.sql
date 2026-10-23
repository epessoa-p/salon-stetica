-- =====================================================================
--  Glamora · Horarios del personal y Asistencia
--
--  SEGURO DE REEJECUTAR: cada tabla, columna, permiso y asignación
--  comprueba antes si ya existe, así que ejecutarlo de nuevo no duplica
--  ni rompe nada. Probado dos veces sobre una base de prueba.
--  Compatible con MySQL 5.7+ y MariaDB 10.2+ (sin DELIMITER).
--
--  ORDEN EN PRODUCCIÓN: actualizar_produccion.sql y
--  actualizar_membresias_fidelizacion.sql antes que este archivo.
--  Ejecutar completo: phpMyAdmin → pestaña SQL → pegar y ejecutar.
-- =====================================================================


-- ---------------------------------------------------------------------
-- 1. Horario semanal: uno o más bloques por día (turno partido)
--    weekday: 1 = lunes … 7 = domingo (ISO)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `staff_schedules` (
    `id`          BIGINT UNSIGNED  NOT NULL AUTO_INCREMENT,
    `company_id`  BIGINT UNSIGNED  NOT NULL,
    `personal_id` BIGINT UNSIGNED  NOT NULL,
    `weekday`     TINYINT UNSIGNED NOT NULL COMMENT '1 lunes … 7 domingo',
    `starts_time` TIME             NOT NULL,
    `ends_time`   TIME             NOT NULL,
    `created_at`  TIMESTAMP        NULL,
    `updated_at`  TIMESTAMP        NULL,
    PRIMARY KEY (`id`),
    KEY `staff_schedules_personal_weekday_index` (`company_id`, `personal_id`, `weekday`),
    CONSTRAINT `staff_schedules_company_id_foreign`  FOREIGN KEY (`company_id`)  REFERENCES `companies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `staff_schedules_personal_id_foreign` FOREIGN KEY (`personal_id`) REFERENCES `personals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 2. Excepciones por fecha
--    off    = no trabaja en el rango (día libre, vacaciones, permiso)
--    custom = ese día trabaja en un horario especial (reemplaza al semanal)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `staff_schedule_exceptions` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `company_id`  BIGINT UNSIGNED NOT NULL,
    `personal_id` BIGINT UNSIGNED NOT NULL,
    `type`        VARCHAR(10)     NOT NULL COMMENT 'off | custom',
    `date_from`   DATE            NOT NULL,
    `date_to`     DATE            NOT NULL,
    `starts_time` TIME            NULL COMMENT 'solo custom',
    `ends_time`   TIME            NULL COMMENT 'solo custom',
    `reason`      VARCHAR(150)    NULL,
    `created_by`  BIGINT UNSIGNED NULL,
    `created_at`  TIMESTAMP       NULL,
    `updated_at`  TIMESTAMP       NULL,
    PRIMARY KEY (`id`),
    KEY `staff_schedule_exceptions_personal_dates_index` (`company_id`, `personal_id`, `date_from`, `date_to`),
    CONSTRAINT `staff_schedule_exceptions_company_id_foreign`  FOREIGN KEY (`company_id`)  REFERENCES `companies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `staff_schedule_exceptions_personal_id_foreign` FOREIGN KEY (`personal_id`) REFERENCES `personals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 3. Asistencia: un registro por cada entrada/salida (admite turno partido)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `attendances` (
    `id`              BIGINT UNSIGNED   NOT NULL AUTO_INCREMENT,
    `company_id`      BIGINT UNSIGNED   NOT NULL,
    `personal_id`     BIGINT UNSIGNED   NOT NULL,
    `work_date`       DATE              NOT NULL,
    `check_in`        DATETIME          NOT NULL,
    `check_out`       DATETIME          NULL,
    `in_source`       VARCHAR(10)       NOT NULL DEFAULT 'self' COMMENT 'self = la propia persona, staff = recepción',
    `out_source`      VARCHAR(10)       NULL,
    `in_marked_by`    BIGINT UNSIGNED   NULL,
    `out_marked_by`   BIGINT UNSIGNED   NULL,
    `scheduled_start` TIME              NULL COMMENT 'inicio de turno vigente al marcar (copia)',
    `late_minutes`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `worked_minutes`  INT UNSIGNED      NULL,
    `notes`           VARCHAR(255)      NULL,
    `edited_by`       BIGINT UNSIGNED   NULL,
    `edit_reason`     VARCHAR(255)      NULL,
    `created_at`      TIMESTAMP         NULL,
    `updated_at`      TIMESTAMP         NULL,
    PRIMARY KEY (`id`),
    KEY `attendances_personal_date_index` (`company_id`, `personal_id`, `work_date`),
    KEY `attendances_company_date_index` (`company_id`, `work_date`),
    CONSTRAINT `attendances_company_id_foreign`  FOREIGN KEY (`company_id`)  REFERENCES `companies` (`id`) ON DELETE CASCADE,
    CONSTRAINT `attendances_personal_id_foreign` FOREIGN KEY (`personal_id`) REFERENCES `personals` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ---------------------------------------------------------------------
-- 4. Cita agendada fuera del horario (se permitió con "agendar de todos modos")
-- ---------------------------------------------------------------------
SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `appointments` ADD COLUMN `outside_schedule` TINYINT(1) NOT NULL DEFAULT 0 AFTER `status`', 'DO 0')
           FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointments' AND COLUMN_NAME = 'outside_schedule');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;

-- Índice para buscar solapes del mismo profesional rápido
SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `appointments` ADD INDEX `appointments_personal_starts_index` (`personal_id`, `starts_at`)', 'DO 0')
           FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'appointments' AND INDEX_NAME = 'appointments_personal_starts_index');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;


-- ---------------------------------------------------------------------
-- 5. Tolerancia de tardanza (minutos) en las preferencias de la empresa
-- ---------------------------------------------------------------------
SET @s := (SELECT IF(COUNT(*) = 0, 'ALTER TABLE `company_settings` ADD COLUMN `attendance_tolerance_minutes` SMALLINT UNSIGNED NOT NULL DEFAULT 10 AFTER `appointment_slot_minutes`', 'DO 0')
           FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'company_settings' AND COLUMN_NAME = 'attendance_tolerance_minutes');
PREPARE q FROM @s; EXECUTE q; DEALLOCATE PREPARE q;


-- ---------------------------------------------------------------------
-- 6. Permisos
--    schedules.view / edit    → ver y editar horarios y excepciones
--    attendance.view          → ver la asistencia de todos y los reportes
--    attendance.mark          → marcar entrada/salida por otra persona
--    attendance.edit          → corregir marcas (con motivo)
--    (marcar la propia asistencia no necesita permiso)
-- ---------------------------------------------------------------------
INSERT INTO `permissions` (`name`, `slug`, `module`, `description`, `created_at`, `updated_at`)
SELECT * FROM (
              SELECT 'Ver Horarios' AS name, 'schedules.view' AS slug, 'schedules' AS module, NULL AS description, NOW() AS created_at, NOW() AS updated_at
    UNION ALL SELECT 'Editar Horarios',            'schedules.edit',  'schedules',  NULL, NOW(), NOW()
    UNION ALL SELECT 'Ver Asistencia',             'attendance.view', 'attendance', NULL, NOW(), NOW()
    UNION ALL SELECT 'Marcar asistencia de otros', 'attendance.mark', 'attendance', NULL, NOW(), NOW()
    UNION ALL SELECT 'Corregir asistencia',        'attendance.edit', 'attendance', NULL, NOW(), NOW()
) AS nuevos
WHERE NOT EXISTS (SELECT 1 FROM `permissions` p WHERE p.`slug` = nuevos.slug);

-- Administración y encargados: todo
INSERT IGNORE INTO `role_permission` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id` FROM `roles` r CROSS JOIN `permissions` p
WHERE r.`slug` IN ('admin', 'manager', 'gerente_c') AND p.`module` IN ('schedules', 'attendance');

-- Recepción (cajero): ve horarios y asistencia, y marca por quienes no tienen usuario
INSERT IGNORE INTO `role_permission` (`role_id`, `permission_id`)
SELECT r.`id`, p.`id` FROM `roles` r CROSS JOIN `permissions` p
WHERE r.`slug` = 'cashier' AND p.`slug` IN ('schedules.view', 'attendance.view', 'attendance.mark');

-- Fin del script.
