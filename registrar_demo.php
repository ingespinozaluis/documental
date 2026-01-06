<?php
// registrar_demo.php
require_once 'includes/funciones_usuario.php';

// --- Datos del Usuario Demo ---
$nombre_demo = "Ana Archivista";
$correo_demo = "ana.archivista@sistema.com";
$password_demo = "password123"; // ¡Usarás esta contraseña para iniciar sesión!
$rol_demo = "Archivista";

echo "Intentando registrar usuario demo...\n";

// La función intentará conectarse, hashear la contraseña e insertar el registro.
if (registrarUsuario($nombre_demo, $correo_demo, $password_demo, $rol_demo)) {
    echo "✅ Usuario demo registrado con éxito!\n";
    echo "Detalles:\n";
    echo "- Nombre: " . $nombre_demo . "\n";
    echo "- Correo: " . $correo_demo . "\n";
    echo "- Contraseña: " . $password_demo . " (¡No almacenar nunca en texto plano!)\n";
    echo "- Rol: " . $rol_demo . "\n";
} else {
    // Esto podría fallar si el correo ya existe (UNIQUE en la tabla)
    echo "❌ Error al registrar el usuario demo. Es posible que el correo ya esté en uso.\n";
}

// Opcional: registrar un Administrador
// registrarUsuario("Pedro Admin", "admin@sistema.com", "admin123", "Administrador"); 

?>