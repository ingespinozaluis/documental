<?php
// dashboard/archivista.php
session_start();
require_once __DIR__ . '/../includes/funciones_usuario.php';
require_once __DIR__ . '/../includes/funciones_documento.php';

// --- CONTROL DE ACCESO ---
if (!verificarRol('Archivista')) {
    header('Location: ../login.php');
    exit();
}

$idUsuarioActual = $_SESSION['usuario_id'];
$mensaje = '';
$categorias = obtenerCategorias();

// --- LÓGICA DE MANEJO POST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // --- LÓGICA DE GESTIÓN DE SOLICITUDES (Archivista aprueba/rechaza solicitudes de acceso) ---
    if (isset($_POST['accion']) && $_POST['accion'] === 'gestionar_solicitud') {
        $idSolicitud = $_POST['idSolicitud'] ?? 0;
        $nuevoEstado = $_POST['estado'] ?? '';

        if (gestionarSolicitud($idSolicitud, $nuevoEstado, $idUsuarioActual)) {
            $mensaje = "<p class='success'>✅ Solicitud {$idSolicitud} marcada como '{$nuevoEstado}' con éxito.</p>";
        } else {
            $mensaje = "<p class='error'>❌ Error al gestionar la solicitud o ID no válido.</p>";
        }
    }
    
    // --- LÓGICA DE SUBIDA DE DOCUMENTOS (Archivista sube, pero el estado es 'Pendiente') ---
    if (isset($_POST['accion']) && $_POST['accion'] === 'subir_documento') {
        $titulo = $_POST['titulo'] ?? '';
        $idCategoria = $_POST['idCategoria'] ?? 0;
        
        // Manejo del archivo
        if (isset($_FILES['archivo']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
            $archivoTmp = $_FILES['archivo']['tmp_name'];
            $nombreOriginal = basename($_FILES['archivo']['name']);
            $extension = pathinfo($nombreOriginal, PATHINFO_EXTENSION);
            
            // Generar un nombre único para evitar colisiones
            $nombreUnico = uniqid('doc_', true) . '.' . $extension;
            $rutaDestino = '../uploads/' . $nombreUnico;
            
            // Mover el archivo subido a la carpeta de uploads
            if (move_uploaded_file($archivoTmp, $rutaDestino)) {
                
                // IMPORTANTE: El estado inicial ahora es 'Pendiente' para que el Admin lo apruebe.
                if (subirDocumentoDB($titulo, $idUsuarioActual, $idCategoria, $nombreUnico, 'Pendiente')) {
                    $mensaje = "<p class='success'>✅ Documento '{$titulo}' subido y clasificado con éxito. (Estado: Pendiente de Aprobación por Administración).</p>";
                } else {
                    $mensaje = "<p class='error'>❌ El archivo se subió, pero falló el registro en la base de datos.</p>";
                    // Limpieza: Considerar borrar el archivo físico si falla la DB
                    unlink($rutaDestino);
                }
            } else {
                $mensaje = "<p class='error'>❌ Error al mover el archivo subido al directorio de destino.</p>";
            }
        } else {
            $mensaje = "<p class='error'>❌ Error en la subida del archivo o no se seleccionó ninguno.</p>";
        }
    }
}

// --- LÓGICA DE BÚSQUEDA (GET) ---
$documentos = [];
$terminoBusqueda = $_GET['termino'] ?? '';

if (!empty($terminoBusqueda)) {
    // El Archivista busca entre los documentos APROBADOS
    $documentos = buscarDocumentos($terminoBusqueda, 'Archivista');
}

