<?php
class AdminControlador {
    private $db;

    public function __construct($database) {
        $this->db = $database->conectar();
    }
    public function obtenerEstadisticas() {
        try {
            $estadisticas = [];

            // Total reportes
            $sql1 = "SELECT COUNT(*) as total FROM reporte";
            $stmt1 = $this->db->prepare($sql1);
            $stmt1->execute();
            $estadisticas['total_reportes'] = $stmt1->fetchColumn();

            // Total usuarios
            $sql2 = "SELECT COUNT(*) as total FROM usuario";
            $stmt2 = $this->db->prepare($sql2);
            $stmt2->execute();
            $estadisticas['total_usuarios'] = $stmt2->fetchColumn();

            // Reportes por estado
            $sql3 = "SELECT estado, COUNT(*) as cantidad FROM reporte GROUP BY estado";
            $stmt3 = $this->db->prepare($sql3);
            $stmt3->execute();
            $estadisticas['reportes_por_estado'] = $stmt3->fetchAll(PDO::FETCH_ASSOC);

            // Tipos de incidentes más comunes
            $sql4 = "SELECT ti.nombre, COUNT(*) as cantidad
                    FROM reporte r
                    INNER JOIN tipo_incidente ti ON r.id_tipo_incidente = ti.id_tipo_incidente
                    GROUP BY ti.nombre
                    ORDER BY cantidad DESC
                    LIMIT 5";
            $stmt4 = $this->db->prepare($sql4);
            $stmt4->execute();
            $estadisticas['tipos_comunes'] = $stmt4->fetchAll(PDO::FETCH_ASSOC);

            return $estadisticas;

        } catch (Exception $e) {
            error_log("Error obteniendo estadísticas: " . $e->getMessage());
            return [
                'total_reportes' => 0,
                'total_usuarios' => 0,
                'reportes_por_estado' => [],
                'tipos_comunes' => []
            ];
        }
    }

    // Método para obtener usuarios
    public function obtenerUsuarios($limite = 50) {
        try {
            $sql = "SELECT
                        u.id_usuario,
                        p.nombres,
                        p.apellidos,
                        p.telefono,
                        u.correo,
                        r.nombre as rol,
                        r.id_rol,
                        eu.nombre as estado,
                        eu.id_estado,
                        COUNT(re.id_reporte) as total_reportes
                    FROM usuario u
                    INNER JOIN persona p ON u.id_persona = p.id_persona
                    INNER JOIN rol r ON u.id_rol = r.id_rol
                    INNER JOIN estado_usuario eu ON u.id_estado = eu.id_estado
                    LEFT JOIN reporte re ON u.id_usuario = re.id_usuario
                    GROUP BY u.id_usuario, p.nombres, p.apellidos, p.telefono, u.correo, r.nombre, r.id_rol, eu.nombre, eu.id_estado
                    ORDER BY u.id_usuario DESC
                    LIMIT :limite";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("Error obteniendo usuarios: " . $e->getMessage());
            return [];
        }
    }

    // Método para obtener reportes
    public function obtenerReportes($limite = 50) {
        try {
            $sql = "SELECT
                        r.id_reporte,
                        r.descripcion,
                        r.latitud,
                        r.longitud,
                        r.fecha_reporte,
                        r.estado,
                        ti.nombre as tipo_incidente,
                        u.correo,
                        CONCAT(p.nombres, ' ', p.apellidos) as usuario
                    FROM reporte r
                    INNER JOIN tipo_incidente ti ON r.id_tipo_incidente = ti.id_tipo_incidente
                    INNER JOIN usuario u ON r.id_usuario = u.id_usuario
                    INNER JOIN persona p ON u.id_persona = p.id_persona
                    ORDER BY r.fecha_reporte DESC
                    LIMIT :limite";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("Error obteniendo reportes: " . $e->getMessage());
            return [];
        }
    }

