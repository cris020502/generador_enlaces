<?php
session_start();
$servername = "localhost:3306";
$username = "root";
$password = ""; // Tu contraseña local
$dbname = "bd_gestor_documental";

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

// Control de acceso: Solo administradores logeados
if (!isset($_SESSION['usuario_logeado']) || !$_SESSION['es_admin']) {
    header("Location: index.php");
    exit;
}

$mensajes_sistema = "";
$usuario_actual = $_SESSION['usuario_logeado'];

// Función de sanitizado para carpetas físicas
function normalizar($nombre) {
    $nombre = str_replace(['á','é','í','ó','ú','Á','É','Í','Ó','Ú','ñ','Ñ',' '], ['a','e','i','o','u','a','e','i','o','u','n','n','_'], $nombre);
    return strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', $nombre));
}

// --- 1. CREAR ÁREA MANUALMENTE ---
if (isset($_POST['btn_crear'])) {
    $nom = trim($_POST['nombre_area']);
    $dir_a = normalizar($nom);
    
    // Prevención de colisión de carpetas físicas en disco
    $dir_f = $dir_a; 
    $c = 1;
    while(file_exists('repositorio_oficial/' . $dir_f)) { 
        $dir_f = $dir_a . '_' . $c; 
        $c++; 
    }
    
    try {
        // Verificar si el nombre visible ya existe
        $stmtChk = $conn->prepare("SELECT id FROM areas WHERE nombre = ?");
        $stmtChk->execute([$nom]);
        if ($stmtChk->fetch()) {
            $mensajes_sistema = "<div class='alert alert-error'><b>Error:</b> Ya existe un área registrada con el nombre '$nom'.</div>";
        } else {
            $st = $conn->prepare("INSERT INTO areas (nombre, directorio, estatus) VALUES (?, ?, 1)");
            $st->execute([$nom, $dir_f]);
            
            // Crear carpeta física
            $ruta_nueva = 'repositorio_oficial/' . $dir_f;
            if (!file_exists($ruta_nueva)) {
                mkdir($ruta_nueva, 0755, true);
            }
            $mensajes_sistema = "<div class='alert alert-success'>Área '<b>$nom</b>' creada exitosamente en <i>/$dir_f/</i>.</div>";
        }
    } catch(PDOException $e) { 
        $mensajes_sistema = "<div class='alert alert-error'><b>Error SQL:</b> " . htmlspecialchars($e->getMessage()) . "</div>"; 
    }
}

