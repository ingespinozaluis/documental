<?php
// includes/funciones_usuario.php

// Incluir el archivo de conexión si aún no está
require_once __DIR__ . '/../config/db.php';

// =========================================================================
// FUNCIONES DE AUTENTICACIÓN Y REGISTRO (TUS FUNCIONES ORIGINALES)
// =========================================================================

/**
 * Registra un nuevo usuario en la base de datos.
 * Utiliza la columna 'rol' como string.
 */
function registrarUsuario($nombre, $correo, $password, $rol) {
    $conexion = conectarDB();
    
    // 1. Cifrar la contraseña
    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    
    // 2. Preparar la consulta SQL para evitar inyección (Prepared Statements)
    $stmt = $conexion->prepare("INSERT INTO usuario (nombre, correo, password_hash, rol, fechaRegistro) VALUES (?, ?, ?, ?, NOW())"); // Agregando fechaRegistro
    
    if (!$stmt) {
        error_log("Error al preparar registrarUsuario: " . $conexion->error);
        $conexion->close();
        return false;
    }
    
    // 3. Vincular parámetros
    $stmt->bind_param("ssss", $nombre, $correo, $password_hash, $rol);
    
    // 4. Ejecutar la consulta
    $exito = $stmt->execute();

    if (!$exito) {
        error_log("Error al registrar: " . $stmt->error);
    }
    
    $stmt->close();
    $conexion->close();
    return $exito;
}

/**
 * Inicia la sesión de un usuario.
 */
function iniciarSesion($correo, $password) {
    $conexion = conectarDB();
    
    // 1. Buscar al usuario por correo
    $stmt = $conexion->prepare("SELECT idUsuario, nombre, password_hash, rol FROM usuario WHERE correo = ?");
    $stmt->bind_param("s", $correo);
    $stmt->execute();
    $resultado = $stmt->get_result();
    
    if ($resultado->num_rows === 1) {
        $usuario = $resultado->fetch_assoc();
        
        // 2. Verificar la contraseña cifrada
        if (password_verify($password, $usuario['password_hash'])) {
            // 3. La contraseña es correcta: iniciar la sesión
            if (session_status() == PHP_SESSION_NONE) {
                session_start();
            }
            
            $_SESSION['usuario_id'] = $usuario['idUsuario'];
            $_SESSION['usuario_nombre'] = $usuario['nombre'];
            $_SESSION['usuario_rol'] = $usuario['rol'];
            
            $stmt->close();
            $conexion->close();
            return true;
        }
    }
    
    $stmt->close();
    $conexion->close();
    return false; // Credenciales inválidas
}

/**
 * Función para verificar si un usuario tiene un rol específico.
 */
function verificarRol($rol_requerido) {
    // Asegurar que la sesión está iniciada
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['usuario_rol']) || $_SESSION['usuario_rol'] !== $rol_requerido) {
        return false;
    }
    return true;
}


// =========================================================================
// FUNCIONES DE ADMINISTRACIÓN (CRUD) - ADAPTADAS SIN TABLA 'rol'
// =========================================================================

/**
 * Obtiene la lista de roles disponibles (Hardcodeada al no existir la tabla 'rol').
 * Nota: Devuelve un array de objetos simulados para que el HTML de admin.php funcione.
 */
function obtenerRoles() {
    // Definir los roles del sistema como strings
    $roles_nombres = [
        'Administrador',
        'Archivista',
        'Secretaria',
        'Externo',
    ];

    $roles = [];
    foreach ($roles_nombres as $nombre) {
        // Simular la estructura de un resultado de DB
        // Usamos 'nombre' como valor y 'nombre' como etiqueta
        $roles[] = ['nombre' => $nombre]; 
    }
    return $roles;
}

/**
 * Implementación de crearUsuario (llama a registrarUsuario).
 * @param string $rol El rol es un string (e.g., 'Archivista').
 */
function crearUsuario($nombre, $correo, $password, $rol) {
    // Llama a la función registrarUsuario, pasando el rol como STRING
    return registrarUsuario($nombre, $correo, $password, $rol);
}

/**
 * Obtiene todos los usuarios y su rol. 
 * ADAPTADA: Selecciona la columna 'rol' directamente de la tabla 'usuario'.
 */
function obtenerUsuarios() {
    $conexion = conectarDB();
    $usuarios = [];
    
    $sql = "SELECT 
                idUsuario, 
                nombre, 
                correo, 
                rol
            FROM usuario
            ORDER BY nombre";
            
    $resultado = $conexion->query($sql);

    if ($resultado) {
        while ($fila = $resultado->fetch_assoc()) {
            $usuarios[] = $fila;
        }
    }
    $conexion->close();
    return $usuarios;
}


/**
 * Elimina un usuario por su ID.
 */
function eliminarUsuario($idUsuario) {
    $conexion = conectarDB();
    
    // Aquí podrías necesitar eliminar manualmente las solicitudes y el historial 
    // asociados a este usuario si la base de datos no usa CASCADE en las claves foráneas.
    
    $sql = "DELETE FROM usuario WHERE idUsuario = ?";
    $stmt = $conexion->prepare($sql);
    
    if (!$stmt) {
        error_log("Error al preparar eliminarUsuario: " . $conexion->error);
        $conexion->close();
        return false;
    }
    
    $stmt->bind_param("i", $idUsuario);
    $exito = $stmt->execute();
    
    $stmt->close();
    $conexion->close();
    return $exito;
}

/**
 * Obtiene los detalles de un usuario por su ID.
 * Útil para precargar el formulario de edición.
 */
function obtenerUsuarioPorId($idUsuario) {
    $conexion = conectarDB();
    $sql = "SELECT idUsuario, nombre, correo, rol FROM usuario WHERE idUsuario = ?";
    $stmt = $conexion->prepare($sql);
    
    if (!$stmt) {
        error_log("Error al preparar obtenerUsuarioPorId: " . $conexion->error);
        $conexion->close();
        return null;
    }

    $stmt->bind_param("i", $idUsuario);
    $stmt->execute();
    $resultado = $stmt->get_result();
    $usuario = $resultado->fetch_assoc();
    
    $stmt->close();
    $conexion->close();
    return $usuario;
}

/**
 * Actualiza la información de un usuario, incluyendo opcionalmente la contraseña.
 */
function actualizarUsuario($idUsuario, $nombre, $correo, $rol, $password = null) {
    $conexion = conectarDB();
    $params = [$nombre, $correo, $rol];
    $types = "sss"; // Tipos iniciales: nombre (s), correo (s), rol (s)
    
    $sql = "UPDATE usuario SET nombre = ?, correo = ?, rol = ?";
    
    if ($password && strlen($password) > 0) {
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $sql .= ", password_hash = ?";
        $params[] = $password_hash;
        $types .= "s";
    }
    
    $sql .= " WHERE idUsuario = ?";
    $params[] = $idUsuario;
    $types .= "i"; // Tipo final: idUsuario (i)

    $stmt = $conexion->prepare($sql);
    
    if (!$stmt) {
        error_log("Error al preparar actualizarUsuario: " . $conexion->error);
        $conexion->close();
        return false;
    }
    
    // Vinculación dinámica de parámetros
    array_unshift($params, $types);
    call_user_func_array([$stmt, 'bind_param'], $params);

    $exito = $stmt->execute();
    
    if (!$exito) {
        error_log("Error al ejecutar actualizarUsuario: " . $stmt->error);
    }

    $stmt->close();
    $conexion->close();
    return $exito;
}