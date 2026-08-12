<?php
session_start();
$servername = "localhost:3306";
$username = "root";
$password = ""; // Tu contraseña local
$dbname = "bd_gestor_documental";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// --- 1. SISTEMA DE USUARIOS Y ROLES CON BD ---
if (isset($_POST['btn_login'])) {
    $user_ingresado = strtolower(trim($_POST['usuario']));
    $pass_ingresado = trim($_POST['password']);

    try {
        $stmt = $conn->prepare("SELECT rol, password FROM usuarios WHERE username = ? LIMIT 1");
        $stmt->execute([$user_ingresado]);
        $usuario_db = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($usuario_db && $usuario_db['password'] === $pass_ingresado) {
            $_SESSION['usuario_logeado'] = $user_ingresado;
            $_SESSION['es_admin'] = ($usuario_db['rol'] === 'admin');
        } else {
            $error_login = "Usuario o contraseña incorrectos.";
        }
    } catch (PDOException $e) {
        $error_login = "Error al conectar con el sistema de usuarios.";
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: index.php");
    exit;
}

if (!isset($_SESSION['usuario_logeado'])) {
?>
    <!DOCTYPE html>
    <html lang="es">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login - Gestor Documental</title>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="style.css?v=3">
    </head>
    <body class="bg-login">
        <div class="login-container">
<img src="logos/logo_infrusch.jpeg" alt="Logo INFRUSCH Consultores" class="login-logo" onerror="this.style.display='none'" style="max-width: 220px;">            
            <h2>Acceso Restringido</h2>
            <p>Ingrese sus credenciales para acceder a la plataforma documental</p>
            
            <?php if(isset($error_login)) echo "<div class='error-msg'>⚠️ $error_login</div>"; ?>
            
            <form method="POST">
                <input type="text" name="usuario" class="login-input" placeholder="Usuario de acceso" autocomplete="off" required>
                <input type="password" name="password" class="login-input" placeholder="Contraseña" required>
                <button type="submit" name="btn_login" class="btn-login">Ingresar al Sistema</button>
            </form>
        </div>
        <div class="footer-creditos">
            Realizado por: 
            <a href="https://github.com/kevingiu7" target="_blank">Kevin Giussepe</a> & 
            <a href="https://github.com/cris020502" target="_blank">Cristopher Vargas</a>
        </div>
        <div class="footer-derechos">
            &copy; 2026 INFRUSCH Consultores. Todos los derechos reservados.
        </div>
    </body>
    </html>
<?php
    exit;
}

$es_admin = $_SESSION['es_admin'];
$usuario_actual = $_SESSION['usuario_logeado'];

// --- 2. CONFIGURACIÓN, SEGURIDAD Y ENRUTAMIENTO BASE ---
$mensajes_sistema = "";
if (isset($_SESSION['mensaje_temp'])) {
    $mensajes_sistema = $_SESSION['mensaje_temp'];
    unset($_SESSION['mensaje_temp']);
}

$carpeta_base = 'repositorio_oficial/'; 
$limite_peso_mb = 10; 
$limite_bytes = $limite_peso_mb * 1024 * 1024; 

if (!file_exists($carpeta_base . '.htaccess')) {
    if (!is_dir($carpeta_base)) mkdir($carpeta_base, 0755, true);
    $reglas_seguridad = '<FilesMatch "\.(php|php[3-7]|phtml|pl|py|jsp|asp|sh|cgi|exe)$">' . "\nRequire all denied\n</FilesMatch>\nOptions -Indexes";
    file_put_contents($carpeta_base . '.htaccess', $reglas_seguridad);
}

function normalizarNombreExacto($nombre) {
    $nombre = str_replace(' ', '_', $nombre);
    $busqueda = ['á','é','í','ó','ú','Á','É','Í','Ó','Ú','ñ','Ñ','ü','Ü'];
    $reemplazo = ['a','e','i','o','u','a','e','i','o','u','n','n','u','u'];
    $nombre = str_replace($busqueda, $reemplazo, $nombre);
    $nombre = preg_replace('/[^a-zA-Z0-9_-]/', '', $nombre); 
    return strtolower($nombre);
}

$extensiones_permitidas = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'zip', 'rar'];

