-- ===================================================================
-- Migración 002: Agregar y asegurar columna DUI única en propietarios (T-087, UNI-01, BAJA-01)
-- ===================================================================

USE clinica_veterinaria;

-- 1. Agregar columna dui si no existe
SET @col_dui_exists := (
    SELECT COUNT(*) 
    FROM information_schema.columns 
    WHERE table_schema = DATABASE() 
      AND table_name = 'propietarios' 
      AND column_name = 'dui'
);

SET @sql_dui := IF(
    @col_dui_exists = 0,
    'ALTER TABLE `propietarios` ADD COLUMN `dui` CHAR(10) NOT NULL COMMENT "Formato #########-# (9 dígitos + guion + 1 dígito)" AFTER `id_propietario`',
    'ALTER TABLE `propietarios` MODIFY COLUMN `dui` CHAR(10) NOT NULL COMMENT "Formato #########-# (9 dígitos + guion + 1 dígito)"'
);
PREPARE stmt_dui FROM @sql_dui;
EXECUTE stmt_dui;
DEALLOCATE PREPARE stmt_dui;

-- 2. Asegurar índice UNIQUE sobre dui
SET @idx_dui_exists := (
    SELECT COUNT(*) 
    FROM information_schema.statistics 
    WHERE table_schema = DATABASE() 
      AND table_name = 'propietarios' 
      AND index_name = 'uq_propietarios_dui'
);

SET @sql_idx := IF(
    @idx_dui_exists = 0,
    'ALTER TABLE `propietarios` ADD CONSTRAINT `uq_propietarios_dui` UNIQUE (`dui`)',
    'SELECT "Índice uq_propietarios_dui ya existe" AS status'
);
PREPARE stmt_idx FROM @sql_idx;
EXECUTE stmt_idx;
DEALLOCATE PREPARE stmt_idx;

-- 3. Agregar columna activo si no existe (BAJA-01)
SET @col_activo_exists := (
    SELECT COUNT(*) 
    FROM information_schema.columns 
    WHERE table_schema = DATABASE() 
      AND table_name = 'propietarios' 
      AND column_name = 'activo'
);

SET @sql_activo := IF(
    @col_activo_exists = 0,
    'ALTER TABLE `propietarios` ADD COLUMN `activo` TINYINT(1) NOT NULL DEFAULT 1 AFTER `correo`',
    'SELECT "Columna activo ya existe" AS status'
);
PREPARE stmt_activo FROM @sql_activo;
EXECUTE stmt_activo;
DEALLOCATE PREPARE stmt_activo;
