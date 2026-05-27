CREATE TABLE IF NOT EXISTS usuarios_ingresos (
    id_ingreso INT AUTO_INCREMENT PRIMARY KEY,
    id_personal INT NOT NULL,
    nombre_usuario VARCHAR(160) NOT NULL,
    id_rol INT NOT NULL,
    session_id VARCHAR(128) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    fecha_ingreso DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_usuarios_ingresos_personal (id_personal),
    KEY idx_usuarios_ingresos_fecha (fecha_ingreso)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
