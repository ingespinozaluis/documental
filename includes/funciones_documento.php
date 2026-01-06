<?php
// includes/funciones_documento.php
// Contiene funciones para interactuar con las tablas documento, categoria, solicitud e historial.

require_once __DIR__ . '/../config/db.php'; 

// =========================================================================
// FUNCIONES DE HISTORIAL (AUDITORÍA)
// =========================================================================

/**
 * Registra una acción en la tabla Historial.
 * Nota: El ID de Documento ahora puede ser opcional (NULL) si la acción no lo requiere.
 */
function registrarHistorial($idUsuario, $accion, $idDocumento = null) {
    $conexion = conectarDB();
    $fecha = date("Y-m-d H:i:s"); // Usamos DATETIME

    $stmt = $conexion->prepare("INSERT INTO historial (idUsuario, idDocumento, accion, fecha) VALUES (?, ?, ?, ?)");
    
    if (!$stmt) {
        error_log("Error al preparar la consulta de historial: " . $conexion->error);
        $conexion->close();
        return false;
    }

    // El parámetro idDocumento puede ser NULL en la base de datos, 
    // pero PHP requiere manejarlo como 'i' y asegurar que sea NULL si no se pasa.
    $idDocumentoParam = is_null($idDocumento) ? null : (int)$idDocumento;
    
    // Usamos bind_param con 'isss' y el idDocumento como el valor numérico (i)
    // El tipo debe reflejar la estructura de la base de datos (idUsuario:i, idDocumento:i, accion:s, fecha:s)
    if (is_null($idDocumentoParam)) {
        // Adaptación simplificada si la columna acepta NULL
        $sql = "INSERT INTO historial (idUsuario, accion, fecha) VALUES (?, ?, ?)";
        $stmt = $conexion->prepare($sql);
        if (!$stmt) {
            error_log("Error al preparar la consulta de historial (NULL): " . $conexion->error);
            $conexion->close();
            return false;
        }
        $stmt->bind_param("iss", $idUsuario, $accion, $fecha);
    } else {
        $stmt->bind_param("iiss", $idUsuario, $idDocumentoParam, $accion, $fecha);
    }
    
    $exito = $stmt->execute();
    
    if (!$exito) {
        error_log("Error al ejecutar el registro de historial: " . $stmt->error);
    }
    
    $stmt->close();
    $conexion->close();
    return $exito;
}


/**
 * Obtiene el historial completo de actividades.
 */
function obtenerHistorialCompleto() {
    $conexion = conectarDB();
    $historial = [];
    
    $sql = "SELECT 
                h.idHistorial, 
                h.accion, 
                h.fecha, 
                u.nombre AS usuario, 
                d.titulo AS documento_titulo
            FROM historial h
            JOIN usuario u ON h.idUsuario = u.idUsuario
            LEFT JOIN documento d ON h.idDocumento = d.idDocumento
            ORDER BY h.fecha DESC";
            
    $resultado = $conexion->query($sql);

    if ($resultado) {
        while ($fila = $resultado->fetch_assoc()) {
            $historial[] = $fila;
        }
    }
    $conexion->close();
    return $historial;
}


// =========================================================================
// FUNCIONES DE DOCUMENTOS Y CATEGORÍAS (CON GESTIÓN DE ESTADO)
// =========================================================================

/**
 * Obtiene todas las categorías para el formulario de clasificación.
 */
function obtenerCategorias() {
    $conexion = conectarDB();
    $sql = "SELECT idCategoria, nombre FROM categoria ORDER BY nombre";
    $resultado = $conexion->query($sql);
    
    $categorias = [];
    if ($resultado && $resultado->num_rows > 0) {
        while($fila = $resultado->fetch_assoc()) {
            $categorias[] = $fila;
        }
    }
    $conexion->close();
    return $categorias;
}


/**
 * Registra los metadatos del documento subido en la base de datos.
 * @param string $titulo
 * @param int $idUsuario
 * @param int $idCategoria
 * @param string $nombreUnico Nombre único del archivo en disco.
 * @param string $estado El estado del documento ('Pendiente', 'Aprobado', etc.).
 * @return bool
 */
function subirDocumentoDB($titulo, $idUsuario, $idCategoria, $nombreUnico, $estado = 'Aprobado') {
    $conexion = conectarDB();
    
    // La fechaSubida usa la función MySQL NOW()
    $sql = "INSERT INTO documento 
            (titulo, idUsuario, idCategoria, rutaArchivo, estado, fechaSubida) 
            VALUES (?, ?, ?, ?, ?, NOW())";
            
    $stmt = $conexion->prepare($sql);
    
    if (!$stmt) {
        error_log("Error al preparar subirDocumentoDB: " . $conexion->error);
        $conexion->close();
        return false;
    }
    
    // El orden de los tipos es: s (titulo), i (idUsuario), i (idCategoria), s (rutaArchivo), s (estado)
    $stmt->bind_param("siiss", $titulo, $idUsuario, $idCategoria, $nombreUnico, $estado);
    
    $exito = $stmt->execute();

    if ($exito) {
        $idDocumento = $conexion->insert_id;
        $accion = "Documento '{$titulo}' subido. Estado inicial: " . $estado;
        registrarHistorial($idUsuario, $accion, $idDocumento);
    } else {
        error_log("Error al ejecutar subirDocumentoDB: " . $stmt->error);
    }

    $stmt->close();
    $conexion->close();
    return $exito;
}

