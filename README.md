# 🏛️ Sistema de Gestión Documental Municipal

Plataforma web desarrollada en PHP orientada a la digitalización, almacenamiento y generación automática de hipervínculos para documentos de carácter público y administrativo de un municipio.

## Características Principales

* **Carga Masiva y Drag & Drop:** Subida de múltiples archivos (PDF, DOCX, XLSX, ZIP) de forma ágil e intuitiva.
* **Sanitización Inteligente:** Normalización automática de nombres de archivos y carpetas (eliminación de espacios, tildes y caracteres especiales) para prevenir enlaces rotos (Error 404).
* **RBAC (Control de Acceso Basado en Roles):** * *Administrador General:* Acceso total al repositorio, creación de usuarios y gestión del catálogo de áreas.
  * *Usuarios de Área:* Aislamiento estricto; solo pueden visualizar, subir y gestionar documentos de su propio departamento.
* **Gestión de Catálogos Segura:** Prevención de colisión de directorios físicos y esquema de "Baja Lógica" (Inactivación) para áreas, garantizando la persistencia histórica de los archivos.
* **Seguridad Criptográfica:** Contraseñas protegidas mediante hashes `BCrypt` nativos de PHP.
* **Exportación a Excel:** Generación automática de reportes `.xls` infalibles con datos de trazabilidad (ID, Nombre, Ruta, Autor, Fecha/Hora y Enlace directo).

##  Requisitos del Sistema

* Servidor Web (Apache/Nginx).
* PHP 7.4 o superior (con extensión PDO habilitada).
* MySQL 5.7+ o MariaDB.

##  Instalación y Despliegue (Estado Cero)

1. Clonar el repositorio en el directorio del servidor web (ej. `htdocs` o `www`).
2. La carpeta `repositorio_oficial/` debe tener permisos de escritura (CHMOD 0755).
3. Importar el archivo `bd_gestor_documental.sql` en el gestor de base de datos (phpMyAdmin). Este script creará la estructura relacional y un usuario maestro.
4. (Opcional) Ajustar las credenciales de base de datos en los archivos `.php` si el entorno local tiene un usuario distinto a `root` sin contraseña.
4. Configurar el archivo central **`conexion.php`** si las credenciales de base de datos o el entorno difieren de los valores por defecto locales (`root` sin contraseña):

```php
<?php
// Configuración de zona horaria oficial (Centro de México)
date_default_timezone_set('America/Mexico_City');

\$servername = "localhost:3306";
\$username   = "root"; // Usuario de la base de datos
\$password   = "";     // Contraseña de la base de datos
\$dbname     = "bd_gestor_documental";

try {
    \$conn = new PDO("mysql:host=\(servername;dbname=\)dbname;charset=utf8mb4", \(username,\)password);
    \(conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);\)conn->exec("SET time_zone = '-06:00'");
} catch(PDOException \$e) {
    die("Error de conexión: " . \$e->getMessage());
}
?>
```


##  Credenciales de Acceso por Defecto
El sistema se entrega en "Estado Cero" (sin áreas configuradas). Para iniciar la parametrización, utilice el usuario semilla:

* **Usuario:** `admin`
* **Contraseña:** `admin123` *(esta se puede modificar dentro del sistema en el apartado de gestion de usuarios )*

##  Créditos
Desarrollado por el equipo de **INFRUSCH Consultores** (Kevin Giussepe & Cristopher Vargas) - 2026.