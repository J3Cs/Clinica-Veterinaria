-- ===================================================================
-- SOLO DESARROLLO / PRUEBAS MANUALES — Datos mínimos para agendar citas (US-04)
--
-- Las historias que gestionan servicios, veterinarios y horarios (US-09, US-10,
-- US-11) aún no existen, así que no hay pantallas para crearlos. Este script
-- inserta datos de ejemplo para poder probar el agendamiento. Es idempotente.
--
-- Las cuentas de veterinario se crean con un hash inválido a propósito:
-- NO se puede iniciar sesión con ellas. Nunca ejecutar en producción.
-- ===================================================================

SET NAMES utf8mb4;
USE clinica_veterinaria;

-- Servicios
INSERT INTO servicios (nombre_servicio, descripcion, duracion_estimada_min, activo)
SELECT * FROM (
    SELECT 'Consulta general'  AS n, 'Revisión clínica general'        AS d, 30 AS m, 1 AS a UNION ALL
    SELECT 'Vacunación',               'Aplicación de vacunas',                20,        1      UNION ALL
    SELECT 'Desparasitación',          'Tratamiento antiparasitario',          15,        1      UNION ALL
    SELECT 'Cirugía menor',            'Procedimiento quirúrgico menor',       60,        1      UNION ALL
    SELECT 'Servicio descontinuado',   'Inactivo: no debe ofrecerse',          30,        0
) AS nuevos
WHERE NOT EXISTS (SELECT 1 FROM servicios);

-- Usuarios veterinarios (sin acceso: hash no válido)
INSERT IGNORE INTO usuarios (nombre, correo, usuario, contrasena, rol, activo) VALUES
    ('Dra. Laura Mendoza', 'laura.demo@clinica.local', 'dra.demo1', '!sin-acceso-seed', 'veterinario', 1),
    ('Dr. Carlos Rivas',   'carlos.demo@clinica.local', 'dr.demo2', '!sin-acceso-seed', 'veterinario', 1);

INSERT IGNORE INTO veterinarios (id_usuario, especialidad, telefono)
SELECT id_usuario, 'Medicina general', '2200-0001' FROM usuarios WHERE usuario = 'dra.demo1';
INSERT IGNORE INTO veterinarios (id_usuario, especialidad, telefono)
SELECT id_usuario, 'Cirugía', '2200-0002' FROM usuarios WHERE usuario = 'dr.demo2';

SET @vet1 := (SELECT v.id_veterinario FROM veterinarios v JOIN usuarios u ON u.id_usuario = v.id_usuario WHERE u.usuario = 'dra.demo1');
SET @vet2 := (SELECT v.id_veterinario FROM veterinarios v JOIN usuarios u ON u.id_usuario = v.id_usuario WHERE u.usuario = 'dr.demo2');

-- Dra. Mendoza: lunes a viernes 08:00–12:00 y 14:00–17:00
INSERT INTO horarios_atencion (id_veterinario, dia_semana, hora_inicio, hora_fin)
SELECT @vet1, d.n, b.i, b.f
  FROM (SELECT 1 AS n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5) d
  CROSS JOIN (SELECT '08:00:00' AS i, '12:00:00' AS f UNION SELECT '14:00:00', '17:00:00') b
 WHERE @vet1 IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM horarios_atencion WHERE id_veterinario = @vet1);

-- Dr. Rivas: lunes a sábado 09:00–13:00
INSERT INTO horarios_atencion (id_veterinario, dia_semana, hora_inicio, hora_fin)
SELECT @vet2, d.n, '09:00:00', '13:00:00'
  FROM (SELECT 1 AS n UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 UNION SELECT 5 UNION SELECT 6) d
 WHERE @vet2 IS NOT NULL
   AND NOT EXISTS (SELECT 1 FROM horarios_atencion WHERE id_veterinario = @vet2);
