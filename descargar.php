<?php
// descargar.php (En la raíz del proyecto)
session_start();
require_once 'includes/funciones_usuario.php';
require_once 'includes/funciones_documento.php';

// Directorio físico de los archivos subidos
$directorio_archivos = __DIR__ . '/uploads/'; 

// 1. Verificar autenticación
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401);
    die("Acceso no autorizado. Debe iniciar sesión.");
}

// 2. Obtener y validar el ID de la solicitud
$idSolicitud = $_GET['id'] ?? 0;
if (!is_numeric($idSolicitud) || $idSolicitud <= 0) {
    http_response_code(400);
    die("ID de solicitud inválido.");
}

// 3. Obtener los detalles de la solicitud
$solicitud = obtenerDetallesSolicitud($idSolicitud);

if (!$solicitud) {
    http_response_code(404);
    die("Solicitud no encontrada.");
}

// 4. Verificar permisos (ID de Usuario y Estado de Aprobación)
$idUsuarioSesion = (int)$_SESSION['usuario_id'];
$idUsuarioSolicitante = (int)$solicitud['idUsuario'];
$estado = $solicitud['estado'];

// El usuario de la sesión debe ser el mismo que el usuario que hizo la solicitud
// Y el estado de la solicitud debe ser 'Aprobada'
if ($idUsuarioSesion !== $idUsuarioSolicitante || $estado !== 'Aprobada') {
    http_response_code(403);
    die("Permisos insuficientes o solicitud no aprobada.");
}

$rutaRelativa = $solicitud['rutaArchivo'];
$rutaAbsoluta = $directorio_archivos . $rutaRelativa;
$nombreArchivo = basename($rutaRelativa); // Nombre real del archivo

// 5. Verificar si el archivo existe físicamente
if (!file_exists($rutaAbsoluta)) {
    http_response_code(404);
    die("Archivo no encontrado en el servidor.");
}

// 6. Forzar la descarga segura
// *******************************************************************
// NOTA IMPORTANTE: Necesitas agregar al archivo .htaccess (si usas Apache) 
// una regla para denegar el acceso directo a la carpeta 'uploads'.
// Ejemplo en .htaccess dentro de /uploads: Deny from all
// *******************************************************************

header('Content-Description: File Transfer');
header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . basename($solicitud['titulo']) . '-' . $nombreArchivo . '"');
header('Expires: 0');
header('Cache-Control: must-revalidate');
header('Pragma: public');
header('Content-Length: ' . filesize($rutaAbsoluta));

// Limpiar el buffer de salida (importante para evitar archivos corruptos)
ob_clean();
flush();

// Enviar el archivo al navegador
readfile($rutaAbsoluta);

// Registrar la descarga en el historial (opcional, pero recomendable)
registrarHistorial($idUsuarioSesion, null, "Documento: '{$solicitud['titulo']}' descargado mediante Solicitud ID {$idSolicitud}.");

exit;
?>