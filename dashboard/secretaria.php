<?php
// dashboard/secretaria.php
session_start();
require_once __DIR__ . '/../includes/funciones_usuario.php';

// --- CONTROL DE ACCESO ---
// Este panel es EXCLUSIVO para la Secretaria.
if (!verificarRol('Secretaria')) {
    // Si no es Secretaria, redirige al login (o al dashboard genérico si existiera)
    header('Location: ../login.php');
    exit();
}

// --------------------------------------------------------
// REDIRECCIÓN A LA FUNCIONALIDAD PRINCIPAL
// --------------------------------------------------------

// Redirigir a buscar.php, donde está toda la funcionalidad de búsqueda y solicitud.
header('Location: buscar.php');
exit();
?>