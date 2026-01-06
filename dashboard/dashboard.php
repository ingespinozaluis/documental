<?php
// dashboard/dashboard.php
// Punto de entrada central y controlador de redirección para todos los roles.

session_start();
require_once __DIR__ . '/../includes/funciones_usuario.php';

// 1. Verificar si hay una sesión activa
if (!isset($_SESSION['usuario_id']) || !isset($_SESSION['usuario_rol'])) {
    // Si no hay sesión, lo redirigimos al login
    header('Location: ../login.php');
    exit();
}

$rol = $_SESSION['usuario_rol'];

// 2. Redirigir al panel específico según el rol
switch ($rol) {
    case 'Administrador':
        // El administrador gestiona usuarios
        header('Location: admin.php');
        exit();
        break;

    case 'Archivista':
        // El Archivista sube documentos y gestiona solicitudes
        header('Location: archivista.php');
        exit();
        break;

    case 'Secretaria':
    case 'Usuario Externo':
        // La Secretaria y el Usuario Externo buscan y solicitan documentos
        header('Location: buscar.php');
        exit();
        break;

    default:
        // Si el rol es desconocido o no está contemplado
        header('Location: ../logout.php');
        exit();
        break;
}

// Nota: Si por alguna razón la redirección falla o el rol no coincide,
// el script terminará aquí, lo cual es seguro.
?>