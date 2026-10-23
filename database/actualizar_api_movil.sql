-- =====================================================================
--  Glamora · API para la app móvil (tokens de Laravel Sanctum)
--
--  SEGURO DE REEJECUTAR: la tabla y el registro de la migración se crean
--  solo si no existen, así que ejecutarlo de nuevo no duplica ni rompe
--  nada. Probado dos veces sobre una base de prueba.
--  Compatible con MySQL 5.7+ y MariaDB 10.2+ (sin DELIMITER).
--
--  Además de este script, el servidor necesita el paquete laravel/sanctum
--  (composer install con el composer.json/composer.lock actualizados).
--  Ejecutar completo: phpMyAdmin → pestaña SQL → pegar y ejecutar.
-- =====================================================================

-- Tokens de acceso de la app (uno por dispositivo; se revocan al cerrar sesión)
CREATE TABLE IF NOT EXISTS `personal_access_tokens` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tokenable_type` VARCHAR(255)    NOT NULL,
    `tokenable_id`   BIGINT UNSIGNED NOT NULL,
    `name`           TEXT            NOT NULL COMMENT 'dispositivo, ej. Galaxy A54 de Ana',
    `token`          VARCHAR(64)     NOT NULL COMMENT 'hash SHA-256 del token, nunca el token en claro',
    `abilities`      TEXT            NULL,
    `last_used_at`   TIMESTAMP       NULL,
    `expires_at`     TIMESTAMP       NULL,
    `created_at`     TIMESTAMP       NULL,
    `updated_at`     TIMESTAMP       NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
    KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`, `tokenable_id`),
    KEY `personal_access_tokens_expires_at_index` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Registro de la migración, para que un futuro `php artisan migrate` no la repita
INSERT INTO `migrations` (`migration`, `batch`)
SELECT n.`migration`, n.`batch` FROM (SELECT '2026_10_22_171449_create_personal_access_tokens_table' AS `migration`, COALESCE(MAX(`batch`), 0) + 1 AS `batch` FROM `migrations`) n
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `migration` = '2026_10_22_171449_create_personal_access_tokens_table');

-- Fin del script.
