<?php
// registrar_admin.php
require_once 'includes/funciones_usuario.php';

// --- Datos del Usuario Administrador ---
$nombre_admin = "Super Administrador";
$correo_admin = "admin@sistema.com";
$password_admin = "AdminSeguro123"; // ¡Usa una contraseña fuerte!
$rol_admin = "Administrador";

echo "Intentando registrar usuario Administrador...\n";

if (registrarUsuario($nombre_admin, $correo_admin, $password_admin, $rol_admin)) {
    echo "✅ Usuario Administrador registrado con éxito!\n";
    echo "Detalles:\n";
    echo "- Nombre: " . $nombre_admin . "\n";
    echo "- Correo: " . $correo_admin . "\n";
    echo "- Contraseña: " . $password_admin . "\n";
    echo "- Rol: " . $rol_admin . "\n";
} else {
    echo "❌ Error al registrar el usuario Administrador. Es posible que el correo ya esté en uso o haya un problema de conexión.\n";
}

// Después de ejecutar, puedes borrar este archivo.
?>