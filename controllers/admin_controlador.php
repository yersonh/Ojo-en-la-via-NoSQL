<?php

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../vendor/autoload.php';

use MongoDB\BSON\ObjectId;
use MongoDB\BSON\UTCDateTime;

class AdminControlador
{
    private $db;

    public function __construct($database = null)
    {
        $this->db = conectarMongoDB();
    }

    private function objectId($id): ObjectId
    {
        if ($id instanceof ObjectId) {
            return $id;
        }

        if (!preg_match('/^[a-f\d]{24}$/i', (string) $id)) {
            throw new Exception('ID invalido.');
        }

        return new ObjectId((string) $id);
    }

    private function filtroUsuario($id): array
    {
        $texto = (string) $id;
        $filtro = [['usuario_id' => $texto], ['usuario_creador_id' => $texto]];

        if (preg_match('/^[a-f\d]{24}$/i', $texto)) {
            $obj = new ObjectId($texto);
            $filtro[] = ['usuario_id' => $obj];
            $filtro[] = ['usuario_creador_id' => $obj];
        }

        return ['$or' => $filtro];
    }

    public function obtenerEstadisticas()
    {
        try {
            $estadisticas = [
                'total_reportes' => $this->db->Reportes->countDocuments([]),
                'total_usuarios' => $this->db->usuario->countDocuments([]),
                'reportes_por_estado' => [],
                'tipos_comunes' => []
            ];

            foreach ($this->db->Reportes->aggregate([
                ['$group' => ['_id' => '$estado', 'cantidad' => ['$sum' => 1]]],
                ['$sort' => ['cantidad' => -1]]
            ]) as $estado) {
                $estadisticas['reportes_por_estado'][] = [
                    'estado' => (string) ($estado['_id'] ?? 'pendiente'),
                    'cantidad' => (int) $estado['cantidad']
                ];
            }

            foreach ($this->db->Reportes->aggregate([
                ['$group' => ['_id' => ['$ifNull' => ['$tipo', '$tipo_incidente']], 'cantidad' => ['$sum' => 1]]],
                ['$sort' => ['cantidad' => -1]],
                ['$limit' => 5]
            ]) as $tipo) {
                $estadisticas['tipos_comunes'][] = [
                    'nombre' => (string) ($tipo['_id'] ?? 'Sin tipo'),
                    'cantidad' => (int) $tipo['cantidad']
                ];
            }

            return $estadisticas;
        } catch (Throwable $e) {
            error_log('Error obteniendo estadisticas: ' . $e->getMessage());
            return [
                'total_reportes' => 0,
                'total_usuarios' => 0,
                'reportes_por_estado' => [],
                'tipos_comunes' => []
            ];
        }
    }

    public function obtenerUsuarios($limite = 50)
    {
        try {
            $usuarios = [];

            foreach ($this->db->usuario->find([], ['sort' => ['_id' => -1], 'limit' => (int) $limite]) as $u) {
                $nombre = (string) ($u['nombre_completo'] ?? $u['nombre'] ?? '');
                $usuarios[] = [
                    'id_usuario' => (string) $u['_id'],
                    'nombres' => $nombre,
                    'apellidos' => '',
                    'telefono' => (string) ($u['telefono'] ?? ''),
                    'correo' => (string) ($u['email'] ?? ''),
                    'rol' => (string) ($u['rol'] ?? 'ciudadano'),
                    'id_rol' => ($u['rol'] ?? 'ciudadano') === 'admin' ? 1 : 2,
                    'estado' => !empty($u['estado']) ? 'Activo' : 'Inactivo',
                    'id_estado' => !empty($u['estado']) ? 1 : 2,
                    'total_reportes' => $this->db->Reportes->countDocuments($this->filtroUsuario($u['_id']))
                ];
            }

            return $usuarios;
        } catch (Throwable $e) {
            error_log('Error obteniendo usuarios: ' . $e->getMessage());
            return [];
        }
    }

    public function obtenerReportes($limite = 50)
    {
        try {
            $usuarios = [];
            foreach ($this->db->usuario->find([], ['projection' => ['nombre_completo' => 1, 'nombre' => 1, 'email' => 1]]) as $u) {
                $usuarios[(string) $u['_id']] = (string) ($u['nombre_completo'] ?? $u['nombre'] ?? $u['email'] ?? 'N/A');
            }

            $reportes = [];
            foreach ($this->db->Reportes->find([], ['sort' => ['fecha_reporte' => -1, '_id' => -1], 'limit' => (int) $limite]) as $r) {
                $ubicacion = $r['ubicacion'] ?? [];
                $uid = (string) ($r['usuario_id'] ?? $r['usuario_creador_id'] ?? '');
                $reportes[] = [
                    'id_reporte' => (string) $r['_id'],
                    'descripcion' => (string) ($r['descripcion'] ?? ''),
                    'latitud' => $ubicacion['latitud'] ?? $ubicacion['lat'] ?? $r['latitud'] ?? '',
                    'longitud' => $ubicacion['longitud'] ?? $ubicacion['lng'] ?? $r['longitud'] ?? '',
                    'fecha_reporte' => $r['fecha_reporte'] ?? null,
                    'estado' => (string) ($r['estado'] ?? 'pendiente'),
                    'tipo_incidente' => (string) ($r['tipo'] ?? $r['tipo_incidente'] ?? 'Sin tipo'),
                    'correo' => '',
                    'usuario' => $usuarios[$uid] ?? 'N/A'
                ];
            }

            return $reportes;
        } catch (Throwable $e) {
            error_log('Error obteniendo reportes: ' . $e->getMessage());
            return [];
        }
    }