/**
 * Obtiene documentos con estado 'Pendiente' para ser revisados por el Administrador.
 */
function obtenerDocumentosPendientes() {
    $conexion = conectarDB();
    $documentos = [];
    
    $sql = "SELECT 
                d.idDocumento, d.titulo, d.fechaSubida, d.estado, c.nombre AS categoria, u.nombre AS usuarioSubio
            FROM documento d
            JOIN categoria c ON d.idCategoria = c.idCategoria
            JOIN usuario u ON d.idUsuario = u.idUsuario
            WHERE d.estado = 'Pendiente'
            ORDER BY d.fechaSubida ASC";
            
    $resultado = $conexion->query($sql);

    if ($resultado) {
        while ($fila = $resultado->fetch_assoc()) {
            $documentos[] = $fila;
        }
    } else {
        error_log("Error al obtener documentos pendientes: " . $conexion->error);
    }

    $conexion->close();
    return $documentos;
}

/**
 * Marca un documento como Aprobado y registra la acción.
 * @param int $idDocumento
 * @param int $idAdmin ID del usuario (Admin) que aprueba.
 * @return bool
 */
function aprobarDocumento($idDocumento, $idAdmin) {
    $conexion = conectarDB();
    $sql = "UPDATE documento SET estado = 'Aprobado' WHERE idDocumento = ? AND estado = 'Pendiente'";
    
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param("i", $idDocumento);
    $exito = $stmt->execute();
    
    if ($exito && $stmt->affected_rows > 0) {
        $accion = "Documento ID {$idDocumento} aprobado por Administrador.";
        registrarHistorial($idAdmin, $accion, $idDocumento);
    }

    $stmt->close();
    $conexion->close();
    return $exito;
}


/**
 * Busca y lista documentos para su visualización o solicitud.
 * IMPORTANTE: Solo muestra documentos con estado 'Aprobado'.
 */
function buscarDocumentos($termino, $rol) {
    $conexion = conectarDB();
    $documentos = [];
    $where = "WHERE d.titulo LIKE ? AND d.estado = 'Aprobado'"; // Solo documentos aprobados son visibles
    $param_type = "s";
    $param_value = "%" . $termino . "%";
    
    $sql = "SELECT 
                d.idDocumento, 
                d.titulo, 
                d.fechaSubida, 
                d.estado, 
                d.rutaArchivo,
                c.nombre AS categoria, 
                u.nombre AS usuarioSubio
            FROM documento d
            JOIN categoria c ON d.idCategoria = c.idCategoria
            JOIN usuario u ON d.idUsuario = u.idUsuario
            " . $where;
    
    $stmt = $conexion->prepare($sql);
    
    if (!$stmt) {
        error_log("Error al preparar buscarDocumentos: " . $conexion->error);
        $conexion->close();
        return $documentos;
    }
    
    $stmt->bind_param($param_type, $param_value);
    $stmt->execute();
    $resultado = $stmt->get_result();

    while ($fila = $resultado->fetch_assoc()) {
        $documentos[] = $fila;
    }

    $stmt->close();
    $conexion->close();
    return $documentos;
}


// =========================================================================
// FUNCIONES DE SOLICITUDES
// =========================================================================

/**
 * Registra una solicitud de documento. (Caso de Uso: Solicitar Documento)
 */
function solicitarDocumento($idDocumento, $idUsuario) {
    $conexion = conectarDB();
    $fecha = date("Y-m-d");
    $estado = 'Pendiente';
    
    // Verificar si ya existe una solicitud pendiente
    $stmt_check = $conexion->prepare("SELECT idSolicitud FROM solicitud WHERE idDocumento = ? AND idUsuario = ? AND estado = 'Pendiente'");
    $stmt_check->bind_param("ii", $idDocumento, $idUsuario);
    $stmt_check->execute();
    $stmt_check->store_result();
    
    if ($stmt_check->num_rows > 0) {
        $stmt_check->close();
        $conexion->close();
        return false; // Ya existe una solicitud pendiente
    }
    $stmt_check->close();
    
    $stmt = $conexion->prepare("INSERT INTO solicitud (idDocumento, idUsuario, fecha, estado) VALUES (?, ?, ?, ?)");
    
    if (!$stmt) {
        error_log("Error al preparar solicitarDocumento: " . $conexion->error);
        $conexion->close();
        return false;
    }

    $stmt->bind_param("iiss", $idDocumento, $idUsuario, $fecha, $estado);
    
    $exito = $stmt->execute();
    
    if ($exito) {
        $accion = "Solicitud de acceso a documento ID: {$idDocumento} registrada.";
        registrarHistorial($idUsuario, $accion, $idDocumento);
    }
    
    $stmt->close();
    $conexion->close();
    return $exito;
}

