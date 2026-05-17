<?php

require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../vendor/autoload.php';

use MongoDB\BSON\UTCDateTime;

class AnalyticsControlador
{
    private $db;

    public function __construct($database = null)
    {
        $this->db = conectarMongoDB();
    }

    private function fechaDesdeDias($dias): UTCDateTime
    {
        $fecha = new DateTime('now', new DateTimeZone('America/Bogota'));
        $fecha->modify('-' . max(0, (int) $dias) . ' days');
        return new UTCDateTime($fecha->getTimestamp() * 1000);
    }

    private function estadoNormalizado($estado): string
    {
        $estado = strtolower((string) $estado);

        return match ($estado) {
            'pendiente' => 'reportes_pendientes',
            'en_revision', 'en revision', 'en proceso' => 'reportes_proceso',
            'resuelto' => 'reportes_resueltos',
            default => ''
        };
    }

    public function obtenerEstadisticasGenerales($dias = 30)
    {
        try {
            $estadisticas = [
                'total_reportes' => $this->db->Reportes->countDocuments([]),
                'reportes_pendientes' => 0,
                'reportes_proceso' => 0,
                'reportes_resueltos' => 0,
                'total_usuarios' => $this->db->usuario->countDocuments([]),
                'usuarios_activos' => $this->db->usuario->countDocuments(['estado' => true])
            ];

            foreach ($this->db->Reportes->aggregate([
                ['$group' => ['_id' => '$estado', 'cantidad' => ['$sum' => 1]]]
            ]) as $estado) {
                $clave = $this->estadoNormalizado($estado['_id'] ?? '');
                if ($clave !== '') {
                    $estadisticas[$clave] = (int) $estado['cantidad'];
                }
            }

            return $estadisticas;
        } catch (Throwable $e) {
            error_log('Error obteniendo estadisticas generales: ' . $e->getMessage());
            return [];
        }
    }

    public function obtenerReportesPorTipo($dias = 30)
    {
        try {
            $pipeline = [
                ['$match' => ['fecha_reporte' => ['$gte' => $this->fechaDesdeDias($dias)]]],
                ['$group' => ['_id' => ['$ifNull' => ['$tipo', '$tipo_incidente']], 'cantidad' => ['$sum' => 1]]],
                ['$sort' => ['cantidad' => -1]]
            ];

            $resultados = $this->normalizarGrupo($this->db->Reportes->aggregate($pipeline), 'tipo');

            if (empty($resultados)) {
                $resultados = $this->normalizarGrupo($this->db->Reportes->aggregate([
                    ['$group' => ['_id' => ['$ifNull' => ['$tipo', '$tipo_incidente']], 'cantidad' => ['$sum' => 1]]],
                    ['$sort' => ['cantidad' => -1]]
                ]), 'tipo');
            }

            return $resultados;
        } catch (Throwable $e) {
            error_log('Error obteniendo reportes por tipo: ' . $e->getMessage());
            return [];
        }
    }

    public function obtenerDistribucionEstado($dias = 30)
    {
        try {
            $pipeline = [
                ['$match' => ['fecha_reporte' => ['$gte' => $this->fechaDesdeDias($dias)]]],
                ['$group' => ['_id' => '$estado', 'cantidad' => ['$sum' => 1]]],
                ['$sort' => ['cantidad' => -1]]
            ];

            $resultados = $this->normalizarGrupo($this->db->Reportes->aggregate($pipeline), 'estado');

            if (empty($resultados)) {
                $resultados = $this->normalizarGrupo($this->db->Reportes->aggregate([
                    ['$group' => ['_id' => '$estado', 'cantidad' => ['$sum' => 1]]],
                    ['$sort' => ['cantidad' => -1]]
                ]), 'estado');
            }

            return $resultados;
        } catch (Throwable $e) {
            error_log('Error obteniendo distribucion por estado: ' . $e->getMessage());
            return [];
        }
    }

    public function obtenerEvolucionTemporal($dias = 30, $agrupacion = 'weekly')
    {
        try {
            $formato = match ($agrupacion) {
                'daily' => '%Y-%m-%d',
                'monthly' => '%Y-%m',
                default => '%G-W%V'
            };

            return iterator_to_array($this->db->Reportes->aggregate([
                ['$match' => ['fecha_reporte' => ['$gte' => $this->fechaDesdeDias($dias)]]],
                [
                    '$group' => [
                        '_id' => [
                            '$dateToString' => [
                                'format' => $formato,
                                'date' => '$fecha_reporte',
                                'timezone' => 'America/Bogota'
                            ]
                        ],
                        'total' => ['$sum' => 1],
                        'resueltos' => [
                            '$sum' => [
                                '$cond' => [['$eq' => ['$estado', 'resuelto']], 1, 0]
                            ]
                        ]
                    ]
                ],
                ['$sort' => ['_id' => 1]],
                ['$project' => ['_id' => 0, 'periodo' => '$_id', 'total' => 1, 'resueltos' => 1]]
            ]), false);
        } catch (Throwable $e) {
            error_log('Error obteniendo evolucion temporal: ' . $e->getMessage());
            return [];
        }
    }