    // Método para cambiar estado de usuario
    public function cambiarEstadoUsuario($idUsuario, $nuevoEstado) {
        try {
            // Validar que el nuevo estado existe (1 = Activo, 2 = Inactivo)
            $estadosValidos = [1, 2];
            if (!in_array($nuevoEstado, $estadosValidos)) {
                throw new Exception("Estado no válido");
            }

            $sql = "UPDATE usuario SET id_estado = :estado WHERE id_usuario = :id_usuario";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':estado', $nuevoEstado, PDO::PARAM_INT);
            $stmt->bindValue(':id_usuario', $idUsuario, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $_SESSION['mensaje'] = "Estado del usuario actualizado correctamente";
                return true;
            } else {
                throw new Exception("Error al actualizar estado");
            }

        } catch (Exception $e) {
            error_log("Error cambiando estado usuario: " . $e->getMessage());
            $_SESSION['error'] = "Error al cambiar estado del usuario: " . $e->getMessage();
            return false;
        }
    }

    // Método para cambiar estado de reporte
    public function cambiarEstadoReporte($idReporte, $nuevoEstado) {
        try {
            $sql = "UPDATE reporte SET estado = :estado WHERE id_reporte = :id_reporte";
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':estado', $nuevoEstado, PDO::PARAM_STR);
            $stmt->bindValue(':id_reporte', $idReporte, PDO::PARAM_INT);

            if ($stmt->execute()) {
                $_SESSION['mensaje'] = "Estado del reporte actualizado correctamente";
                return true;
            } else {
                throw new Exception("Error al actualizar estado del reporte");
            }

        } catch (Exception $e) {
            error_log("Error cambiando estado reporte: " . $e->getMessage());
            $_SESSION['error'] = "Error al cambiar estado del reporte: " . $e->getMessage();
            return false;
        }
    }

    // Método para eliminar reporte
    public function eliminarReporte($idReporte) {
        try {
            // Iniciar transacción para eliminar en cascada
            $this->db->beginTransaction();

            // Eliminar imágenes del reporte
            $sql1 = "DELETE FROM imagen_reporte WHERE id_reporte = :id_reporte";
            $stmt1 = $this->db->prepare($sql1);
            $stmt1->bindValue(':id_reporte', $idReporte, PDO::PARAM_INT);
            $stmt1->execute();

            // Eliminar comentarios del reporte
            $sql2 = "DELETE FROM comentario_reporte WHERE id_reporte = :id_reporte";
            $stmt2 = $this->db->prepare($sql2);
            $stmt2->bindValue(':id_reporte', $idReporte, PDO::PARAM_INT);
            $stmt2->execute();

            // Eliminar historial del reporte
            $sql3 = "DELETE FROM historial_estado WHERE id_reporte = :id_reporte";
            $stmt3 = $this->db->prepare($sql3);
            $stmt3->bindValue(':id_reporte', $idReporte, PDO::PARAM_INT);
            $stmt3->execute();

            // Eliminar reporte
            $sql4 = "DELETE FROM reporte WHERE id_reporte = :id_reporte";
            $stmt4 = $this->db->prepare($sql4);
            $stmt4->bindValue(':id_reporte', $idReporte, PDO::PARAM_INT);
            $stmt4->execute();

            $this->db->commit();
            $_SESSION['mensaje'] = "Reporte eliminado correctamente";
            return true;

        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Error eliminando reporte: " . $e->getMessage());
            $_SESSION['error'] = "Error al eliminar el reporte: " . $e->getMessage();
            return false;
        }
    }

    // ==============================================
    // NUEVOS MÉTODOS AGREGADOS - SIN MODIFICAR LO EXISTENTE
    // ==============================================

    // Método para obtener autoridades disponibles
    public function obtenerAutoridades() {
        return [
            [
                'id' => 1,
                'nombre' => 'Alcaldía de Villavicencio - Secretaría de Infraestructura',
                'email' => 'infraestructura@villavicencio.gov.co',
                'tipo' => 'municipal',
                'descripcion' => 'Vías urbanas, calles y avenidas dentro de la ciudad',
                'responsable' => 'Secretaría de Infraestructura'
            ],
            [
                'id' => 2,
                'nombre' => 'INVIAS - Instituto Nacional de Vías',
                'email' => 'pqrs@invias.gov.co',
                'tipo' => 'nacional',
                'descripcion' => 'Vías nacionales como la Vía al Llano, vía a Puerto López',
                'responsable' => 'Dirección Territorial Meta'
            ],
            [
                'id' => 3,
                'nombre' => 'Gobernación del Meta - Secretaría de Infraestructura',
                'email' => 'infraestructura@meta.gov.co',
                'tipo' => 'departamental',
                'descripcion' => 'Vías departamentales que conectan municipios del Meta',
                'responsable' => 'Secretaría de Infraestructura Departamental'
            ]
        ];
    }

    // Método para "simular" envío de alerta (solo notificación interna)
    public function enviarAlertaAutoridad($idReporte, $idAutoridad, $emailPersonalizado = null) {
        try {
            // 1. Obtener información del reporte
            $sqlReporte = "SELECT r.*, ti.nombre as tipo_incidente,
                                  CONCAT(p.nombres, ' ', p.apellidos) as ciudadano
                           FROM reporte r
                           INNER JOIN tipo_incidente ti ON r.id_tipo_incidente = ti.id_tipo_incidente
                           INNER JOIN usuario u ON r.id_usuario = u.id_usuario
                           INNER JOIN persona p ON u.id_persona = p.id_persona
                           WHERE r.id_reporte = :id_reporte";

            $stmtReporte = $this->db->prepare($sqlReporte);
            $stmtReporte->bindValue(':id_reporte', $idReporte, PDO::PARAM_INT);
            $stmtReporte->execute();
            $reporte = $stmtReporte->fetch(PDO::FETCH_ASSOC);

            if (!$reporte) {
                throw new Exception("Reporte no encontrado");
            }

            // 2. Obtener información de la autoridad
            $autoridades = $this->obtenerAutoridades();
            $autoridad = null;

            foreach ($autoridades as $auth) {
                if ($auth['id'] == $idAutoridad) {
                    $autoridad = $auth;
                    break;
                }
            }

            if (!$autoridad) {
                throw new Exception("Autoridad no válida");
            }

            // 3. Crear notificación interna (SIMULACIÓN)
            $mensajeNotificacion = "📤 Alerta simulada enviada a {$autoridad['nombre']} " .
                                  "para el reporte #{$idReporte} - {$reporte['tipo_incidente']}";

            $this->crearNotificacionAlerta($idReporte, $autoridad, $mensajeNotificacion);

            // 4. Actualizar estado del reporte
            $this->actualizarEstadoReporte($idReporte, 'Notificado');

            // 5. Registrar en historial (para analytics)
            $emailDestino = $emailPersonalizado ?: $autoridad['email'];
            $this->registrarAlertaEnHistorial($idReporte, $autoridad, $emailDestino, 'simulado');

            $_SESSION['mensaje'] = "✅ Notificación de alerta creada: {$autoridad['nombre']} ha sido notificado";
            return true;

        } catch (Exception $e) {
            error_log("Error en alerta simulada: " . $e->getMessage());
            $_SESSION['error'] = "❌ Error: " . $e->getMessage();
            return false;
        }
    }

    // Método para crear notificación de alerta
    private function crearNotificacionAlerta($idReporte, $autoridad, $mensaje) {
        try {
            // Notificar a todos los admins sobre la alerta simulada
            $sqlAdmins = "SELECT id_usuario FROM usuario WHERE id_rol = 1 AND id_estado = 1";
            $stmtAdmins = $this->db->prepare($sqlAdmins);
            $stmtAdmins->execute();
            $admins = $stmtAdmins->fetchAll(PDO::FETCH_ASSOC);

            foreach ($admins as $admin) {
                $sql = "INSERT INTO notificacion
                        (id_usuario_destino, id_reporte, tipo, mensaje)
                        VALUES (:id_destino, :id_reporte, 'alerta_autoridad', :mensaje)";

                $stmt = $this->db->prepare($sql);
                $stmt->bindValue(':id_destino', $admin['id_usuario'], PDO::PARAM_INT);
                $stmt->bindValue(':id_reporte', $idReporte, PDO::PARAM_INT);
                $stmt->bindValue(':mensaje', $mensaje);
                $stmt->execute();
            }

            return true;

        } catch (Exception $e) {
            error_log("Error creando notificación de alerta: " . $e->getMessage());
            return false;
        }
    }

    // Método auxiliar para actualizar estado del reporte
    private function actualizarEstadoReporte($idReporte, $nuevoEstado) {
        $sql = "UPDATE reporte SET estado = :estado WHERE id_reporte = :id_reporte";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':estado', $nuevoEstado);
        $stmt->bindValue(':id_reporte', $idReporte, PDO::PARAM_INT);
        return $stmt->execute();
    }

    // Método para registrar en el historial de alertas
    private function registrarAlertaEnHistorial($idReporte, $autoridad, $emailDestino, $estado) {
        // Primero necesitamos crear la tabla historial_alertas si no existe
        // Por ahora solo logueamos la acción
        error_log("📝 Alerta simulada registrada - Reporte: {$idReporte}, Autoridad: {$autoridad['nombre']}, Estado: {$estado}");
        return true;
    }

    // Obtener notificaciones no leídas del usuario actual
    public function obtenerNotificacionesNoLeidas($idUsuario, $limite = 10) {
        try {
            $sql = "SELECT n.*, r.descripcion, ti.nombre as tipo_incidente,
                           uo.correo as usuario_origen,
                           CONCAT(po.nombres, ' ', po.apellidos) as nombre_origen,
                           DATE(n.fecha) as fecha
                    FROM notificacion n
                    INNER JOIN reporte r ON n.id_reporte = r.id_reporte
                    INNER JOIN tipo_incidente ti ON r.id_tipo_incidente = ti.id_tipo_incidente
                    LEFT JOIN usuario uo ON n.id_usuario_origen = uo.id_usuario
                    LEFT JOIN persona po ON uo.id_persona = po.id_persona
                    WHERE n.id_usuario_destino = :id_usuario AND n.leida = FALSE
                    ORDER BY n.fecha DESC
                    LIMIT :limite";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':id_usuario', $idUsuario, PDO::PARAM_INT);
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("Error obteniendo notificaciones: " . $e->getMessage());
            return [];
        }
    }

    // Obtener todas las notificaciones del usuario
    public function obtenerTodasNotificaciones($idUsuario, $limite = 20) {
        try {
            $sql = "SELECT n.*, r.descripcion, ti.nombre as tipo_incidente,
                           uo.correo as usuario_origen,
                           CONCAT(po.nombres, ' ', po.apellidos) as nombre_origen,
                           DATE(n.fecha) as fecha
                    FROM notificacion n
                    INNER JOIN reporte r ON n.id_reporte = r.id_reporte
                    INNER JOIN tipo_incidente ti ON r.id_tipo_incidente = ti.id_tipo_incidente
                    LEFT JOIN usuario uo ON n.id_usuario_origen = uo.id_usuario
                    LEFT JOIN persona po ON uo.id_persona = po.id_persona
                    WHERE n.id_usuario_destino = :id_usuario
                    ORDER BY n.fecha DESC
                    LIMIT :limite";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':id_usuario', $idUsuario, PDO::PARAM_INT);
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            error_log("Error obteniendo notificaciones: " . $e->getMessage());
            return [];
        }
    }

    // Marcar notificación como leída
    public function marcarNotificacionLeida($idNotificacion, $idUsuario) {
        try {
            $sql = "UPDATE notificacion SET leida = TRUE
                    WHERE id_notificacion = :id_notificacion
                    AND id_usuario_destino = :id_usuario";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':id_notificacion', $idNotificacion, PDO::PARAM_INT);
            $stmt->bindValue(':id_usuario', $idUsuario, PDO::PARAM_INT);
            return $stmt->execute();

        } catch (Exception $e) {
            error_log("Error marcando notificación: " . $e->getMessage());
            return false;
        }
    }

    // Marcar todas las notificaciones como leídas
    public function marcarTodasLeidas($idUsuario) {
        try {
            $sql = "UPDATE notificacion SET leida = TRUE
                    WHERE id_usuario_destino = :id_usuario AND leida = FALSE";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':id_usuario', $idUsuario, PDO::PARAM_INT);
            return $stmt->execute();

        } catch (Exception $e) {
            error_log("Error marcando todas las notificaciones: " . $e->getMessage());
            return false;
        }
    }

    // Contar notificaciones no leídas del usuario
    public function contarNotificacionesNoLeidas($idUsuario) {
        try {
            $sql = "SELECT COUNT(*) as total
                    FROM notificacion
                    WHERE id_usuario_destino = :id_usuario AND leida = FALSE";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':id_usuario', $idUsuario, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchColumn();

        } catch (Exception $e) {
            error_log("Error contando notificaciones: " . $e->getMessage());
            return 0;
        }
    }
}
?>