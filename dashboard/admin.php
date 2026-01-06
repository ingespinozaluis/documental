<?php
// dashboard/admin.php
session_start();
require_once __DIR__ . '/../includes/funciones_usuario.php';
require_once __DIR__ . '/../includes/funciones_documento.php';

// --- CONTROL DE ACCESO ---
if (!verificarRol('Administrador')) {
    header('Location: ../login.php');
    exit();
}

$idUsuarioActual = $_SESSION['usuario_id'];
$mensaje = '';
$usuarioAEditar = null; // Variable para almacenar los datos del usuario si se está editando

// --- LÓGICA DE MANEJO POST (CRUD y Aprobación) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    // 1. GESTIÓN DE APROBACIÓN DE DOCUMENTOS
    if ($accion === 'aprobar_documento') {
        $idDocumento = $_POST['idDocumento'] ?? 0;
        if ($idDocumento && aprobarDocumento($idDocumento, $idUsuarioActual)) {
            $mensaje = "<p class='success'>✅ Documento ID {$idDocumento} aprobado con éxito. Ahora es visible para todos los usuarios.</p>";
        } else {
            $mensaje = "<p class='error'>❌ Error al aprobar el documento o el ID no es válido.</p>";
        }
    }

    // 2. GESTIÓN DE USUARIOS (CRUD: Crear, Eliminar)
    $idUsuario = $_POST['idUsuario'] ?? null;
    $nombre = $_POST['nombre'] ?? '';
    $correo = $_POST['correo'] ?? '';
    $password = $_POST['password'] ?? '';
    $rol = $_POST['idRol'] ?? ''; // Para la creación

    if ($accion === 'crear') {
        if (!empty($nombre) && !empty($correo) && !empty($password) && !empty($rol)) {
            if (crearUsuario($nombre, $correo, $password, $rol)) {
                $mensaje = "<p class='success'>✅ Usuario creado con éxito como {$rol}.</p>";
            } else {
                $mensaje = "<p class='error'>❌ Error al crear el usuario. El correo ya podría existir o faltan datos.</p>";
            }
        }
    } elseif ($accion === 'eliminar') {
        if ($idUsuario && $idUsuario != $idUsuarioActual && eliminarUsuario($idUsuario)) {
            $mensaje = "<p class='success'>✅ Usuario ID {$idUsuario} eliminado con éxito.</p>";
        } else {
            $mensaje = "<p class='error'>❌ Error al eliminar el usuario o intentó eliminarse a sí mismo.</p>";
        }
    }

    // 3. GESTIÓN DE EDICIÓN DE USUARIOS (UPDATE)
    if ($accion === 'actualizar') {
        $idUsuarioEdit = $_POST['idUsuarioEdit'] ?? 0;
        $nombreEdit = $_POST['nombreEdit'] ?? '';
        $correoEdit = $_POST['correoEdit'] ?? '';
        $rolEdit = $_POST['rolEdit'] ?? '';
        $passwordEdit = $_POST['passwordEdit'] ?? null; // La contraseña puede ser null/vacía

        if ($idUsuarioEdit) {
            if (actualizarUsuario($idUsuarioEdit, $nombreEdit, $correoEdit, $rolEdit, $passwordEdit)) {
                // Si el usuario edita su propia cuenta, actualiza la sesión
                if ($idUsuarioEdit == $idUsuarioActual) {
                    $_SESSION['usuario_nombre'] = $nombreEdit;
                    $_SESSION['usuario_rol'] = $rolEdit;
                }
                $mensaje = "<p class='success'>✅ Usuario ID {$idUsuarioEdit} actualizado con éxito.</p>";
            } else {
                $mensaje = "<p class='error'>❌ Error al actualizar el usuario.</p>";
            }
        }
    }
}

// --- LÓGICA DE CARGA DE DATOS PARA EDICIÓN (GET) ---
// Si se presiona el botón 'Editar', cargamos los datos
if (isset($_GET['editar'])) {
    $idEditar = $_GET['editar'];
    $usuarioAEditar = obtenerUsuarioPorId($idEditar);
    if (!$usuarioAEditar) {
        $mensaje = "<p class='error'>❌ Usuario no encontrado.</p>";
    }
}


// --- CARGA DE DATOS PARA VISTAS ---
$historial = obtenerHistorialCompleto();
$usuarios = obtenerUsuarios();
$roles = obtenerRoles(); 
$documentosPendientes = obtenerDocumentosPendientes();

// --- LÓGICA DE BÚSQUEDA DE DOCUMENTOS ---
$documentosBuscados = [];
$terminoBusqueda = $_GET['buscar'] ?? '';

