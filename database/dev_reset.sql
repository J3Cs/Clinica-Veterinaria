-- ===================================================================
-- SOLO DESARROLLO. DESTRUYE TODOS LOS DATOS.
-- Elimina las tablas para volver a ejecutar init.sql desde cero.
-- Nunca ejecutar en un entorno con datos reales.
-- ===================================================================
USE clinica_veterinaria;
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `historial_mascota`;
DROP TABLE IF EXISTS `consultas_veterinarias`;
DROP TABLE IF EXISTS `citas`;
DROP TABLE IF EXISTS `horarios_atencion`;
DROP TABLE IF EXISTS `servicios`;
DROP TABLE IF EXISTS `mascotas`;
DROP TABLE IF EXISTS `propietarios`;
DROP TABLE IF EXISTS `veterinarios`;
DROP TABLE IF EXISTS `usuarios`;
SET FOREIGN_KEY_CHECKS = 1;
