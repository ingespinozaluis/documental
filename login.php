<?php
// login.php
require_once 'includes/funciones_usuario.php';

$mensaje = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = $_POST['correo'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Suponiendo que la función iniciarSesion inicia la sesión y guarda el rol
    if (iniciarSesion($correo, $password)) { 
        
        // --- CORRECCIÓN APLICADA AQUÍ ---
        // Redirigir siempre al controlador central de dashboards
        header('Location: dashboard/dashboard.php'); 
        // ------------------------------------
        
        exit();
    } else {
        $mensaje = "Credenciales incorrectas o usuario no encontrado.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Iniciar Sesión - Gestión Documental</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f4f9; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; flex-direction: column; }
        h1 { color: #333; }
        form { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        input[type="email"], input[type="password"], button { width: 100%; padding: 10px; margin-bottom: 10px; border: 1px solid #ccc; border-radius: 4px; box-sizing: border-box; }
        button { background-color: #007bff; color: white; border: none; cursor: pointer; }
    </style>
</head>
<body>
    <h1>Iniciar Sesión</h1>
    
    <?php if ($mensaje): ?>
        <p style="color: red;"><?php echo $mensaje; ?></p>
    <?php endif; ?>
    
    <form method="POST" action="login.php">
        <label for="correo">Correo:</label>
        <input type="email" id="correo" name="correo" required><br>
        
        <label for="password">Contraseña:</label>
        <input type="password" id="password" name="password" required><br>
        
        <button type="submit">Acceder</button>
    </form>
</body>
</html>