-- ===================================================================
-- Migración 003: Asegurar columna activo en tabla mascotas (BAJA-01)
-- ===================================================================

SET NAMES utf8mb4;
USE clinica_veterinaria;

SET @col_activo_exists := (
    SELECT COUNT(*) 
    FROM information_schema.columns 
    WHERE table_schema = DATABASE() 
      AND table_name = 'mascotas' 
      AND column_name = 'activo'
);

SET @sql_activo := IF(
    @col_activo_exists = 0,
    'ALTER TABLE `mascotas` ADD COLUMN `activo` TINYINT(1) NOT NULL DEFAULT 1 AFTER `caracteristicas_relevantes`',
    'SELECT "Columna activo ya existe en mascotas" AS status'
);
PREPARE stmt_activo FROM @sql_activo;
EXECUTE stmt_activo;
DEALLOCATE PREPARE stmt_activo;