// Cargar solicitudes pendientes (para la gestión)
$solicitudes = obtenerSolicitudesPendientes();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Panel de Archivista</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f9; color: #333; }
        .container { max-width: 1100px; margin: 30px auto; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1, h2 { border-bottom: 2px solid #eee; padding-bottom: 10px; margin-top: 20px; }
        .success { color: #155724; background-color: #d4edda; border: 1px solid #c3e6cb; padding: 10px; border-radius: 4px; }
        .error { color: #721c24; background-color: #f8d7da; border: 1px solid #f5c6cb; padding: 10px; border-radius: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background-color: #007bff; color: white; }
        .form-upload input, .form-upload select, .form-upload button { margin-right: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Panel de Archivista 🗄️</h1>
        <p>Usuario: <strong><?php echo htmlspecialchars($_SESSION['usuario_nombre']); ?></strong></p>
        
        <?php echo $mensaje; ?>

        <h2>⬆️ Subir y Clasificar Nuevo Documento</h2>
        <form method="POST" action="archivista.php" enctype="multipart/form-data" class="form-upload">
            <input type="hidden" name="accion" value="subir_documento">
            
            <input type="text" name="titulo" placeholder="Título del Documento" required>
            
            <input type="file" name="archivo" required>
            
            <select name="idCategoria" required>
                <option value="">Seleccione Categoría</option>
                <?php foreach ($categorias as $cat): ?>
                    <option value="<?php echo $cat['idCategoria']; ?>"><?php echo htmlspecialchars($cat['nombre']); ?></option>
                <?php endforeach; ?>
            </select>
            
            <button type="submit" style="background-color: #28a745;">Clasificar y Subir (Pendiente)</button>
        </form>

        <hr>
        
        <h2>🔎 Buscar Documentos Aprobados</h2>
        <form method="GET" action="archivista.php" style="margin-bottom: 20px;">
            <input type="text" name="termino" placeholder="Buscar por título..." value="<?php echo htmlspecialchars($terminoBusqueda); ?>" required style="padding: 8px; width: 300px;">
            <button type="submit" style="background-color: #6c757d;">Buscar</button>
        </form>

        <?php if (!empty($documentos)): ?>
            <h3>Resultados de Búsqueda (<?php echo count($documentos); ?> encontrados)</h3>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Título</th>
                        <th>Categoría</th>
                        <th>Fecha Subida</th>
                        <th>Estado</th>
                        <th>Acceso Directo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $ruta_base_archivos = '../uploads/';
                    foreach ($documentos as $doc): ?>
                        <tr>
                            <td><?php echo $doc['idDocumento']; ?></td>
                            <td><?php echo htmlspecialchars($doc['titulo']); ?></td>
                            <td><?php echo htmlspecialchars($doc['categoria']); ?></td>
                            <td><?php echo htmlspecialchars($doc['fechaSubida']); ?></td>
                            <td><strong><?php echo htmlspecialchars($doc['estado']); ?></strong></td>
                            <td>
                                <a href="<?php echo $ruta_base_archivos . htmlspecialchars($doc['rutaArchivo']); ?>" target="_blank" style="color:#007bff;">Ver Archivo</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php elseif (!empty($terminoBusqueda)): ?>
            <p>No se encontraron documentos que coincidan con la búsqueda.</p>
        <?php endif; ?>

        <hr>

        <h2>📬 Solicitudes de Documentos Pendientes de Acceso</h2>
        <?php if (empty($solicitudes)): ?>
            <p>No hay solicitudes pendientes de acceso en este momento.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <th>ID Sol.</th>
                        <th>Documento</th>
                        <th>Solicitante</th>
                        <th>Fecha</th>
                        <th>Acción</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($solicitudes as $sol): ?>
                        <tr>
                            <td><?php echo $sol['idSolicitud']; ?></td>
                            <td><?php echo htmlspecialchars($sol['documento']); ?></td>
                            <td><?php echo htmlspecialchars($sol['solicitante']); ?></td>
                            <td><?php echo htmlspecialchars($sol['fecha']); ?></td>
                            <td>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="accion" value="gestionar_solicitud">
                                    <input type="hidden" name="idSolicitud" value="<?php echo $sol['idSolicitud']; ?>">
                                    
                                    <button type="submit" name="estado" value="Aprobada" style="background-color: green; color: white;">Aprobar</button>
                                    <button type="submit" name="estado" value="Rechazada" style="background-color: red; color: white;">Rechazar</button>
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