    public function cambiarEstadoUsuario($idUsuario, $nuevoEstado)
    {
        try {
            $estado = in_array($nuevoEstado, [1, '1', true, 'true', 'Activo'], true);
            $this->db->usuario->updateOne(['_id' => $this->objectId($idUsuario)], ['$set' => ['estado' => $estado]]);
            $_SESSION['mensaje'] = 'Estado del usuario actualizado correctamente';
            return true;
        } catch (Throwable $e) {
            error_log('Error cambiando estado usuario: ' . $e->getMessage());
            $_SESSION['error'] = 'Error al cambiar estado del usuario: ' . $e->getMessage();
            return false;
        }
    }

    public function cambiarEstadoReporte($idReporte, $nuevoEstado)
    {
        try {
            $rid = $this->objectId($idReporte);
            $reporte = $this->db->Reportes->findOne(['_id' => $rid]);
            $fecha = new UTCDateTime();

            $this->db->Reportes->updateOne(
                ['_id' => $rid],
                [
                    '$set' => ['estado' => $nuevoEstado, 'fecha_estado' => $fecha],
                    '$push' => [
                        'historial_estados' => [
                            'estado_anterior' => $reporte['estado'] ?? null,
                            'estado_nuevo' => $nuevoEstado,
                            'fecha_estado' => $fecha
                        ]
                    ]
                ]
            );

            $_SESSION['mensaje'] = 'Estado del reporte actualizado correctamente';
            return true;
        } catch (Throwable $e) {
            error_log('Error cambiando estado reporte: ' . $e->getMessage());
            $_SESSION['error'] = 'Error al cambiar estado del reporte: ' . $e->getMessage();
            return false;
        }
    }

    public function eliminarReporte($idReporte)
    {
        try {
            $rid = $this->objectId($idReporte);
            $this->db->Reportes->deleteOne(['_id' => $rid]);
            $this->db->notificaciones->deleteMany(['reporte_id' => $rid]);

            $_SESSION['mensaje'] = 'Reporte eliminado correctamente';
            return true;
        } catch (Throwable $e) {
            error_log('Error eliminando reporte: ' . $e->getMessage());
            $_SESSION['error'] = 'Error al eliminar el reporte: ' . $e->getMessage();
            return false;
        }
    }

    public function obtenerNotificacionesNoLeidas($idUsuario, $limite = 10)
    {
        return $this->obtenerNotificaciones($idUsuario, $limite, false);
    }

    public function obtenerTodasNotificaciones($idUsuario, $limite = 20)
    {
        return $this->obtenerNotificaciones($idUsuario, $limite, null);
    }

    private function obtenerNotificaciones($idUsuario, $limite, $leida)
    {
        try {
            $uid = $this->objectId($idUsuario);
            $filtro = [
                '$or' => [
                    ['usuario_destino_id' => $uid],
                    ['usuario_destino_id' => (string) $uid]
                ]
            ];

            if ($leida !== null) {
                $filtro['leida'] = $leida;
            }

            return iterator_to_array($this->db->notificaciones->find($filtro, [
                'sort' => ['fecha' => -1],
                'limit' => (int) $limite
            ]), false);
        } catch (Throwable $e) {
            error_log('Error obteniendo notificaciones: ' . $e->getMessage());
            return [];
        }
    }

    public function marcarNotificacionLeida($idNotificacion, $idUsuario)
    {
        try {
            $this->db->notificaciones->updateOne(
                ['_id' => $this->objectId($idNotificacion)],
                ['$set' => ['leida' => true]]
            );
            return true;
        } catch (Throwable $e) {
            error_log('Error marcando notificacion: ' . $e->getMessage());
            return false;
        }
    }

    public function marcarTodasLeidas($idUsuario)
    {
        try {
            $uid = $this->objectId($idUsuario);
            $this->db->notificaciones->updateMany(
                ['$or' => [['usuario_destino_id' => $uid], ['usuario_destino_id' => (string) $uid]]],
                ['$set' => ['leida' => true]]
            );
            return true;
        } catch (Throwable $e) {
            error_log('Error marcando todas las notificaciones: ' . $e->getMessage());
            return false;
        }
    }

    public function contarNotificacionesNoLeidas($idUsuario)
    {
        try {
            $uid = $this->objectId($idUsuario);
            return $this->db->notificaciones->countDocuments([
                '$or' => [['usuario_destino_id' => $uid], ['usuario_destino_id' => (string) $uid]],
                'leida' => false
            ]);
        } catch (Throwable $e) {
            error_log('Error contando notificaciones: ' . $e->getMessage());
            return 0;
        }
    }
}