if (!empty($terminoBusqueda)) {
    // El Admin busca documentos
    $documentosBuscados = buscarDocumentos($terminoBusqueda, $_SESSION['usuario_rol']);
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Administración</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f9; color: #333; }
        .container { max-width: 1200px; margin: 30px auto; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1, h2, h3 { border-bottom: 2px solid #eee; padding-bottom: 10px; margin-top: 20px; }
        .success { color: #155724; background-color: #d4edda; border: 1px solid #c3e6cb; padding: 10px; border-radius: 4px; }
        .error { color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background-color: #343a40; color: white; }
        .form-user input, .form-user select { padding: 8px; margin-right: 10px; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 4px; }
        .form-user button { padding: 8px 15px; border: none; cursor: pointer; border-radius: 4px; background-color: #007bff; color: white; }
        
        /* Estilos para la búsqueda */
        .search-section { padding: 20px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 20px; }
        .search-section input[type="text"] { width: 70%; padding: 10px; margin-right: 10px; border: 1px solid #ccc; border-radius: 4px; }
        .search-section button { padding: 10px 15px; background-color: #6c757d; color: white; border: none; cursor: pointer; border-radius: 4px; }
        .download-link { color: #007bff; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Panel de Administración 🛡️</h1>
        <p>Usuario: <strong><?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?></strong></p>
        
        <?php echo $mensaje; ?>

        <h2>🔎 Búsqueda de Documentos</h2>
        <div class="search-section">
            <form method="GET" action="admin.php">
                <input type="text" name="buscar" placeholder="Buscar por título del documento..." value="<?php echo htmlspecialchars($terminoBusqueda); ?>" required>
                <button type="submit">Buscar</button>
                <?php if (!empty($terminoBusqueda)): ?>
                    <a href="admin.php" style="margin-left: 10px; color: #dc3545;">Limpiar Búsqueda</a>
                <?php endif; ?>
            </form>
        </div>

        <?php if (!empty($terminoBusqueda)): ?>
            <h3>Resultados de la Búsqueda (Término: "<?php echo htmlspecialchars($terminoBusqueda); ?>")</h3>
            <?php if (empty($documentosBuscados)): ?>
                <p>No se encontraron documentos con el término "<?php echo htmlspecialchars($terminoBusqueda); ?>".</p>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>ID Doc.</th>
                            <th>Título</th>
                            <th>Categoría</th>
                            <th>Subido por</th>
                            <th>Estado</th>
                            <th>Acceso</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documentosBuscados as $doc): ?>
                            <tr>
                                <td><?php echo $doc['idDocumento']; ?></td>
                                <td><?php echo htmlspecialchars($doc['titulo']); ?></td>
                                <td><?php echo htmlspecialchars($doc['categoria']); ?></td>
                                <td><?php echo htmlspecialchars($doc['usuarioSubio']); ?></td>
                                <td><?php echo htmlspecialchars($doc['estado']); ?></td>
                                <td>
                                    <a href="../download.php?file=<?php echo urlencode($doc['rutaArchivo']); ?>" class="download-link" target="_blank">Descargar</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            <hr>
        <?php endif; ?>

        <h2>📄 Documentos Pendientes de Aprobación</h2>

        <?php if (empty($documentosPendientes)): ?>
            <p>No hay documentos pendientes de aprobación en este momento.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID Doc.</th>
                        <th>Título</th>
                        <th>Subido por</th>
                        <th>Categoría</th>
                        <th>Fecha Subida</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($documentosPendientes as $doc): ?>
                        <tr>
                            <td><?php echo $doc['idDocumento']; ?></td>
                            <td><?php echo htmlspecialchars($doc['titulo']); ?></td>
                            <td><?php echo htmlspecialchars($doc['usuarioSubio']); ?></td>
                            <td><?php echo htmlspecialchars($doc['categoria']); ?></td>
                            <td><?php echo htmlspecialchars($doc['fechaSubida']); ?></td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="accion" value="aprobar_documento">
                                    <input type="hidden" name="idDocumento" value="<?php echo $doc['idDocumento']; ?>">
                                    <button type="submit" style="background-color: #ffc107; color: black; font-weight: bold;">Aprobar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <hr>

        <h2>📜 Historial Completo de Actividad</h2>
        
        <?php if (empty($historial)): ?>
            <p>No hay registros de actividad en el historial.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Fecha</th>
                        <th>Usuario</th>
                        <th>Acción Realizada</th>
                        <th>Documento Afectado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historial as $reg): ?>
                        <tr>
                            <td><?php echo $reg['idHistorial']; ?></td>
                            <td><?php echo htmlspecialchars($reg['fecha']); ?></td>
                            <td><?php echo htmlspecialchars($reg['usuario']); ?></td>
                            <td><?php echo htmlspecialchars($reg['accion']); ?></td>
                            <td><?php echo htmlspecialchars($reg['documento_titulo'] ?? 'N/A'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <hr>

        <h2>👥 Gestión de Usuarios y Roles</h2>

        <?php if ($usuarioAEditar): ?>
            <h3>✏️ Editar Usuario: <?php echo htmlspecialchars($usuarioAEditar['nombre']); ?></h3>
            <div style="border: 2px solid #007bff; padding: 15px; margin-bottom: 20px; border-radius: 5px; background-color: #e9f7ff;">
                <form method="POST" action="admin.php" class="form-user">
                    <input type="hidden" name="accion" value="actualizar">
                    <input type="hidden" name="idUsuarioEdit" value="<?php echo htmlspecialchars($usuarioAEditar['idUsuario']); ?>">
                    
                    <label>Nombre:</label>
                    <input type="text" name="nombreEdit" value="<?php echo htmlspecialchars($usuarioAEditar['nombre']); ?>" required>
                    
                    <label>Correo:</label>
                    <input type="email" name="correoEdit" value="<?php echo htmlspecialchars($usuarioAEditar['correo']); ?>" required>
                    
                    <label>Rol:</label>
                    <select name="rolEdit" required>
                        <?php foreach ($roles as $rol): ?>
                            <?php 
                            $rolNombre = htmlspecialchars($rol['nombre']);
                            $selected = ($rolNombre === $usuarioAEditar['rol']) ? 'selected' : ''; 
                            ?>
                            <option value="<?php echo $rolNombre; ?>" <?php echo $selected; ?>><?php echo $rolNombre; ?></option>
                        <?php endforeach; ?>
                    </select>
                    
                    <label>Nueva Contraseña (Dejar vacío si no cambia):</label>
                    <input type="password" name="passwordEdit" placeholder="Nueva Contraseña">
                    
                    <button type="submit" style="background-color: #007bff;">Guardar Cambios</button>
                    <a href="admin.php" style="margin-left: 10px; color: #6c757d; text-decoration: none;">Cancelar</a>
                </form>
            </div>
            <hr>
        <?php endif; ?>

        <h3>Crear Nuevo Usuario</h3>
        <form method="POST" action="admin.php" class="form-user">
            <input type="hidden" name="accion" value="crear">
            <input type="text" name="nombre" placeholder="Nombre completo" required>
            <input type="email" name="correo" placeholder="Correo electrónico" required>
            <input type="password" name="password" placeholder="Contraseña" required>
            <select name="idRol" required>
                <option value="">Seleccione Rol</option>
                <?php foreach ($roles as $rol): ?>
                    <option value="<?php echo htmlspecialchars($rol['nombre']); ?>"><?php echo htmlspecialchars($rol['nombre']); ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit" style="background-color: #28a745;">Crear Usuario</button>
        </form>

        <hr>
        
        <h3>Usuarios Existentes</h3>
        <?php if (empty($usuarios)): ?>
            <p>No hay usuarios registrados.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre</th>
                        <th>Correo</th>
                        <th>Rol</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($usuarios as $user): ?>
                        <tr>
                            <td><?php echo $user['idUsuario']; ?></td>
                            <td><?php echo htmlspecialchars($user['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($user['correo']); ?></td>
                            <td><strong><?php echo htmlspecialchars($user['rol']); ?></strong></td>
                            <td>
                                <a href="admin.php?editar=<?php echo $user['idUsuario']; ?>" style="background-color: #ffc107; color: black; padding: 8px 10px; border-radius: 4px; text-decoration: none; margin-right: 5px;">Editar</a>
                                
                                <form method="POST" style="display:inline;" onsubmit="return confirm('¿Está seguro de eliminar al usuario <?php echo htmlspecialchars($user['nombre']); ?>? Esta acción es irreversible.');">
                                    <input type="hidden" name="accion" value="eliminar">
                                    <input type="hidden" name="idUsuario" value="<?php echo $user['idUsuario']; ?>">
                                    <button type="submit" style="background-color: #dc3545;" <?php echo ($user['idUsuario'] == $idUsuarioActual) ? 'disabled title="No puedes eliminar tu propia cuenta"' : ''; ?>>Eliminar</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <hr>
        <p><a href="../logout.php">Cerrar Sesión</a></p>
    </div>
</body>
</html>