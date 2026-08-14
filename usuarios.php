<?php
session_start();

require_once 'conexion.php';

// Control de acceso: Solo administradores logeados
if (!isset($_SESSION['usuario_logeado']) || !$_SESSION['es_admin']) {
    header("Location: index.php");
    exit;
}

$mensajes_sistema = "";
$usuario_actual = $_SESSION['usuario_logeado'];

// Función para limpiar nombres de carpeta
function normalizar($nombre) {
    $nombre = str_replace(['á','é','í','ó','ú','Á','É','Í','Ó','Ú','ñ','Ñ',' '], ['a','e','i','o','u','a','e','i','o','u','n','n','_'], $nombre);
    return strtolower(preg_replace('/[^a-zA-Z0-9_-]/', '', $nombre));
}

// --- 1. CREAR USUARIO ---
if (isset($_POST['btn_crear'])) {
    $usr = strtolower(trim($_POST['username']));
    $pass = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $rol = $_POST['rol'];
    $id_a = null;

    try {
        $stmtChk = $conn->prepare("SELECT id FROM usuarios WHERE username = ?");
        $stmtChk->execute([$usr]);
        if ($stmtChk->fetch()) {
            $mensajes_sistema = "<div class='alert alert-error'><b>Error:</b> El usuario '$usr' ya existe.</div>";
        } else {
            if ($rol !== 'admin') {
                if ($_POST['area_sel'] === 'nueva') {
                    $nom_a = trim($_POST['nueva_area']);
                    $dir_a = normalizar($nom_a);
                    
                    $dir_f = $dir_a; $c = 1;
                    while (file_exists('repositorio_oficial/' . $dir_f)) { 
                        $dir_f = $dir_a . '_' . $c; 
                        $c++; 
                    }
                    
                    $st = $conn->prepare("INSERT INTO areas (nombre, directorio, estatus) VALUES (?, ?, 1)");
                    $st->execute([$nom_a, $dir_f]);
                    $id_a = $conn->lastInsertId();
                    mkdir('repositorio_oficial/' . $dir_f, 0755, true);
                } else {
                    $id_a = $_POST['area_sel'] ?? null;
                }
            }
            $st = $conn->prepare("INSERT INTO usuarios (username, password, rol, id_area) VALUES (?, ?, ?, ?)");
            $st->execute([$usr, $pass, $rol, $id_a]);
            $mensajes_sistema = "<div class='alert alert-success'>Usuario <b>$usr</b> registrado correctamente.</div>";
        }
    } catch(PDOException $e) { 
        $mensajes_sistema = "<div class='alert alert-error'>Error: " . $e->getMessage() . "</div>"; 
    }
}

// --- 2. ELIMINAR USUARIO ---
if (isset($_POST['btn_eliminar'])) {
    $id = (int)$_POST['id_eliminar'];
    if ($_POST['nom_eliminar'] === $usuario_actual) {
        $mensajes_sistema = "<div class='alert alert-warning'><b>Seguridad:</b> No puedes eliminar tu propia cuenta en sesión.</div>";
    } else {
        $conn->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$id]);
        $mensajes_sistema = "<div class='alert alert-success'>Usuario eliminado del sistema.</div>";
    }
}

// --- 3. EDITAR USUARIO ---
if (isset($_POST['btn_editar'])) {
    $id = (int)$_POST['edit_id'];
    $usr = strtolower(trim($_POST['edit_usr']));
    $rol = $_POST['edit_rol'];
    $id_a = null;

    try {
        if ($rol !== 'admin') {
            if ($_POST['edit_area_sel'] === 'nueva') {
                $nom_a = trim($_POST['edit_nueva_area']);
                $dir_a = normalizar($nom_a);
                
                $dir_f = $dir_a; $c = 1;
                while (file_exists('repositorio_oficial/' . $dir_f)) { 
                    $dir_f = $dir_a . '_' . $c; 
                    $c++; 
                }
                
                $st = $conn->prepare("INSERT INTO areas (nombre, directorio, estatus) VALUES (?, ?, 1)");
                $st->execute([$nom_a, $dir_f]);
                $id_a = $conn->lastInsertId();
                mkdir('repositorio_oficial/' . $dir_f, 0755, true);
            } else {
                $id_a = $_POST['edit_area_sel'] ?? null;
            }
        }

        if (!empty($_POST['edit_pass'])) {
            $st = $conn->prepare("UPDATE usuarios SET username=?, password=?, rol=?, id_area=? WHERE id=?");
            $st->execute([$usr, password_hash($_POST['edit_pass'], PASSWORD_BCRYPT), $rol, $id_a, $id]);
        } else {
            $st = $conn->prepare("UPDATE usuarios SET username=?, rol=?, id_area=? WHERE id=?");
            $st->execute([$usr, $rol, $id_a, $id]);
        }
        $mensajes_sistema = "<div class='alert alert-success'>Datos del usuario <b>$usr</b> actualizados correctamente.</div>";
    } catch(PDOException $e) {
        $mensajes_sistema = "<div class='alert alert-error'>Error: " . $e->getMessage() . "</div>";
    }
}

