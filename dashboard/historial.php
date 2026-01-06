<?php
// dashboard/historial.php
session_start();
require_once __DIR__ . '/../includes/funciones_usuario.php';
require_once __DIR__ . '/../includes/funciones_documento.php'; // Incluye la función obtenerHistorialCompleto

// --- CONTROL DE ACCESO ---
// Panel solo para Administrador y Archivista
$rol_permitido = false;
if (verificarRol('Administrador') || verificarRol('Archivista')) {
    $rol_permitido = true;
}

if (!$rol_permitido) {
    header('Location: ../login.php');
    exit();
}

$historial = obtenerHistorialCompleto();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Visualización de Historial de Actividad</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f9; color: #333; }
        .container { max-width: 1200px; margin: 30px auto; padding: 20px; background: white; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        h1 { border-bottom: 2px solid #eee; padding-bottom: 10px; margin-top: 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background-color: #007bff; color: white; }
        tr:nth-child(even) { background-color: #f2f2f2; }
        .action { font-style: italic; color: #555; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📜 Registro de Historial de Actividad</h1>
        <p>Mostrando todas las acciones registradas en el sistema por **<?php echo htmlspecialchars($_SESSION['usuario_rol']); ?>**.</p>
        
        <table>
            <thead>
                <tr>
                    <th>ID Acción</th>
                    <th>Fecha y Hora</th>
                    <th>Usuario</th>
                    <th>Documento Afectado</th>
                    <th>Detalle de la Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($historial)): ?>
                    <?php foreach ($historial as $registro): ?>
                        <tr>
                            <td><?php echo $registro['idHistorial']; ?></td>
                            <td><?php echo htmlspecialchars($registro['fecha']); ?></td>
                            <td><strong><?php echo htmlspecialchars($registro['usuario']); ?></strong></td>
                            <td><?php echo htmlspecialchars($registro['documento_titulo'] ?? 'N/A'); ?></td>
                            <td class="action"><?php echo htmlspecialchars($registro['accion']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5">No se ha registrado ninguna actividad en el historial.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>

        <p style="margin-top: 20px;"><a href="../dashboard/dashboard.php">Volver al Dashboard</a> | <a href="../logout.php">Cerrar Sesión</a></p>
    </div>
</body>
</html>