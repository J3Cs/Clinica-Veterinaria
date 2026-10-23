-- Crear la base de datos si no existe
CREATE DATABASE IF NOT EXISTS clinica_veterinaria 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE clinica_veterinaria;

-- NOTA: este script es idempotente y NO borra datos (CREATE TABLE IF NOT EXISTS).
-- Para reiniciar una BD de desarrollo desde cero use database/dev_reset.sql.

-- 1. Tabla: usuarios
CREATE TABLE IF NOT EXISTS `usuarios` (
  `id_usuario` INT AUTO_INCREMENT PRIMARY KEY,
  `nombre` VARCHAR(100) NOT NULL,
  `correo` VARCHAR(150) NOT NULL UNIQUE,
  `usuario` VARCHAR(50) NOT NULL UNIQUE,
  `contrasena` VARCHAR(255) NOT NULL,
  `rol` ENUM('administrador', 'veterinario') NOT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabla: veterinarios
CREATE TABLE IF NOT EXISTS `veterinarios` (
  `id_veterinario` INT AUTO_INCREMENT PRIMARY KEY,
  `id_usuario` INT NOT NULL UNIQUE,
  `especialidad` VARCHAR(100) DEFAULT NULL,
  `telefono` VARCHAR(20) DEFAULT NULL,
  `licencia_medica` VARCHAR(50) DEFAULT NULL,
  CONSTRAINT `fk_veterinarios_usuarios` 
    FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) 
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tabla: propietarios
CREATE TABLE IF NOT EXISTS `propietarios` (
  `id_propietario` INT AUTO_INCREMENT PRIMARY KEY,
  `dui`            CHAR(10) NOT NULL COMMENT 'Formato #########-# (9 dígitos + guion + 1 dígito)',
  `nombre_completo` VARCHAR(150) NOT NULL,
  `direccion` TEXT DEFAULT NULL,
  `telefono` VARCHAR(20) DEFAULT NULL,
  `correo` VARCHAR(150) DEFAULT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `fecha_registro` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `uq_propietarios_dui` UNIQUE (`dui`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tabla: mascotas
CREATE TABLE IF NOT EXISTS `mascotas` (
  `id_mascota` INT AUTO_INCREMENT PRIMARY KEY,
  `id_propietario` INT NOT NULL,
  `nombre` VARCHAR(100) NOT NULL,
  `especie` VARCHAR(50) NOT NULL,
  `raza` VARCHAR(50) DEFAULT NULL,
  `sexo` ENUM('Macho', 'Hembra') NOT NULL,
  `fecha_nacimiento` DATE DEFAULT NULL,
  `caracteristicas_relevantes` TEXT DEFAULT NULL,
  `activo` TINYINT(1) NOT NULL DEFAULT 1,
  `fecha_registro` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_mascotas_propietarios` 
    FOREIGN KEY (`id_propietario`) REFERENCES `propietarios` (`id_propietario`) 
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Tabla: servicios
CREATE TABLE IF NOT EXISTS `servicios` (
  `id_servicio` INT AUTO_INCREMENT PRIMARY KEY,
  `nombre_servicio` VARCHAR(100) NOT NULL,
  `descripcion` TEXT DEFAULT NULL,
  `duracion_estimada_min` INT NOT NULL DEFAULT 30,
  `activo` TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Tabla: horarios_atencion
CREATE TABLE IF NOT EXISTS `horarios_atencion` (
  `id_horario` INT AUTO_INCREMENT PRIMARY KEY,
  `id_veterinario` INT NOT NULL,
  `dia_semana` INT NOT NULL COMMENT '1=Lunes, 7=Domingo',
  `hora_inicio` TIME NOT NULL,
  `hora_fin` TIME NOT NULL,
  CONSTRAINT `fk_horarios_veterinarios` 
    FOREIGN KEY (`id_veterinario`) REFERENCES `veterinarios` (`id_veterinario`) 
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Tabla: citas
CREATE TABLE IF NOT EXISTS `citas` (
  `id_cita` INT AUTO_INCREMENT PRIMARY KEY,
  `id_mascota` INT NOT NULL,
  `id_veterinario` INT NOT NULL,
  `id_servicio` INT NOT NULL,
  `fecha_cita` DATE NOT NULL,
  `hora_cita` TIME NOT NULL,
  `estado` ENUM('Programada', 'Atendida', 'Cancelada') NOT NULL DEFAULT 'Programada',
  `motivo_cancelacion` TEXT DEFAULT NULL,
  `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX `idx_citas_vet_fecha_estado` (`id_veterinario`, `fecha_cita`, `estado`),
  CONSTRAINT `fk_citas_mascotas` 
    FOREIGN KEY (`id_mascota`) REFERENCES `mascotas` (`id_mascota`) 
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_citas_veterinarios` 
    FOREIGN KEY (`id_veterinario`) REFERENCES `veterinarios` (`id_veterinario`) 
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_citas_servicios` 
    FOREIGN KEY (`id_servicio`) REFERENCES `servicios` (`id_servicio`) 
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Tabla: consultas_veterinarias
CREATE TABLE IF NOT EXISTS `consultas_veterinarias` (
  `id_consulta` INT AUTO_INCREMENT PRIMARY KEY,
  `id_cita` INT NOT NULL UNIQUE,
  `id_mascota` INT NOT NULL,
  `id_veterinario` INT NOT NULL,
  `fecha_atencion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `diagnostico` TEXT NOT NULL,
  `observaciones` TEXT DEFAULT NULL,
  `tratamiento` TEXT DEFAULT NULL,
  `servicios_adicionales` TEXT DEFAULT NULL,
  CONSTRAINT `fk_consultas_citas` 
    FOREIGN KEY (`id_cita`) REFERENCES `citas` (`id_cita`) 
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_consultas_mascotas` 
    FOREIGN KEY (`id_mascota`) REFERENCES `mascotas` (`id_mascota`) 
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT `fk_consultas_veterinarios` 
    FOREIGN KEY (`id_veterinario`) REFERENCES `veterinarios` (`id_veterinario`) 
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Tabla: historial_mascota (Antecedentes y problemas crónicos previos)
CREATE TABLE IF NOT EXISTS `historial_mascota` (
  `id_historial_mascota` INT AUTO_INCREMENT PRIMARY KEY,
  `id_mascota` INT NOT NULL,
  `condicion_medica` VARCHAR(150) NOT NULL COMMENT 'Ej: Insuficiencia renal, epilepsia, etc.',
  `descripcion_problema` TEXT DEFAULT NULL COMMENT 'Detalles del problema crónico previo',
  `alergias` TEXT DEFAULT NULL COMMENT 'Alergias conocidas antes de venir a la clínica',
  `fecha_registro_antecedente` DATE DEFAULT NULL COMMENT 'Fecha aproximada de diagnóstico previo',
  CONSTRAINT `fk_historial_mascotas` 
    FOREIGN KEY (`id_mascota`) REFERENCES `mascotas` (`id_mascota`) 
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Fin del esquema base. El administrador inicial se crea mediante:
--   php init_admin.php
-- (script CLI ubicado en la raíz del proyecto, ver ADM-01)