    public function obtenerUsuariosActivos($dias = 30, $limite = 5)
    {
        try {
            return iterator_to_array($this->db->Reportes->aggregate([
                ['$match' => ['fecha_reporte' => ['$gte' => $this->fechaDesdeDias($dias)]]],
                [
                    '$group' => [
                        '_id' => '$usuario_id',
                        'total_reportes' => ['$sum' => 1],
                        'ultima_actividad' => ['$max' => '$fecha_reporte']
                    ]
                ],
                ['$sort' => ['total_reportes' => -1, 'ultima_actividad' => -1]],
                ['$limit' => (int) $limite],
                ['$lookup' => ['from' => 'usuario', 'localField' => '_id', 'foreignField' => '_id', 'as' => 'usuario']],
                [
                    '$project' => [
                        '_id' => 0,
                        'correo' => ['$ifNull' => [['$arrayElemAt' => ['$usuario.email', 0]], '']],
                        'nombre' => ['$ifNull' => [['$arrayElemAt' => ['$usuario.nombre_completo', 0]], 'Usuario']],
                        'total_reportes' => 1,
                        'ultima_actividad' => 1,
                        'estado' => ['$ifNull' => [['$arrayElemAt' => ['$usuario.estado', 0]], true]]
                    ]
                ]
            ]), false);
        } catch (Throwable $e) {
            error_log('Error obteniendo usuarios activos: ' . $e->getMessage());
            return [];
        }
    }

    public function obtenerTendencias($dias = 30)
    {
        try {
            $dias = max(1, (int) $dias);
            $actualInicio = $this->fechaDesdeDias($dias);
            $anteriorInicio = $this->fechaDesdeDias($dias * 2);

            $actual = $this->db->Reportes->countDocuments(['fecha_reporte' => ['$gte' => $actualInicio]]);
            $anterior = $this->db->Reportes->countDocuments([
                'fecha_reporte' => [
                    '$gte' => $anteriorInicio,
                    '$lt' => $actualInicio
                ]
            ]);

            return ['total_reportes' => $this->calcularTendencia($actual, $anterior)];
        } catch (Throwable $e) {
            error_log('Error obteniendo tendencias: ' . $e->getMessage());
            return [];
        }
    }

    private function normalizarGrupo($cursor, string $campo): array
    {
        $resultados = [];

        foreach ($cursor as $item) {
            $resultados[] = [
                $campo => (string) ($item['_id'] ?? 'Sin dato'),
                'cantidad' => (int) $item['cantidad']
            ];
        }

        return $resultados;
    }

    private function calcularTendencia($actual, $anterior)
    {
        if ((int) $anterior === 0) {
            return [
                'direccion' => $actual > 0 ? 'up' : 'neutral',
                'porcentaje' => $actual > 0 ? 100 : 0,
                'diferencia' => (int) $actual
            ];
        }

        $diferencia = (int) $actual - (int) $anterior;
        $porcentaje = ($diferencia / (int) $anterior) * 100;

        return [
            'direccion' => $diferencia > 0 ? 'up' : ($diferencia < 0 ? 'down' : 'neutral'),
            'porcentaje' => abs(round($porcentaje, 1)),
            'diferencia' => $diferencia
        ];
    }
}

if (isset($_GET['action'])) {
    $analyticsControlador = new AnalyticsControlador();

    header('Content-Type: application/json');

    try {
        $dias = isset($_GET['dias']) ? (int) $_GET['dias'] : 30;
        $agrupacion = $_GET['agrupacion'] ?? 'weekly';

        switch ($_GET['action']) {
            case 'estadisticas_generales':
                echo json_encode([
                    'stats' => $analyticsControlador->obtenerEstadisticasGenerales($dias),
                    'tendencias' => $analyticsControlador->obtenerTendencias($dias)
                ]);
                break;

            case 'reportes_por_tipo':
                echo json_encode($analyticsControlador->obtenerReportesPorTipo($dias));
                break;

            case 'distribucion_estado':
                echo json_encode($analyticsControlador->obtenerDistribucionEstado($dias));
                break;

            case 'evolucion_temporal':
                echo json_encode($analyticsControlador->obtenerEvolucionTemporal($dias, $agrupacion));
                break;

            case 'usuarios_activos':
                echo json_encode($analyticsControlador->obtenerUsuariosActivos($dias));
                break;

            default:
                echo json_encode(['error' => 'Accion no valida']);
        }
    } catch (Throwable $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}
