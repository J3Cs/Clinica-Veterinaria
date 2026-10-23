-- ===================================================================
-- Migración 004: Cambiar ON DELETE CASCADE -> RESTRICT en tablas clínicas (BAJA-01)
-- Evita que un DELETE físico destruya mascotas, citas, consultas e historial.
-- Mantiene CASCADE solo en horarios_atencion (datos de configuración).
--
-- Cada clave se elimina y se vuelve a crear en sentencias SEPARADAS: MySQL/MariaDB
-- fallan (error 121) si se hace en un único ALTER con el mismo nombre.
-- Ejecutar UNA sola vez sobre bases creadas con el init.sql anterior.
-- ===================================================================

USE clinica_veterinaria;

ALTER TABLE `veterinarios` DROP FOREIGN KEY `fk_veterinarios_usuarios`;
ALTER TABLE `veterinarios` ADD CONSTRAINT `fk_veterinarios_usuarios` FOREIGN KEY (`id_usuario`)
  REFERENCES `usuarios` (`id_usuario`) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `mascotas` DROP FOREIGN KEY `fk_mascotas_propietarios`;
ALTER TABLE `mascotas` ADD CONSTRAINT `fk_mascotas_propietarios` FOREIGN KEY (`id_propietario`)
  REFERENCES `propietarios` (`id_propietario`) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `citas` DROP FOREIGN KEY `fk_citas_mascotas`;
ALTER TABLE `citas` ADD CONSTRAINT `fk_citas_mascotas` FOREIGN KEY (`id_mascota`)
  REFERENCES `mascotas` (`id_mascota`) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `citas` DROP FOREIGN KEY `fk_citas_veterinarios`;
ALTER TABLE `citas` ADD CONSTRAINT `fk_citas_veterinarios` FOREIGN KEY (`id_veterinario`)
  REFERENCES `veterinarios` (`id_veterinario`) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `consultas_veterinarias` DROP FOREIGN KEY `fk_consultas_citas`;
ALTER TABLE `consultas_veterinarias` ADD CONSTRAINT `fk_consultas_citas` FOREIGN KEY (`id_cita`)
  REFERENCES `citas` (`id_cita`) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `consultas_veterinarias` DROP FOREIGN KEY `fk_consultas_mascotas`;
ALTER TABLE `consultas_veterinarias` ADD CONSTRAINT `fk_consultas_mascotas` FOREIGN KEY (`id_mascota`)
  REFERENCES `mascotas` (`id_mascota`) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `consultas_veterinarias` DROP FOREIGN KEY `fk_consultas_veterinarios`;
ALTER TABLE `consultas_veterinarias` ADD CONSTRAINT `fk_consultas_veterinarios` FOREIGN KEY (`id_veterinario`)
  REFERENCES `veterinarios` (`id_veterinario`) ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE `historial_mascota` DROP FOREIGN KEY `fk_historial_mascotas`;
ALTER TABLE `historial_mascota` ADD CONSTRAINT `fk_historial_mascotas` FOREIGN KEY (`id_mascota`)
  REFERENCES `mascotas` (`id_mascota`) ON DELETE RESTRICT ON UPDATE CASCADE;
