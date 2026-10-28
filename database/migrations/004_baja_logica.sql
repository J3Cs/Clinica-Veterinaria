-- ===================================================================
-- Migración 004_baja_logica: Campos de baja lógica en propietarios y mascotas (T-090, US-12, BAJA-01)
-- Asegura de forma idempotente la existencia de la columna `activo` en
-- las tablas `propietarios` y `mascotas`, permitiendo desactivar registros
-- con relaciones clínicas sin perder integridad histórica.
-- ===================================================================

SET NAMES utf8mb4;
USE clinica_veterinaria;

-- 1. Asegurar columna `activo` en `propietarios`
SET @col_prop_activo := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'propietarios'
      AND column_name = 'activo'
);

SET @sql_prop_activo := IF(
    @col_prop_activo = 0,
    'ALTER TABLE `propietarios` ADD COLUMN `activo` TINYINT(1) NOT NULL DEFAULT 1 AFTER `correo`',
    'SELECT "Columna activo ya existe en propietarios" AS status'
);
PREPARE stmt_prop_activo FROM @sql_prop_activo;
EXECUTE stmt_prop_activo;
DEALLOCATE PREPARE stmt_prop_activo;

-- 2. Asegurar columna `activo` en `mascotas`
SET @col_masc_activo := (
    SELECT COUNT(*)
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = 'mascotas'
      AND column_name = 'activo'
);

SET @sql_masc_activo := IF(
    @col_masc_activo = 0,
    'ALTER TABLE `mascotas` ADD COLUMN `activo` TINYINT(1) NOT NULL DEFAULT 1 AFTER `caracteristicas_relevantes`',
    'SELECT "Columna activo ya existe en mascotas" AS status'
);
PREPARE stmt_masc_activo FROM @sql_masc_activo;
EXECUTE stmt_masc_activo;
DEALLOCATE PREPARE stmt_masc_activo;