$areas_existentes = [];
$periodos_comunes = [];
try {
    $stmt_areas = $conn->query("SELECT nombre, directorio FROM areas ORDER BY nombre ASC");
    while ($row = $stmt_areas->fetch(PDO::FETCH_ASSOC)) {
        $areas_existentes[$row['directorio']] = $row['nombre'];
    }
    
    $stmt_cat = $conn->query("SELECT nombre, directorio FROM categorias ORDER BY id ASC");
    while ($row = $stmt_cat->fetch(PDO::FETCH_ASSOC)) {
        $periodos_comunes[$row['directorio']] = $row['nombre'];
    }
} catch (PDOException $e) {}

// --- 3. LÓGICA DE SUBIDA Y CREACIÓN DINÁMICA DE RUTAS ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($_POST) && empty($_FILES) && $_SERVER['CONTENT_LENGTH'] > 0) {
    $_SESSION['mensaje_temp'] = "<div class='alert alert-error'><b>Error Crítico:</b> Archivo demasiado pesado. El servidor abortó la conexión.</div>";
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

if (isset($_POST['btn_cargar_unificado']) && isset($_FILES['archivos'])) {
    
    $area_seleccionada = '';
    if ($es_admin) {
        if (isset($_POST['area_destino']) && $_POST['area_destino'] === 'nueva') {
            $nombre_visible_area = trim($_POST['nueva_area']);
            $area_seleccionada = normalizarNombreExacto($nombre_visible_area);
            try {
                $stmt = $conn->prepare("INSERT IGNORE INTO areas (nombre, directorio) VALUES (?, ?)");
                $stmt->execute([$nombre_visible_area, $area_seleccionada]);
                $areas_existentes[$area_seleccionada] = $nombre_visible_area;
            } catch(PDOException $e) {}
        } else {
            $area_seleccionada = $_POST['area_destino'] ?? '';
        }
    } else {
        $area_seleccionada = normalizarNombreExacto($usuario_actual);
    }

    $anio_seleccionado = $_POST['anio_destino'] ?? '';
    
    $categoria_seleccionada = '';
    if (isset($_POST['categoria_destino']) && $_POST['categoria_destino'] === 'nueva') {
        $nombre_visible_cat = trim($_POST['nueva_categoria']);
        $categoria_seleccionada = normalizarNombreExacto($nombre_visible_cat);
        try {
            $stmt = $conn->prepare("INSERT IGNORE INTO categorias (nombre, directorio) VALUES (?, ?)");
            $stmt->execute([$nombre_visible_cat, $categoria_seleccionada]);
            $periodos_comunes[$categoria_seleccionada] = $nombre_visible_cat;
        } catch(PDOException $e) {}
    } else {
        $categoria_seleccionada = $_POST['categoria_destino'] ?? '';
    }
    
    if ($area_seleccionada !== '' && $anio_seleccionado !== '' && $categoria_seleccionada !== '') {
        $carpeta_destino = $carpeta_base . $area_seleccionada . '/' . $anio_seleccionado . '/' . $categoria_seleccionada . '/';
        
        if (!is_dir($carpeta_destino)) {
            mkdir($carpeta_destino, 0755, true);
        }

        $archivos_procesados = 0;
        $errores_acumulados = "";
        $total_archivos = count($_FILES['archivos']['name']);

        for ($i = 0; $i < $total_archivos; $i++) {
            $error_subida = $_FILES['archivos']['error'][$i];
            $nombre_err = $_FILES['archivos']['name'][$i];
            
            if ($error_subida === UPLOAD_ERR_INI_SIZE || $error_subida === UPLOAD_ERR_FORM_SIZE) {
                $errores_acumulados .= "<div class='alert alert-error'><b>Aviso:</b> El archivo <i>$nombre_err</i> es demasiado pesado y fue bloqueado por el servidor.</div>";
                continue;
            }

            if ($error_subida === UPLOAD_ERR_OK) {
                $nombre_original = pathinfo($_FILES['archivos']['name'][$i], PATHINFO_FILENAME);
                $ext = strtolower(pathinfo($_FILES['archivos']['name'][$i], PATHINFO_EXTENSION));
                
                if (!in_array($ext, $extensiones_permitidas)) {
                    $errores_acumulados .= "<div class='alert alert-error'><b>Seguridad:</b> Extensión no permitida en <i>$nombre_err</i>.</div>";
                    continue;
                }
                
                if ($_FILES['archivos']['size'][$i] > $limite_bytes) {
                    $errores_acumulados .= "<div class='alert alert-error'><b>Aviso:</b> El archivo <i>$nombre_err</i> supera el límite de {$limite_peso_mb}MB.</div>";
                    continue;
                }

                $nombre_nuevo = normalizarNombreExacto($nombre_original) . '.' . $ext;
                $ruta_final = $carpeta_destino . $nombre_nuevo;

                $contador = 1;
                while (file_exists($ruta_final)) {
                    $nombre_nuevo = normalizarNombreExacto($nombre_original) . "($contador)." . $ext;
                    $ruta_final = $carpeta_destino . $nombre_nuevo;
                    $contador++;
                }

                if (move_uploaded_file($_FILES['archivos']['tmp_name'][$i], $ruta_final)) {
                    if ($ext === 'zip' && class_exists('ZipArchive')) {
                        $zip = new ZipArchive;
                        if ($zip->open($ruta_final) === TRUE) {
                            for ($z = 0; $z < $zip->numFiles; $z++) {
                                $nombre_interno = $zip->getNameIndex($z);
                                if (substr($nombre_interno, -1) === '/' || strpos($nombre_interno, '__MACOSX') !== false) continue;

                                $ext_interna = strtolower(pathinfo($nombre_interno, PATHINFO_EXTENSION));
                                if (!in_array($ext_interna, $extensiones_permitidas)) continue;

                                $nombre_interno_saneado = normalizarNombreExacto(pathinfo($nombre_interno, PATHINFO_FILENAME)) . '.' . $ext_interna;
                                $ruta_interna_final = $carpeta_destino . $nombre_interno_saneado;

                                $contador_zip = 1;
                                while (file_exists($ruta_interna_final)) {
                                    $ruta_interna_final = $carpeta_destino . normalizarNombreExacto(pathinfo($nombre_interno, PATHINFO_FILENAME)) . "($contador_zip)." . $ext_interna;
                                    $contador_zip++;
                                }

                                if (file_put_contents($ruta_interna_final, $zip->getFromIndex($z)) !== false) {
                                    try {
                                        $stmt = $conn->prepare("INSERT INTO bitacora (usuario, archivo, link, accion) VALUES (?, ?, ?, ?)");
                                        $stmt->execute([$usuario_actual, basename($ruta_interna_final), $ruta_interna_final, "Subió (Extraído)"]);
                                    } catch (PDOException $e) { }
                                    $archivos_procesados++;
                                }
                            }
                            $zip->close();
                            unlink($ruta_final);
                        }
                    } else {
                        try {
                            $stmt = $conn->prepare("INSERT INTO bitacora (usuario, archivo, link, accion) VALUES (?, ?, ?, ?)");
                            $stmt->execute([$usuario_actual, $nombre_nuevo, $ruta_final, "Subió documento"]);
                        } catch (PDOException $e) {}
                        $archivos_procesados++;
                    }
                }
            }
        }
        
        $mensaje_final = $errores_acumulados;
        if ($archivos_procesados > 0) {
            $nombre_area_mos = $areas_existentes[$area_seleccionada] ?? $area_seleccionada;
            $nombre_cat_mos = $periodos_comunes[$categoria_seleccionada] ?? $categoria_seleccionada;
            $ruta_amigable = $nombre_area_mos . " > " . $anio_seleccionado . " > " . $nombre_cat_mos;
            $mensaje_final .= "<div class='alert alert-success'>¡Éxito! Se guardaron $archivos_procesados archivo(s) en la ruta: <br><i>$ruta_amigable</i></div>";
        }
        
        if (!empty($mensaje_final)) $_SESSION['mensaje_temp'] = $mensaje_final;
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $_SESSION['mensaje_temp'] = "<div class='alert alert-error'><b>Error:</b> Faltan datos de la ruta.</div>";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// --- 4. LÓGICA DE ELIMINACIÓN ---
if (isset($_POST['btn_eliminar'])) {
    $archivo_a_borrar = $_POST['ruta_eliminar'];
    $nombre_archivo_borrar = basename($archivo_a_borrar);
    
    $es_propietario = false;
    try {
        $stmt_verificar = $conn->prepare("SELECT usuario FROM bitacora WHERE archivo = ? LIMIT 1");
        $stmt_verificar->execute([$nombre_archivo_borrar]);
        $resultado = $stmt_verificar->fetch(PDO::FETCH_ASSOC);
        if ($resultado && $resultado['usuario'] === $usuario_actual) $es_propietario = true;
    } catch (PDOException $e) {}

    if (file_exists($archivo_a_borrar)) {
        if ($es_admin || $es_propietario) {
            unlink($archivo_a_borrar);
            try {
                $stmt = $conn->prepare("INSERT INTO bitacora (usuario, archivo, link, accion) VALUES (?, ?, ?, ?)");
                $stmt->execute([$usuario_actual, $nombre_archivo_borrar, $archivo_a_borrar, "Eliminó documento"]);
            } catch (PDOException $e) {}
            $_SESSION['mensaje_temp'] = "<div class='alert alert-success'>Archivo eliminado correctamente.</div>";
        } else {
            $_SESSION['mensaje_temp'] = "<div class='alert alert-error'><b>Seguridad:</b> No tienes permisos para eliminar este archivo.</div>";
        }
    }
    
    header("Location: " . $_SERVER['PHP_SELF']);
    exit;
}

// --- 5. EXPORTAR REPORTE EXCEL ---
if (isset($_POST['btn_descargar_excel_seleccion'])) {
    if (!empty($_POST['archivos_seleccionados'])) {
        $nombreArchivo = 'Reporte_Seleccionado_' . date('Y-m-d') . '.xls'; 
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment;filename="' . $nombreArchivo . '"');
        echo '<meta charset="utf-8">';
        echo '<table border="1">';
        echo '<tr style="background-color: #4f46e5; color: white; font-weight: bold;">';
        echo '<th>ID</th><th>Nombre del Documento</th><th>Ruta Interna</th><th>Subido por</th><th>Fecha</th><th>Enlace Directo</th>';
        echo '</tr>';
        
        $id = 1;
        $protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
        $directorio_base = rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . '/';
        $dominio_base = $protocolo . $_SERVER['HTTP_HOST'] . $directorio_base;
        
        foreach ($_POST['archivos_seleccionados'] as $ruta_relativa) {
            $nombreArch = basename($ruta_relativa);
            $ruta_para_mostrar = str_replace($carpeta_base, '', dirname($ruta_relativa)); 
            $url_completa = $dominio_base . $ruta_relativa;
            
            $creador = "Desconocido"; 
            try {
                $stmt_ex = $conn->prepare("SELECT usuario FROM bitacora WHERE archivo = ? LIMIT 1");
                $stmt_ex->execute([$nombreArch]);
                $res_ex = $stmt_ex->fetch(PDO::FETCH_ASSOC);
                if ($res_ex) $creador = $res_ex['usuario'];
            } catch (PDOException $e) { }
            
            $fecha_arch = date('d/m/Y H:i', filemtime($ruta_relativa));
            
            echo '<tr>';
            echo "<td>$id</td><td>$nombreArch</td><td>$ruta_para_mostrar</td><td>$creador</td><td>$fecha_arch</td>";
            echo "<td><a href=\"$url_completa\">$url_completa</a></td>";
            echo '</tr>';
            $id++;
        }
        echo '</table>';
        exit;
    } else {
        $_SESSION['mensaje_temp'] = "<div class='alert alert-error'>Debes seleccionar al menos un archivo para exportar a Excel.</div>";
        header("Location: " . $_SERVER['PHP_SELF']);
        exit;
    }
}

// --- 6. LECTURA PARA LA TABLA ---
$filtro_fecha = $_POST['filtro_fecha'] ?? 'todos';
$archivos_cargados = [];

if (is_dir($carpeta_base)) {
    $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($carpeta_base, RecursiveDirectoryIterator::SKIP_DOTS));
    $tiempo_actual = time();
    
    foreach ($iterador as $archivo) {
        if ($archivo->isFile() && $archivo->getFilename() !== '.htaccess') {
            $ruta = str_replace('\\', '/', $archivo->getPathname());
            $nombre_archivo_actual = $archivo->getFilename();
            
            $creador_db = "Desconocido";
            try {
                $stmt_creador = $conn->prepare("SELECT usuario FROM bitacora WHERE archivo = ? LIMIT 1");
                $stmt_creador->execute([$nombre_archivo_actual]);
                $resultado = $stmt_creador->fetch(PDO::FETCH_ASSOC);
                if ($resultado) $creador_db = $resultado['usuario'];
            } catch (PDOException $e) {}

            if (!$es_admin) {
                $area_del_usuario = normalizarNombreExacto($usuario_actual);
                $esta_en_su_area = (strpos($ruta, $carpeta_base . $area_del_usuario . '/') === 0);

                if ($creador_db !== $usuario_actual) {
                    if (!($creador_db === 'Desconocido' && $esta_en_su_area)) {
                        continue; 
                    }
                }
            }

            $fecha_mod = $archivo->getMTime();
            $dias_dif = ($tiempo_actual - $fecha_mod) / (60 * 60 * 24);
            if ($filtro_fecha === 'hoy' && $dias_dif > 1) continue;
            if ($filtro_fecha === '7dias' && $dias_dif > 7) continue;
            if ($filtro_fecha === '1mes' && $dias_dif > 30) continue;

            $archivos_cargados[] = [
                'ruta' => $ruta, 'nombre' => $nombre_archivo_actual, 
                'tamano' => $archivo->getSize(), 'fecha' => $fecha_mod, 'creador' => $creador_db
            ];
        }
    }
    usort($archivos_cargados, function($a, $b) { return $b['fecha'] - $a['fecha']; });
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel Documental</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Enlace al archivo CSS externo -->
    <link rel="stylesheet" href="style.css">
</head>
<body class="bg-dashboard">
    <div class="dashboard-container">
        <div class="header-panel">
            <h2>Panel Documental</h2>
            <div class="user-controls">
                <div class="user-badge">
                    <span>👤</span> <?= strtoupper($usuario_actual) ?>
                </div>
                <a href="?logout=true" class="logout-btn">Cerrar Sesión</a>
            </div>
        </div>
        
        <?php if(!empty($mensajes_sistema)) echo $mensajes_sistema; ?>

        <form method="POST" action="" enctype="multipart/form-data">
            <h3>1. Configurar Ruta de Destino</h3>
            <div class="ruta-container">
                <div>
                    <label>Área</label>
                    <?php if ($es_admin): ?>
                        <select name="area_destino" id="selector_area" required>
                            <?php foreach($areas_existentes as $key => $val): ?>
                                <option value="<?= $key ?>"><?= htmlspecialchars($val) ?></option>
                            <?php endforeach; ?>
                            <option value="nueva" style="font-weight:bold; color:var(--primary);">+ Crear nueva área...</option>
                        </select>
                        <input type="text" name="nueva_area" id="input_nueva_area" placeholder="Ej. Archivo Municipal" style="display:none; margin-top: 10px;">
                    <?php else: ?>
                        <p class="user-fixed-area"><?= strtoupper($usuario_actual) ?></p>
                    <?php endif; ?>
                </div>

                <div>
                    <label>Año</label>
                    <select name="anio_destino" required>
                        <?php 
                        $anio_actual = date('Y');
                        for($y = 2024; $y <= ($anio_actual + 1); $y++): ?>
                            <option value="<?= $y ?>" <?= ($y == $anio_actual) ? 'selected' : '' ?>><?= $y ?></option>
                        <?php endfor; ?>
                    </select>
                </div>

                <div>
                    <label>Categoría / Periodo</label>
                    <select name="categoria_destino" id="selector_categoria" required>
                        <?php foreach($periodos_comunes as $key => $val): ?>
                            <option value="<?= $key ?>"><?= htmlspecialchars($val) ?></option>
                        <?php endforeach; ?>
                        <option value="nueva" style="font-weight:bold; color:var(--primary);">+ Crear nueva categoría...</option>
                    </select>
                    <input type="text" name="nueva_categoria" id="input_nueva_categoria" placeholder="Ej. Licitaciones" style="display:none; margin-top: 10px;">
                </div>
            </div>

            <h3>2. Cargar Documentos</h3>
            <div id="drop-area">
                <p>📂 Arrastra aquí tus archivos (.pdf, .zip, .xlsx, .docx)</p>
                <p class="sub">O haz clic para buscar en tu equipo (Máx. <?= $limite_peso_mb ?> MB)</p>
                <input type="file" id="archivos" name="archivos[]" multiple required style="display:none;">
            </div>

            <div style="text-align: center; margin-bottom: 24px;">
                <p id="txtSeleccion" style="font-weight: 500; color: var(--text-muted); font-size: 14px;">Ningún archivo seleccionado</p>
            </div>

            <div style="text-align: center;">
                <button type="button" id="btn_cancelar" class="btn btn-cancelar">Descartar Selección</button>
                <button type="submit" name="btn_cargar_unificado" class="btn btn-cargar">☁️ Subir Archivos al Repositorio</button>
            </div>
        </form>

        <hr style="margin: 40px 0; border: 0; border-top: 1px solid var(--border-color);">

        <h3>Repositorio de Archivos</h3>
        
        <form method="POST" action="" class="filtro-card">
            <label>Filtrar por fecha: </label>
            <select name="filtro_fecha">
                <option value="todos" <?= ($filtro_fecha=='todos') ? 'selected' : '' ?>>Todos los tiempos</option>
                <option value="hoy" <?= ($filtro_fecha=='hoy') ? 'selected' : '' ?>>Hoy</option>
                <option value="7dias" <?= ($filtro_fecha=='7dias') ? 'selected' : '' ?>>Últimos 7 días</option>
                <option value="1mes" <?= ($filtro_fecha=='1mes') ? 'selected' : '' ?>>Último Mes</option>
            </select>
            <button type="submit" class="btn btn-cargar">Aplicar Filtro</button>
        </form>

        <form method="POST" action="">
            <button type="submit" name="btn_descargar_excel_seleccion" class="btn btn-herramienta">
                📥 Exportar Selección a Excel
            </button>

            <div class="tabla-contenedor">
                <table class="tabla-archivos">
                    <thead>
                        <tr>
                            <th style="width: 40px; text-align: center;"></th>
                            <th>Documento</th>
                            <th>Ruta en Repositorio</th>
                            <th>Subido por</th>
                            <th>Fecha</th>
                            <th style="text-align: right;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($archivos_cargados)): ?>
                            <tr>
                                <td colspan="6" style="text-align:center; padding: 30px; color: var(--text-muted);">No hay documentos disponibles.</td>
                            </tr>
                        <?php endif; ?>
                        
                        <?php foreach ($archivos_cargados as $archivo): 
                            $protocolo = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
                            $dir_base = rtrim(dirname($_SERVER['PHP_SELF']), '/\\') . '/';
                            $url_absoluta = $protocolo . $_SERVER['HTTP_HOST'] . $dir_base . $archivo['ruta'];
                            $ruta_mostrar = str_replace($carpeta_base, '', dirname($archivo['ruta']));
                        ?>
                        <tr>
                            <td style="text-align: center;">
                                <input type="checkbox" name="archivos_seleccionados[]" value="<?= htmlspecialchars($archivo['ruta']) ?>" class="checkbox-excel">
                            </td>
                            <td><b style="color: var(--text-main);"><?= htmlspecialchars($archivo['nombre']) ?></b></td>
                            <td class="td-ruta"><?= htmlspecialchars($ruta_mostrar) ?></td>
                            <td><span class="badge-user"><?= htmlspecialchars($archivo['creador']) ?></span></td>
                            <td style="color: var(--text-muted); font-size: 13px;"><?= date('d/m/Y', $archivo['fecha']) ?></td>
                            <td>
                                <div class="acciones-flex">
                                    <a href="<?= htmlspecialchars($archivo['ruta']) ?>" target="_blank" class="btn-icono btn-ver" title="Ver Documento">👁️</a>
                                    <button type="button" onclick="copiarAlPortapapeles('<?= htmlspecialchars($url_absoluta) ?>')" class="btn-icono btn-copiar" title="Copiar Enlace">📋</button>
                                    <form method="POST" style="display:inline; margin:0;" onsubmit="return confirm('¿Eliminar definitivamente este documento?');">
                                        <input type="hidden" name="ruta_eliminar" value="<?= htmlspecialchars($archivo['ruta']) ?>">
                                        <button type="submit" name="btn_eliminar" class="btn-icono btn-borrar" title="Eliminar Documento">🗑️</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </form>
    </div>
    
    <script>
        const selArea = document.getElementById('selector_area');
        const inputArea = document.getElementById('input_nueva_area');
        if(selArea) {
            selArea.addEventListener('change', (e) => {
                inputArea.style.display = (e.target.value === 'nueva') ? 'block' : 'none';
                inputArea.required = (e.target.value === 'nueva');
            });
        }

        const selCat = document.getElementById('selector_categoria');
        const inputCat = document.getElementById('input_nueva_categoria');
        if(selCat) {
            selCat.addEventListener('change', (e) => {
                inputCat.style.display = (e.target.value === 'nueva') ? 'block' : 'none';
                inputCat.required = (e.target.value === 'nueva');
            });
        }

        // --- FUNCIONES DRAG & DROP MEJORADAS ---
        const dropArea = document.getElementById('drop-area');
        const inputFile = document.getElementById('archivos');
        const txtSeleccion = document.getElementById('txtSeleccion');
        const btnCancelar = document.getElementById('btn_cancelar');

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, preventDefaults, false);
            document.body.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            dropArea.addEventListener(eventName, () => dropArea.classList.add('highlight'), false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, () => dropArea.classList.remove('highlight'), false);
        });

        dropArea.addEventListener('click', () => inputFile.click());

        dropArea.addEventListener('drop', (e) => {
            const dt = e.dataTransfer;
            const files = dt.files;
            inputFile.files = files;
            actNames();
        }, false);

        inputFile.addEventListener('change', actNames);

        function actNames() {
            if (inputFile.files.length === 0) {
                txtSeleccion.innerHTML = 'Ningún archivo seleccionado';
                btnCancelar.style.display = 'none'; 
                return;
            }
            btnCancelar.style.display = 'inline-block';
            let h = `<span style="color:var(--primary);"><b>${inputFile.files.length}</b> archivo(s) listos para subir:</span><ul style="text-align:left; font-size:14px; max-width:400px; margin:10px auto; background:#f8fafc; padding:15px 30px; border-radius:8px; border:1px solid #e2e8f0;">`;
            for (let i=0; i<inputFile.files.length; i++) h += `<li style="margin-bottom:5px; color:#475569;">📄 ${inputFile.files[i].name}</li>`;
            txtSeleccion.innerHTML = h + `</ul>`;
        }

        btnCancelar.addEventListener('click', () => { inputFile.value = ''; actNames(); });

        function mostrarToast(mensaje) {
            const toastPrevio = document.getElementById('toast-alerta');
            if (toastPrevio) {
                toastPrevio.remove();
            }

            const toast = document.createElement('div');
            toast.id = 'toast-alerta';
            toast.className = 'toast-mensaje';
            toast.innerHTML = mensaje;
            document.body.appendChild(toast);

            toast.offsetHeight; 

            toast.classList.add('mostrar');

            setTimeout(() => {
                toast.classList.remove('mostrar');
                setTimeout(() => {
                    if(toast.parentElement) toast.remove();
                }, 300);
            }, 3000);
        }

       function copiarAlPortapapeles(url) {
            const tempInput = document.createElement("input");
            tempInput.style.position = "absolute";
            tempInput.style.left = "-9999px";
            tempInput.value = url;
            document.body.appendChild(tempInput);
            tempInput.select();
            tempInput.setSelectionRange(0, 99999);
            try {
                document.execCommand("copy");
                mostrarToast("✅ <b>¡Copiado!</b> Enlace guardado en el portapapeles");
            } catch (err) {
                prompt("Tu navegador bloqueó el copiado. Copia el enlace manualmente:", url);
            }
            document.body.removeChild(tempInput);
        }
    </script>
</body>
</html>