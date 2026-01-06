<?php
// config/db.php

// Constantes de Conexión
define('DB_HOST', 'localhost');
define('DB_USER', 'denver'); // Reemplaza con tu usuario de MySQL
define('DB_PASS', '$852456$');     // Reemplaza con tu contraseña de MySQL
define('DB_NAME', 'gestion_documental_db');

// Función para establecer la conexión
function conectarDB() {
    $conexion = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

    if ($conexion->connect_error) {
        die("Error de conexión a la base de datos: " . $conexion->connect_error);
    }
    
    // Establecer el juego de caracteres a utf8
    $conexion->set_charset("utf8");

    return $conexion;
}