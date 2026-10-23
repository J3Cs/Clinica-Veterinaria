-- ===================================================================
-- Migración 005: Índice para consultar la agenda de un veterinario (US-04, T-031)
-- Acelera: citas por veterinario + fecha + estado (cálculo de disponibilidad).
-- Idempotente.
-- ===================================================================

USE clinica_veterinaria;

SET @idx_existe := (
    SELECT COUNT(*)
      FROM information_schema.statistics
     WHERE table_schema = DATABASE()
       AND table_name = 'citas'
       AND index_name = 'idx_citas_vet_fecha_estado'
);

SET @sql_idx := IF(
    @idx_existe = 0,
    'ALTER TABLE `citas` ADD INDEX `idx_citas_vet_fecha_estado` (`id_veterinario`, `fecha_cita`, `estado`)',
    'SELECT "Índice idx_citas_vet_fecha_estado ya existe" AS status'
);
PREPARE stmt_idx FROM @sql_idx;
EXECUTE stmt_idx;
DEALLOCATE PREPARE stmt_idx;