/**
 * Lista todas las solicitudes pendientes para que el Archivista las gestione.
 */
function obtenerSolicitudesPendientes() {
    $conexion = conectarDB();
    $solicitudes = [];
    
    $sql = "SELECT 
                s.idSolicitud, s.idDocumento, s.fecha, s.estado, 
                u.nombre AS solicitante, 
                d.titulo AS documento
            FROM solicitud s
            JOIN usuario u ON s.idUsuario = u.idUsuario
            JOIN documento d ON s.idDocumento = d.idDocumento
            WHERE s.estado = 'Pendiente'
            ORDER BY s.fecha ASC";
            
    $resultado = $conexion->query($sql);

    if ($resultado) {
        while ($fila = $resultado->fetch_assoc()) {
            $solicitudes[] = $fila;
        }
    }
    $conexion->close();
    return $solicitudes;
}

/**
 * Aprueba o rechaza una solicitud y registra la acción en el historial. 
 */
function gestionarSolicitud($idSolicitud, $nuevoEstado, $idUsuarioGestor) { 
    $conexion = conectarDB();
    
    // 1. Obtener el ID del documento antes de la actualización
    $stmt_fetch = $conexion->prepare("SELECT idDocumento FROM solicitud WHERE idSolicitud = ?");
    $stmt_fetch->bind_param("i", $idSolicitud);
    $stmt_fetch->execute();
    $resultado = $stmt_fetch->get_result();
    $solicitud_data = $resultado->fetch_assoc();
    $stmt_fetch->close();

    $idDocumento = $solicitud_data['idDocumento'] ?? null;
    
    if (!$idDocumento) {
        $conexion->close();
        return false;
    }
    
    // 2. Actualizar el estado de la solicitud
    $stmt = $conexion->prepare("UPDATE solicitud SET estado = ? WHERE idSolicitud = ?");
    $stmt->bind_param("si", $nuevoEstado, $idSolicitud);
    
    $exito = $stmt->execute();
    
    // 3. Registrar en Historial si la actualización fue exitosa
    if ($exito) {
        $accion = "Solicitud ID: {$idSolicitud} fue '{$nuevoEstado}' por el Archivista.";
        registrarHistorial($idUsuarioGestor, $accion, $idDocumento);
    }

    $stmt->close();
    $conexion->close();
    return $exito;
}

/**
 * Obtiene todas las solicitudes enviadas por un usuario específico (Secretaria/Externo).
 */
function obtenerSolicitudesPorUsuario($idUsuario) {
    $conexion = conectarDB();
    $solicitudes = [];
    
    $sql = "SELECT 
                s.idSolicitud, 
                s.fecha, 
                s.estado, 
                d.titulo AS documento_titulo, 
                d.rutaArchivo 
            FROM solicitud s
            JOIN documento d ON s.idDocumento = d.idDocumento
            WHERE s.idUsuario = ?
            ORDER BY s.fecha DESC";
            
    $stmt = $conexion->prepare($sql);
    
    if (!$stmt) {
        error_log("Error al preparar obtenerSolicitudesPorUsuario: " . $conexion->error);
        $conexion->close();
        return $solicitudes;
    }
    
    $stmt->bind_param("i", $idUsuario);
    $stmt->execute();
    $resultado = $stmt->get_result();

    while ($fila = $resultado->fetch_assoc()) {
        $solicitudes[] = $fila;
    }

    $stmt->close();
    $conexion->close();
    return $solicitudes;
}

/**
 * Obtiene los detalles de una solicitud específica por su ID.
 * Usada por el script de descarga segura.
 */
function obtenerDetallesSolicitud($idSolicitud) {
    $conexion = conectarDB();
    $solicitud = null;
    
    $sql = "SELECT 
                s.idSolicitud, 
                s.idUsuario, 
                s.estado, 
                d.rutaArchivo,
                d.titulo
            FROM solicitud s
            JOIN documento d ON s.idDocumento = d.idDocumento
            WHERE s.idSolicitud = ?";
            
    $stmt = $conexion->prepare($sql);
    
    if (!$stmt) {
        error_log("Error al preparar obtenerDetallesSolicitud: " . $conexion->error);
        $conexion->close();
        return null;
    }
    
    $stmt->bind_param("i", $idSolicitud);
    $stmt->execute();
    $resultado = $stmt->get_result();

    if ($resultado->num_rows > 0) {
        $solicitud = $resultado->fetch_assoc();
    }

    $stmt->close();
    $conexion->close();
    return $solicitud;
}