// --- LECTURA DE CATÁLOGOS ---
$areas = $conn->query("SELECT id, nombre FROM areas WHERE estatus = 1 ORDER BY nombre ASC")->fetchAll(PDO::FETCH_ASSOC);

$usuarios = $conn->query("
    SELECT u.id, u.username, u.rol, u.id_area, a.nombre as area_nom, a.estatus as area_estatus 
    FROM usuarios u 
    LEFT JOIN areas a ON u.id_area = a.id 
    ORDER BY u.id DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestión de Usuarios</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=4">
</head>
<body class="bg-dashboard">
    <div class="dashboard-container">
        <div class="header-panel">
            <h2>Gestión de Usuarios</h2>
            <div class="user-controls">
                <a href="index.php" class="btn btn-cargar" style="background:#475569; text-decoration:none;">⬅️ Volver al Panel</a>
                <a href="areas.php" class="btn btn-cargar" style="background:#0284c7; text-decoration:none;">📂 Gestión de Áreas</a>
            </div>
        </div>
        
        <?= $mensajes_sistema ?>

        <div style="background:#f8fafc; padding:20px; border-radius:8px; border:1px solid #e2e8f0; margin-bottom:20px;">
            <h3 style="margin-top:0; color:#1e293b;">👤 Registrar Nuevo Usuario</h3>
            <form method="POST">
                <div class="ruta-container">
                    <div>
                        <label>Usuario</label>
                        <input type="text" name="username" required placeholder="ej. obras_usr">
                    </div>
                    <div>
                        <label>Contraseña</label>
                        <input type="password" name="password" required placeholder="••••••••">
                    </div>
                    <div>
                        <label>Rol asignado</label>
                        <select name="rol" id="rol_c">
                            <option value="usuario">Usuario de Área</option>
                            <option value="admin">Administrador General</option>
                        </select>
                    </div>
                    <div id="div_area_c">
                        <label>Área / Departamento</label>
                        <select name="area_sel" id="area_c" required>
                            <option value="">- Seleccione Área -</option>
                            <?php foreach($areas as $a): ?>
                                <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['nombre']) ?></option>
                            <?php endforeach; ?>
                            <option value="nueva" style="font-weight:bold; color:var(--primary, #2b6cb0);">+ Crear nueva área...</option>
                        </select>
                        <input type="text" name="nueva_area" id="inp_area_c" placeholder="Nombre de la nueva área" style="display:none; margin-top:5px;">
                    </div>
                </div>
                <button type="submit" name="btn_crear" class="btn btn-cargar" style="background:#16a34a;">Guardar Usuario</button>
            </form>
        </div>

        <h3>Usuarios Registrados en el Sistema</h3>
        <div class="tabla-contenedor">
            <table class="tabla-archivos">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Usuario</th>
                        <th>Rol</th>
                        <th>Área Asignada</th>
                        <th style="text-align:right;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($usuarios as $u): ?>
                    <tr>
                        <td><?= $u['id'] ?></td>
                        <td><b style="color:var(--text-main, #0f172a);"><?= htmlspecialchars($u['username']) ?></b></td>
                        <td><span class="badge-user"><?= strtoupper($u['rol']) ?></span></td>
                        <td style="color:var(--text-muted, #64748b);">
                            <?php 
                                if ($u['rol'] === 'admin') {
                                    echo '<i>Acceso Total</i>';
                                } else {
                                    if (!empty($u['area_nom'])) {
                                        echo htmlspecialchars($u['area_nom']);
                                        if ($u['area_estatus'] == 0) {
                                            echo " <span style='color:#ef4444; font-size:11px;'>(Inactiva)</span>";
                                        }
                                    } else {
                                        echo 'Sin área asignada';
                                    }
                                }
                            ?>
                        </td>
                        <td>
                            <div class="acciones-flex">
                                <button type="button" class="btn-icono btn-ver" title="Editar" onclick="abrirEdit(<?= $u['id']?>,'<?= htmlspecialchars($u['username'], ENT_QUOTES)?>','<?= $u['rol']?>','<?= $u['id_area']?>')">✏️</button>
                                <form method="POST" style="margin:0;" onsubmit="return confirm('¿Eliminar definitivamente a este usuario?');">
                                    <input type="hidden" name="id_eliminar" value="<?= $u['id'] ?>">
                                    <input type="hidden" name="nom_eliminar" value="<?= htmlspecialchars($u['username']) ?>">
                                    <button type="submit" name="btn_eliminar" class="btn-icono btn-borrar" title="Borrar">🗑️</button>
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
                <h3>Modificar Usuario</h3>
                <button type="button" class="close-btn" onclick="cerrarModal()">&times;</button>
            </div>
            <form method="POST" id="formEditarUsuario" onsubmit="return confirmarEdicion();">
                <input type="hidden" name="edit_id" id="e_id">
                
                <div class="form-group-modal">
                    <label for="e_usr">Nombre de Usuario</label>
                    <input type="text" name="edit_usr" id="e_usr" required>
                </div>
                
                <div class="form-group-modal">
                    <label for="e_pass">Nueva Contraseña <small style="color:#64748b; font-weight:normal;">(Dejar en blanco para no cambiar)</small></label>
                    <input type="password" name="edit_pass" id="e_pass" placeholder="••••••••">
                </div>
                
                <div class="form-group-modal">
                    <label for="e_rol">Rol Asignado</label>
                    <select name="edit_rol" id="e_rol">
                        <option value="usuario">Usuario de Área</option>
                        <option value="admin">Administrador General</option>
                    </select>
                </div>
                
                <div class="form-group-modal" id="e_div_area">
                    <label for="e_area">Área / Departamento</label>
                    <select name="edit_area_sel" id="e_area">
                        <option value="">- Seleccione Área -</option>
                        <?php foreach($areas as $a): ?>
                            <option value="<?= $a['id'] ?>"><?= htmlspecialchars($a['nombre']) ?></option>
                        <?php endforeach; ?>
                        <option value="nueva" style="font-weight:bold; color:var(--primary, #2b6cb0);">+ Crear nueva área...</option>
                    </select>
                    <input type="text" name="edit_nueva_area" id="e_inp_area" placeholder="Nombre de la nueva área" style="display:none; margin-top:8px;">
                </div>
                
                <div class="modal-actions">
                    <button type="button" class="btn-modal-cancel" onclick="cerrarModal()">Cancelar</button>
                    <button type="submit" name="btn_editar" class="btn-modal-save">Guardar Cambios</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        const cbR = (rolSel, divA, selA) => { 
            if (rolSel.value === 'admin') { 
                divA.style.display = 'none'; 
                selA.required = false; 
            } else { 
                divA.style.display = 'block'; 
                selA.required = true; 
            } 
        };
        
        const cbA = (selA, inpA) => { 
            inpA.style.display = selA.value === 'nueva' ? 'block' : 'none'; 
            inpA.required = selA.value === 'nueva'; 
        };
        
        document.getElementById('rol_c').addEventListener('change', e => cbR(e.target, document.getElementById('div_area_c'), document.getElementById('area_c')));
        document.getElementById('area_c').addEventListener('change', e => cbA(e.target, document.getElementById('inp_area_c')));
        
        document.getElementById('e_rol').addEventListener('change', e => cbR(e.target, document.getElementById('e_div_area'), document.getElementById('e_area')));
        document.getElementById('e_area').addEventListener('change', e => cbA(e.target, document.getElementById('e_inp_area')));

        function abrirEdit(id, usr, rol, ida) {
            document.getElementById('e_id').value = id;
            document.getElementById('e_usr').value = usr;
            document.getElementById('e_pass').value = '';
            document.getElementById('e_rol').value = rol;
            
            document.getElementById('e_rol').dispatchEvent(new Event('change'));
            
            document.getElementById('e_area').value = (ida && ida !== 'null' && ida !== '') ? ida : '';
            document.getElementById('e_inp_area').style.display = 'none';
            document.getElementById('e_inp_area').required = false;
            
            document.getElementById('mEdit').style.display = 'flex';
        }

        function cerrarModal() {
            document.getElementById('mEdit').style.display = 'none';
        }

        window.addEventListener('click', (e) => {
            const modal = document.getElementById('mEdit');
            if (e.target === modal) {
                cerrarModal();
            }
        });

        function confirmarEdicion() {
            const username = document.getElementById('e_usr').value.trim();
            const pass = document.getElementById('e_pass').value;
            let msg = `¿Confirmas que deseas actualizar la información del usuario "${username}"?`;
            if (pass !== '') {
                msg += '\n\n⚠️ Nota: Se actualizará la contraseña de acceso.';
            }
            return confirm(msg);
        }
    </script>
</body>
</html>