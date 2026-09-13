-- Crear la base de datos si no existe
CREATE DATABASE IF NOT EXISTS clinica_veterinaria 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;

USE clinica_veterinaria;

-- Desactivar temporalmente restricciones para reestructuración limpia
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

-- 1. Tabla: usuarios
CREATE TABLE `usuarios` (
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
CREATE TABLE `veterinarios` (
  `id_veterinario` INT AUTO_INCREMENT PRIMARY KEY,
  `id_usuario` INT NOT NULL UNIQUE,
  `especialidad` VARCHAR(100) DEFAULT NULL,
  `telefono` VARCHAR(20) DEFAULT NULL,
  `licencia_medica` VARCHAR(50) DEFAULT NULL,
  CONSTRAINT `fk_veterinarios_usuarios` 
    FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id_usuario`) 
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tabla: propietarios
CREATE TABLE `propietarios` (
  `id_propietario` INT AUTO_INCREMENT PRIMARY KEY,
  `nombre_completo` VARCHAR(150) NOT NULL,
  `direccion` TEXT DEFAULT NULL,
  `telefono` VARCHAR(20) DEFAULT NULL,
  `correo` VARCHAR(150) DEFAULT NULL,
  `fecha_registro` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tabla: mascotas
CREATE TABLE `mascotas` (
  `id_mascota` INT AUTO_INCREMENT PRIMARY KEY,
  `id_propietario` INT NOT NULL,
  `nombre` VARCHAR(100) NOT NULL,
  `especie` VARCHAR(50) NOT NULL,
  `raza` VARCHAR(50) DEFAULT NULL,
  `sexo` ENUM('Macho', 'Hembra') NOT NULL,
  `fecha_nacimiento` DATE DEFAULT NULL,
  `caracteristicas_relevantes` TEXT DEFAULT NULL,
  `fecha_registro` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_mascotas_propietarios` 
    FOREIGN KEY (`id_propietario`) REFERENCES `propietarios` (`id_propietario`) 
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Tabla: servicios
CREATE TABLE `servicios` (
  `id_servicio` INT AUTO_INCREMENT PRIMARY KEY,
  `nombre_servicio` VARCHAR(100) NOT NULL,
  `descripcion` TEXT DEFAULT NULL,
  `duracion_estimada_min` INT NOT NULL DEFAULT 30,
  `activo` TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Tabla: horarios_atencion
CREATE TABLE `horarios_atencion` (
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
CREATE TABLE `citas` (
  `id_cita` INT AUTO_INCREMENT PRIMARY KEY,
  `id_mascota` INT NOT NULL,
  `id_veterinario` INT NOT NULL,
  `id_servicio` INT NOT NULL,
  `fecha_cita` DATE NOT NULL,
  `hora_cita` TIME NOT NULL,
  `estado` ENUM('Programada', 'Atendida', 'Cancelada') NOT NULL DEFAULT 'Programada',
  `motivo_cancelacion` TEXT DEFAULT NULL,
  `fecha_creacion` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_citas_mascotas` 
    FOREIGN KEY (`id_mascota`) REFERENCES `mascotas` (`id_mascota`) 
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_citas_veterinarios` 
    FOREIGN KEY (`id_veterinario`) REFERENCES `veterinarios` (`id_veterinario`) 
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_citas_servicios` 
    FOREIGN KEY (`id_servicio`) REFERENCES `servicios` (`id_servicio`) 
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Tabla: consultas_veterinarias
CREATE TABLE `consultas_veterinarias` (
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
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_consultas_mascotas` 
    FOREIGN KEY (`id_mascota`) REFERENCES `mascotas` (`id_mascota`) 
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_consultas_veterinarios` 
    FOREIGN KEY (`id_veterinario`) REFERENCES `veterinarios` (`id_veterinario`) 
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Tabla: historial_mascota
CREATE TABLE `historial_mascota` (
  `id_historial_mascota` INT AUTO_INCREMENT PRIMARY KEY,
  `id_mascota` INT NOT NULL,
  `consulta_previa` DATE DEFAULT NULL,
  `vacunas_colocadas` VARCHAR(100) DEFAULT NULL,
  `fecha_vacunas` DATE DEFAULT NULL,
  `nombre_medicamento` VARCHAR(100) DEFAULT NULL,
  CONSTRAINT `fk_historial_mascotas` 
    FOREIGN KEY (`id_mascota`) REFERENCES `mascotas` (`id_mascota`) 
    ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insertar usuario administrador por defecto (password: Admin123!)
INSERT INTO `usuarios` (`nombre`, `correo`, `usuario`, `contrasena`, `rol`, `activo`) 
VALUES ('Administrador General', 'admin@clinica.com', 'admin_master', '$2y$12$eImiTXuWVxfM37uY4JANjO...eW17p1yU/JvF9lW8.', 'administrador', 1);