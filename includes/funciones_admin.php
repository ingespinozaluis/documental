<?php
// includes/funciones_admin.php
// Contiene funciones para la gestión de usuarios por parte del Administrador.

require_once __DIR__ . '/../config/db.php'; 
require_once __DIR__ . '/funciones_usuario.php'; // Necesario para acceder a $_SESSION

/**
 * Obtiene la lista completa de usuarios del sistema.
 */
function obtenerUsuarios() {
    $conexion = conectarDB();
    $usuarios = [];
    $sql = "SELECT idUsuario, nombre, correo, rol FROM usuario ORDER BY rol, nombre";
    $resultado = $conexion->query($sql);
    
    if ($resultado && $resultado->num_rows > 0) {
        while ($fila = $resultado->fetch_assoc()) {
            $usuarios[] = $fila;
        }
    }
    $conexion->close();
    return $usuarios;
}

/**
 * Obtiene la información de un usuario específico por su ID.
 */
function obtenerUsuarioPorId($idUsuario) {
    $conexion = conectarDB();
    $usuario = null;
    
    $stmt = $conexion->prepare("SELECT idUsuario, nombre, correo, rol FROM usuario WHERE idUsuario = ?");
    
    if (!$stmt) {
        error_log("Error al preparar obtenerUsuarioPorId: " . $conexion->error);
        $conexion->close();
        return null;
    }
    
    $stmt->bind_param("i", $idUsuario);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    if ($resultado->num_rows === 1) {
        $usuario = $resultado->fetch_assoc();
    }
    
    $stmt->close();
    $conexion->close();
    return $usuario;
}

/**
 * Registra o modifica un usuario existente (CRUD: Create/Update).
 * Si idUsuario es 0 o nulo, es una inserción.
 */
function guardarUsuario($idUsuario, $nombre, $correo, $rol, $password = null) {
    $conexion = conectarDB();
    $exito = false;
    $stmt = null;
    
    // 1. Inserción (Nuevo Usuario)
    if (empty($idUsuario)) {
        if (empty($password)) {
            $conexion->close();
            return false; // Contraseña obligatoria para nuevo usuario
        }
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $conexion->prepare("INSERT INTO usuario (nombre, correo, rol, password_hash) VALUES (?, ?, ?, ?)");
        if ($stmt) {
            $stmt->bind_param("ssss", $nombre, $correo, $rol, $password_hash);
        }
        
    } 
    // 2. Actualización (Usuario Existente)
    else {
        if (!empty($password)) {
            // Actualizar contraseña y otros campos
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conexion->prepare("UPDATE usuario SET nombre=?, correo=?, rol=?, password_hash=? WHERE idUsuario=?");
            if ($stmt) {
                $stmt->bind_param("ssssi", $nombre, $correo, $rol, $password_hash, $idUsuario);
            }
        } else {
            // Actualizar solo nombre, correo y rol
            $stmt = $conexion->prepare("UPDATE usuario SET nombre=?, correo=?, rol=? WHERE idUsuario=?");
            if ($stmt) {
                $stmt->bind_param("sssi", $nombre, $correo, $rol, $idUsuario);
            }
        }
    }
    
    if ($stmt) {
        $exito = $stmt->execute();
        if (!$exito) {
            error_log("Error al ejecutar guardarUsuario: " . $stmt->error);
        }
        $stmt->close();
    } else {
        error_log("Error al preparar guardarUsuario: " . $conexion->error);
    }
    
    $conexion->close();
    return $exito;
}

/**
 * Elimina un usuario (CRUD: Delete).
 */
function eliminarUsuario($idUsuario) {
    $conexion = conectarDB();
    
    // El usuario no puede eliminarse a sí mismo (seguridad básica)
    if ((int)$idUsuario === (int)$_SESSION['usuario_id']) { 
        $conexion->close();
        return false; 
    }

    $stmt = $conexion->prepare("DELETE FROM usuario WHERE idUsuario = ?");
    
    if (!$stmt) {
        error_log("Error al preparar eliminarUsuario: " . $conexion->error);
        $conexion->close();
        return false;
    }
    
    $stmt->bind_param("i", $idUsuario);
    
    $exito = $stmt->execute();
    
    if (!$exito) {
        error_log("Error al ejecutar eliminarUsuario: " . $stmt->error);
    }
    
    $stmt->close();
    $conexion->close();
    return $exito;
}