-- Nuevo rol 4: Invitado (vistas de asistencia, clases y cursos)
-- Ejecutar manualmente o desde config/ejecutar_migraciones_bds.php

INSERT INTO roles (id_rol, nombre_rol) VALUES (4, 'Invitado')
ON DUPLICATE KEY UPDATE nombre_rol = VALUES(nombre_rol);