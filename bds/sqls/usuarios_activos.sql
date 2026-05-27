CREATE TABLE IF NOT EXISTS usuarios_activos (
    id_activo INT AUTO_INCREMENT PRIMARY KEY,
    id_personal INT NOT NULL,
    session_id VARCHAR(128) NOT NULL,
    nombre_usuario VARCHAR(160) NOT NULL,
    id_rol INT NOT NULL,
    ruta_actual VARCHAR(255) DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    ultimo_ping DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_usuarios_activos_session (session_id),
    KEY idx_usuarios_activos_ultimo_ping (ultimo_ping),
    KEY idx_usuarios_activos_personal (id_personal)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
