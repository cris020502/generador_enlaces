-- Creación de la base de datos
CREATE DATABASE IF NOT EXISTS bd_gestor_documental 
DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE bd_gestor_documental;

-- 1. Tabla: areas (Catálogo de Departamentos)
-- NOTA: Se crea primero para que 'usuarios' y 'categorias' puedan enlazar sus llaves foráneas.
CREATE TABLE IF NOT EXISTS areas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  directorio VARCHAR(100) NOT NULL UNIQUE,
  estatus TINYINT(1) DEFAULT 1 
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Tabla: usuarios (Control de Acceso - RBAC)
CREATE TABLE IF NOT EXISTS usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  rol VARCHAR(50) NOT NULL,
  id_area INT DEFAULT NULL,
  CONSTRAINT fk_usuarios_areas FOREIGN KEY (id_area) REFERENCES areas(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Tabla: categorias (Clasificación Periodos / Transversales)
CREATE TABLE IF NOT EXISTS categorias (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  directorio VARCHAR(100) NOT NULL,
  id_area INT DEFAULT NULL,
  CONSTRAINT fk_categorias_areas FOREIGN KEY (id_area) 
    REFERENCES areas(id) ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Tabla: bitacora (Auditoría Inmutable)
CREATE TABLE IF NOT EXISTS bitacora (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario VARCHAR(50) DEFAULT NULL,
  archivo VARCHAR(255) DEFAULT NULL,
  link VARCHAR(255) DEFAULT NULL,
  accion VARCHAR(90) DEFAULT NULL,
  fecha TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Inserción del Usuario Maestro (Administrador)
-- Usuario: admin | Contraseña: admin123
INSERT INTO usuarios (username, password, rol) 
VALUES ('admin', '$2y$10$eE0m/lD070h9Lp4pZp.l1eZ7q9Q9/12Q5zX08/o.7wJ.y.2.8.N.C', 'admin');