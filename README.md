# 🏛️ Sistema de Gestión Documental Municipal

Plataforma web desarrollada en PHP orientada a la digitalización, almacenamiento, trazabilidad y generación automática de hipervínculos para documentos de carácter público y administrativo de un municipio.

---

## 🚀 Características Principales

* **Carga Masiva y Drag & Drop:** Subida ágil de múltiples archivos (.pdf, .docx, .xlsx, .zip) con soporte de descompresión automática en servidor.
* **Sanitización Inteligente de Archivos:** Normalización estricta de nombres y rutas (eliminación de espacios, tildes y caracteres especiales) mediante expresiones regulares para garantizar URLs limpias y prevenir enlaces rotos (Error 404).
* **Ciclo de Años Dinámico y Autosustentable:** Renderizado automático del selector de años basado en el reloj del servidor, asegurando disponibilidad para periodos vigentes y futuros sin mantenimiento manual de código.
* **RBAC (Control de Acceso Basado en Roles):**
  * *Administrador General:* Control global del repositorio, gestión del catálogo de dependencias/áreas y administración de credenciales de usuario.
  * *Usuarios de Área:* Aislamiento departmental estricto; cada cuenta visualiza y gestiona únicamente los documentos de su área asignada.
* **Gestión Segura del Catálogo de Áreas:** * *Inmutabilidad de Carpetas Físicas:* La edición de un área solo altera su nombre visible para proteger la persistencia de enlaces web previamente publicados.
  * *Baja Lógica (Soft Delete):* Desactivación de áreas para ocultarlas de los formularios de carga sin comprometer el historial documental ni la integridad relacional.
  * *Prevención de Colisiones:* Generación de sufijos incrementales en caso de reutilización de nombres en el sistema de archivos.
* **Seguridad Criptográfica:** Almacenamiento seguro de contraseñas mediante hashing nativo `BCrypt` (`password_hash` / `password_verify`).
* **Exportación a Excel Infalible:** Generación de reportes tabulares `.xls` con decodificación de rutas URI, fecha/hora exacta de carga (`d/m/Y H:i`), autor y enlace directo.
* **Arquitectura Modular (DRY):** Conexión centralizada a base de datos mediante PDO.

---

## 🛠️ Requisitos del Sistema

* **Servidor Web:** Apache 2.4+ / Nginx.
* **Lenguaje:** PHP 7.4 o superior (con extensiones `PDO_MySQL` y `ZipArchive` habilitadas).
* **Gestor de Base de Datos:** MySQL 5.7+ / MariaDB 10.3+.

---

## 📂 Estructura del Proyecto

```text
├── .htaccess                     # Reglas de seguridad raíz (si aplica)
├── conexion.php                  # Configuración y enlace central a BD (PDO)
├── index.php                     # Panel principal de gestión documental y exportación
├── usuarios.php                  # Módulo de administración de usuarios y roles
├── areas.php                     # Módulo de control de catálogo y estado de áreas
├── style.css                     # Hoja de estilos del sistema
├── bd_gestor_documental.sql      # Script SQL de despliegue inicial
└── repositorio_oficial/          # Directorio físico de almacenamiento
    └── .htaccess                 # Bloqueo de ejecución de scripts (.php, .exe)