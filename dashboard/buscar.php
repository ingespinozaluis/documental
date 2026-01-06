<?php
// dashboard/buscar.php
session_start();
require_once __DIR__ . '/../includes/funciones_usuario.php';
require_once __DIR__ . '/../includes/funciones_documento.php';

// --- CONTROL DE ACCESO ---
// Este panel es accesible para Secretaria y Usuario Externo
$rol_permitido = false;
if (verificarRol('Secretaria') || verificarRol('Usuario Externo')) {
    $rol_permitido = true;
}

if (!$rol_permitido) {
    header('Location: ../login.php');
    exit();
}

$idUsuarioActual = $_SESSION['usuario_id'];
$rol = $_SESSION['usuario_rol'];
$terminoBusqueda = $_GET['termino'] ?? '';
$documentos = [];
$mensaje = '';

// --------------------------------------------------------
// --- LÓGICA DEL CASO DE USO 2: Solicitar Documento ---
// --------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['solicitar_doc'])) {
    
    $idDocumento = $_POST['idDocumento'] ?? 0;
    
    if ($idDocumento > 0) {
        if (solicitarDocumento($idDocumento, $idUsuarioActual)) {
            $mensaje = "<p class='success'>✅ Solicitud de documento ID: {$idDocumento} enviada. Pendiente de aprobación.</p>";
        } else {
            $mensaje = "<p class='error'>❌ Error al enviar la solicitud o ya existe una solicitud pendiente para este documento.</p>";
        }
    }
}

// --------------------------------------------------------
// --- LÓGICA DEL CASO DE USO 1: Buscar Documento ---
// Se ejecuta después de la lógica POST para actualizar resultados si es necesario.
// --------------------------------------------------------
if (!empty($terminoBusqueda)) {
    // La función buscarDocumentos aplica la lógica de búsqueda.
    $documentos = buscarDocumentos($terminoBusqueda, $rol);
}

// Recargar las solicitudes después de una posible acción POST
$mis_solicitudes = obtenerSolicitudesPorUsuario($idUsuarioActual); 
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Búsqueda de Documentos (<?php echo htmlspecialchars($rol); ?>)</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f9; color: #333; }
        .container { max-width: 900px; margin: 30px auto; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1, h2 { border-bottom: 2px solid #eee; padding-bottom: 10px; margin-top: 20px; }
        .success { color: #155724; background-color: #d4edda; border: 1px solid #c3e6cb; padding: 10px; border-radius: 4px; }
        .error { color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background-color: #e9ecef; }
        form input[type="text"] { padding: 10px; margin-bottom: 10px; width: 60%; box-sizing: border-box; border: 1px solid #ccc; border-radius: 4px; display: inline; }
        button { padding: 10px 15px; border: none; cursor: pointer; border-radius: 4px; background-color: #007bff; color: white; }
        .btn-solicitar { background-color: #ffc107; color: #333; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Búsqueda de Documentos</h1>
        <p>Rol: <strong><?php echo htmlspecialchars($rol); ?></strong> | Usuario: <strong><?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?></strong></p>

        <h2>🔎 Iniciar Búsqueda</h2>
        <?php echo $mensaje; ?>

        <form method="GET" action="buscar.php">
            <input type="text" name="termino" placeholder="Buscar por título..." value="<?php echo htmlspecialchars($terminoBusqueda); ?>" required>
            <button type="submit">Buscar</button>
        </form>
        
        <?php if (!empty($documentos)): ?>
            <h3>Resultados de Búsqueda</h3>
            <table>
                <thead>
                    <tr>
                        <th>Título</th>
                        <th>Categoría</th>
                        <th>Subido por</th>
                        <th>Estado</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($documentos as $doc): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($doc['titulo']); ?></td>
                            <td><?php echo htmlspecialchars($doc['categoria']); ?></td>
                            <td><?php echo htmlspecialchars($doc['usuarioSubio']); ?></td>
                            <td><?php echo htmlspecialchars($doc['estado']); ?></td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="idDocumento" value="<?php echo $doc['idDocumento']; ?>">
                                    <input type="hidden" name="solicitar_doc" value="1">
                                    <button type="submit" class="btn-solicitar">Solicitar Acceso</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php elseif (!empty($terminoBusqueda)): ?>
            <p>No se encontraron documentos que coincidan con la búsqueda.</p>
        <?php else: ?>
            <p>Ingrese un término de búsqueda para encontrar documentos.</p>
        <?php endif; ?>
        
        <hr>

        <h2>📥 Mis Solicitudes de Documentos</h2>
        
        <?php if (empty($mis_solicitudes)): ?>
            <p>Aún no has enviado ninguna solicitud de documento.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID Solicitud</th>
                        <th>Documento Solicitado</th>
                        <th>Fecha de Solicitud</th>
                        <th>Estado</th>
                        <th>Acceso al Archivo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    foreach ($mis_solicitudes as $sol): 
                    ?>
                        <tr>
                            <td><?php echo $sol['idSolicitud']; ?></td>
                            <td><?php echo htmlspecialchars($sol['documento_titulo']); ?></td>
                            <td><?php echo htmlspecialchars($sol['fecha']); ?></td>
                            <td>
                                <strong>
                                    <?php 
                                    echo htmlspecialchars($sol['estado']); 
                                    $color = ($sol['estado'] == 'Aprobada') ? 'green' : (($sol['estado'] == 'Rechazada') ? 'red' : '#ff9800');
                                    echo "<span style='color: {$color}; margin-left: 5px;'>●</span>";
                                    ?>
                                </strong>
                            </td>
                            <td>
                                <?php if ($sol['estado'] === 'Aprobada'): ?>
                                    <a href="../descargar.php?id=<?php echo $sol['idSolicitud']; ?>" 
                                       style="color: #007bff; text-decoration: none; font-weight: bold;">
                                        Descargar Archivo Seguro
                                    </a>
                                <?php elseif ($sol['estado'] === 'Rechazada'): ?>
                                    Acceso denegado.
                                <?php else: ?>
                                    Pendiente de Revisión
                                <?php endif; ?>
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