// --- 2. RENOMBRAR ÁREA (REGLA DE ORO: NO TOCAR DIRECTORIO) ---
if (isset($_POST['btn_editar'])) {
    $id = (int)$_POST['edit_id'];
    $nom = trim($_POST['edit_nom']);
    
    try {
        $conn->prepare("UPDATE areas SET nombre = ? WHERE id = ?")->execute([$nom, $id]);
        $mensajes_sistema = "<div class='alert alert-success'>Nombre visible del área actualizado a '<b>$nom</b>'.</div>";
    } catch(PDOException $e) {
        $mensajes_sistema = "<div class='alert alert-error'><b>Error al actualizar:</b> " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// --- 3. CAMBIAR ESTADO (BAJA LÓGICA: ACTIVAR / DESACTIVAR) ---
if (isset($_POST['btn_toggle'])) {
    $id = (int)$_POST['toggle_id'];
    $est = (int)$_POST['toggle_val'];
    
    try {
        $conn->prepare("UPDATE areas SET estatus = ? WHERE id = ?")->execute([$est, $id]);
        $estado_txt = ($est == 1) ? 'activada' : 'desactivada';
        $mensajes_sistema = "<div class='alert alert-success'>El área ha sido $estado_txt correctamente.</div>";
    } catch(PDOException $e) {
        $mensajes_sistema = "<div class='alert alert-error'><b>Error:</b> " . htmlspecialchars($e->getMessage()) . "</div>";
    }
}

// LECTURA DEL CATÁLOGO COMPLETO DE ÁREAS
$areas = $conn->query("SELECT * FROM areas ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Áreas</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
</head>
<body class="bg-dashboard">
    <div class="dashboard-container">
        <div class="header-panel">
            <h2>Gestión de Catálogo de Áreas</h2>
            <div class="user-controls">
                <a href="index.php" class="btn btn-cargar" style="background:#475569; text-decoration:none;">⬅️ Volver al Panel</a>
                <a href="usuarios.php" class="btn btn-cargar" style="background:#4f46e5; text-decoration:none;">⚙️ Usuarios</a>
            </div>
        </div>

        <?= $mensajes_sistema ?>

        <div style="background:#f8fafc; padding:20px; border-radius:8px; border:1px solid #e2e8f0; margin-bottom:20px;">
            <h3 style="margin-top:0; color:#1e293b;">📂 Crear Nueva Área</h3>
            <form method="POST" style="display:flex; gap:15px; align-items:flex-end; flex-wrap:wrap;">
                <div style="flex:1; min-width:250px;">
                    <label>Nombre Oficial del Área</label>
                    <input type="text" name="nombre_area" required placeholder="Ej. Registro Civil, Obras Públicas">
                </div>
                <button type="submit" name="btn_crear" class="btn btn-cargar" style="background:#16a34a;">Registrar Área</button>
            </form>
        </div>

        <h3>Catálogo de Áreas del Municipio</h3>
        <p style="font-size:13px; color:var(--text-muted); margin-top:-5px; margin-bottom:15px;">
            <b>Nota de Seguridad:</b> Al editar un área solo se actualiza su nombre visible. La carpeta física permanece intacta para evitar romper hipervínculos públicos generados anteriormente.
        </p>

        <div class="tabla-contenedor">
            <table class="tabla-archivos">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre Visible</th>
                        <th>Directorio Físico</th>
                        <th>Estado</th>
                        <th style="text-align:right;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($areas)): ?>
                        <tr>
                            <td colspan="5" style="text-align:center; padding:20px; color:#666;">No hay áreas registradas en el catálogo.</td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach($areas as $a): ?>
                    <tr>
                        <td><?= $a['id'] ?></td>
                        <td><b style="color:var(--text-main);"><?= htmlspecialchars($a['nombre']) ?></b></td>
                        <td style="color:#666; font-size:12px; font-family:monospace;">/<?= htmlspecialchars($a['directorio']) ?>/</td>
                        <td>
                            <?php if($a['estatus'] == 1): ?>
                                <span class="badge-user" style="background:#dcfce7; color:#166534;">Activa</span>
                            <?php else: ?>
                                <span class="badge-user" style="background:#fee2e2; color:#991b1b;">Inactiva</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="acciones-flex">
                                <button type="button" class="btn-icono btn-ver" title="Renombrar Área" onclick="abrirEdit(<?= $a['id']?>,'<?= htmlspecialchars($a['nombre'], ENT_QUOTES) ?>')">✏️</button>
                                
                                <form method="POST" style="margin:0;" onsubmit="return confirm('¿Desea cambiar el estado de esta área?');">
                                    <input type="hidden" name="toggle_id" value="<?= $a['id'] ?>">
                                    <input type="hidden" name="toggle_val" value="<?= $a['estatus'] == 1 ? 0 : 1 ?>">
                                    <button type="submit" name="btn_toggle" class="btn-icono <?= $a['estatus'] == 1 ? 'btn-borrar' : 'btn-ver' ?>" title="<?= $a['estatus'] == 1 ? 'Desactivar Área' : 'Activar Área' ?>">
                                        <?= $a['estatus'] == 1 ? '⛔' : '✅' ?>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="modal-overlay" id="mEdit">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Renombrar Área</h3>
                <button type="button" class="close-btn" onclick="cerrarModal()">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="edit_id" id="e_id">
                
                <label>Nuevo Nombre Visible</label>
                <input type="text" name="edit_nom" id="e_nom" required style="margin-bottom:15px;">
                
                <div class="alert alert-warning" style="font-size:12px; margin-bottom:15px; padding:10px;">
                    ℹ️ La ruta física de la carpeta no se modificará para asegurar la persistencia de los hipervínculos generados.
                </div>
                
                <div style="text-align:right;">
                    <button type="button" class="btn btn-cancelar" style="display:inline-block; background:#64748b;" onclick="cerrarModal()">Cancelar</button>
                    <button type="submit" name="btn_editar" class="btn btn-cargar">Actualizar Nombre</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function abrirEdit(id, nom) {
            document.getElementById('e_id').value = id;
            document.getElementById('e_nom').value = nom;
            document.getElementById('mEdit').style.display = 'flex';
        }

        function cerrarModal() {
            document.getElementById('mEdit').style.display = 'none';
        }
    </script>
</body>
</html>