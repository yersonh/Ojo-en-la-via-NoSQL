<?php
require_once __DIR__ . '/../config/database.php';

class AnalyticsControlador {
    private $db;

    public function __construct($database) {
        $this->db = $database->conectar();
    }

    // Obtener estadísticas generales
    public function obtenerEstadisticasGenerales($dias = 30) {
        try {
            $estadisticas = [
                'total_reportes' => 0,
                'reportes_pendientes' => 0,
                'reportes_proceso' => 0,
                'reportes_resueltos' => 0,
                'total_usuarios' => 0,
                'usuarios_activos' => 0
            ];

            // Total reportes
            $sql = "SELECT COUNT(*) as total FROM reporte";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $estadisticas['total_reportes'] = (int)$stmt->fetchColumn();

            // Reportes por estado
            $sql = "SELECT estado, COUNT(*) as cantidad FROM reporte GROUP BY estado";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $reportesEstado = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($reportesEstado as $estado) {
                switch ($estado['estado']) {
                    case 'Pendiente':
                        $estadisticas['reportes_pendientes'] = (int)$estado['cantidad'];
                        break;
                    case 'En Proceso':
                        $estadisticas['reportes_proceso'] = (int)$estado['cantidad'];
                        break;
                    case 'Resuelto':
                        $estadisticas['reportes_resueltos'] = (int)$estado['cantidad'];
                        break;
                }
            }

            // Total usuarios
            $sql = "SELECT COUNT(*) as total FROM usuario";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $estadisticas['total_usuarios'] = (int)$stmt->fetchColumn();

            // Usuarios activos
            $sql = "SELECT COUNT(*) as activos FROM usuario WHERE id_estado = 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $estadisticas['usuarios_activos'] = (int)$stmt->fetchColumn();

            return $estadisticas;

        } catch (Exception $e) {
            error_log("Error obteniendo estadísticas generales: " . $e->getMessage());
            return [];
        }
    }

    // Obtener reportes por tipo
    public function obtenerReportesPorTipo($dias = 30) {
        try {
            $fechaInicio = date('Y-m-d', strtotime("-$dias days"));

            $sql = "SELECT
                        ti.nombre as tipo,
                        COUNT(*) as cantidad
                    FROM reporte r
                    INNER JOIN tipo_incidente ti ON r.id_tipo_incidente = ti.id_tipo_incidente
                    WHERE r.fecha_reporte >= :fecha_inicio OR :fecha_inicio IS NULL
                    GROUP BY ti.nombre
                    ORDER BY cantidad DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':fecha_inicio', $fechaInicio);
            $stmt->execute();

            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Si no hay resultados con filtro de fecha, mostrar todos
            if (empty($resultados)) {
                $sql = "SELECT
                            ti.nombre as tipo,
                            COUNT(*) as cantidad
                        FROM reporte r
                        INNER JOIN tipo_incidente ti ON r.id_tipo_incidente = ti.id_tipo_incidente
                        GROUP BY ti.nombre
                        ORDER BY cantidad DESC";

                $stmt = $this->db->prepare($sql);
                $stmt->execute();
                $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            return $resultados;

        } catch (Exception $e) {
            error_log("Error obteniendo reportes por tipo: " . $e->getMessage());
            return [];
        }
    }

    // Obtener distribución por estado
    public function obtenerDistribucionEstado($dias = 30) {
        try {
            $fechaInicio = date('Y-m-d', strtotime("-$dias days"));

            $sql = "SELECT
                        estado,
                        COUNT(*) as cantidad
                    FROM reporte
                    WHERE fecha_reporte >= :fecha_inicio OR :fecha_inicio IS NULL
                    GROUP BY estado
                    ORDER BY cantidad DESC";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':fecha_inicio', $fechaInicio);
            $stmt->execute();

            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Si no hay resultados con filtro de fecha, mostrar todos
            if (empty($resultados)) {
                $sql = "SELECT
                            estado,
                            COUNT(*) as cantidad
                        FROM reporte
                        GROUP BY estado
                        ORDER BY cantidad DESC";

                $stmt = $this->db->prepare($sql);
                $stmt->execute();
                $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            return $resultados;

        } catch (Exception $e) {
            error_log("Error obteniendo distribución por estado: " . $e->getMessage());
            return [];
        }
    }

    // Obtener evolución temporal
    public function obtenerEvolucionTemporal($dias = 30, $agrupacion = 'weekly') {
        try {
            $fechaInicio = date('Y-m-d', strtotime("-$dias days"));

            switch ($agrupacion) {
                case 'daily':
                    $formatoFecha = "DATE(r.fecha_reporte)";
                    break;
                case 'weekly':
                    $formatoFecha = "CONCAT('Sem ', WEEK(r.fecha_reporte))";
                    break;
                case 'monthly':
                    $formatoFecha = "DATE_FORMAT(r.fecha_reporte, '%M')";
                    break;
                default:
                    $formatoFecha = "CONCAT('Sem ', WEEK(r.fecha_reporte))";
            }

            $sql = "SELECT
                        $formatoFecha as periodo,
                        COUNT(*) as total,
                        SUM(CASE WHEN estado = 'Resuelto' THEN 1 ELSE 0 END) as resueltos
                    FROM reporte r
                    WHERE r.fecha_reporte >= :fecha_inicio OR :fecha_inicio IS NULL
                    GROUP BY periodo
                    ORDER BY MIN(r.fecha_reporte)";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':fecha_inicio', $fechaInicio);
            $stmt->execute();

            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return $resultados;

        } catch (Exception $e) {
            error_log("Error obteniendo evolución temporal: " . $e->getMessage());
            return [];
        }
    }

    // Obtener usuarios más activos - VERSIÓN CORREGIDA
    public function obtenerUsuariosActivos($dias = 30, $limite = 5) {
        try {
            // Primero intentamos con el filtro de fecha
            $fechaInicio = date('Y-m-d', strtotime("-$dias days"));

            $sql = "SELECT
                        u.correo,
                        CONCAT(p.nombres, ' ', p.apellidos) as nombre,
                        COUNT(r.id_reporte) as total_reportes,
                        COALESCE(MAX(r.fecha_reporte), u.id_usuario) as ultima_actividad,
                        eu.nombre as estado
                    FROM usuario u
                    INNER JOIN persona p ON u.id_persona = p.id_persona
                    INNER JOIN estado_usuario eu ON u.id_estado = eu.id_estado
                    LEFT JOIN reporte r ON u.id_usuario = r.id_usuario
                    WHERE (r.fecha_reporte >= :fecha_inicio OR r.fecha_reporte IS NULL)
                    GROUP BY u.id_usuario, u.correo, p.nombres, p.apellidos, eu.nombre
                    HAVING COUNT(r.id_reporte) > 0
                    ORDER BY total_reportes DESC, ultima_actividad DESC
                    LIMIT :limite";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':fecha_inicio', $fechaInicio);
            $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
            $stmt->execute();

            $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Si no hay resultados, obtener todos los usuarios con reportes sin filtrar por fecha
            if (empty($resultados)) {
                $sql = "SELECT
                            u.correo,
                            CONCAT(p.nombres, ' ', p.apellidos) as nombre,
                            COUNT(r.id_reporte) as total_reportes,
                            COALESCE(MAX(r.fecha_reporte), 'Nunca') as ultima_actividad,
                            eu.nombre as estado
                        FROM usuario u
                        INNER JOIN persona p ON u.id_persona = p.id_persona
                        INNER JOIN estado_usuario eu ON u.id_estado = eu.id_estado
                        LEFT JOIN reporte r ON u.id_usuario = r.id_usuario
                        GROUP BY u.id_usuario, u.correo, p.nombres, p.apellidos, eu.nombre
                        HAVING COUNT(r.id_reporte) > 0
                        ORDER BY total_reportes DESC, ultima_actividad DESC
                        LIMIT :limite";

                $stmt = $this->db->prepare($sql);
                $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
                $stmt->execute();

                $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            // Si aún no hay resultados, mostrar al menos los usuarios existentes
            if (empty($resultados)) {
                $sql = "SELECT
                            u.correo,
                            CONCAT(p.nombres, ' ', p.apellidos) as nombre,
                            0 as total_reportes,
                            'Nunca' as ultima_actividad,
                            eu.nombre as estado
                        FROM usuario u
                        INNER JOIN persona p ON u.id_persona = p.id_persona
                        INNER JOIN estado_usuario eu ON u.id_estado = eu.id_estado
                        WHERE u.id_estado = 1
                        ORDER BY u.id_usuario DESC
                        LIMIT :limite";

                $stmt = $this->db->prepare($sql);
                $stmt->bindValue(':limite', $limite, PDO::PARAM_INT);
                $stmt->execute();

                $resultados = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            return $resultados;

        } catch (Exception $e) {
            error_log("Error obteniendo usuarios activos: " . $e->getMessage());
            return [];
        }
    }

    // Obtener tendencias
    public function obtenerTendencias($dias = 30) {
        try {
            $periodoActualInicio = date('Y-m-d', strtotime("-$dias days"));
            $periodoAnteriorInicio = date('Y-m-d', strtotime("-" . ($dias * 2) . " days"));
            $periodoAnteriorFin = date('Y-m-d', strtotime("-$dias days"));

            $tendencias = [];

            // Total reportes - tendencia
            $sql = "SELECT
                        (SELECT COUNT(*) FROM reporte WHERE fecha_reporte >= :actual_inicio) as actual,
                        (SELECT COUNT(*) FROM reporte WHERE fecha_reporte >= :anterior_inicio AND fecha_reporte < :anterior_fin) as anterior";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':actual_inicio', $periodoActualInicio);
            $stmt->bindValue(':anterior_inicio', $periodoAnteriorInicio);
            $stmt->bindValue(':anterior_fin', $periodoAnteriorFin);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            $tendencias['total_reportes'] = $this->calcularTendencia($result['actual'], $result['anterior']);

            return $tendencias;

        } catch (Exception $e) {
            error_log("Error obteniendo tendencias: " . $e->getMessage());
            return [];
        }
    }

    private function calcularTendencia($actual, $anterior) {
        if ($anterior == 0) {
            return $actual > 0 ? ['direccion' => 'up', 'porcentaje' => 100, 'diferencia' => $actual] :
                                ['direccion' => 'neutral', 'porcentaje' => 0, 'diferencia' => 0];
        }

        $diferencia = $actual - $anterior;
        $porcentaje = ($diferencia / $anterior) * 100;

        return [
            'direccion' => $diferencia > 0 ? 'up' : ($diferencia < 0 ? 'down' : 'neutral'),
            'porcentaje' => abs(round($porcentaje, 1)),
            'diferencia' => $diferencia
        ];
    }
}

// Manejar solicitudes AJAX
if (isset($_GET['action'])) {
    $database = new Database();
    $analyticsControlador = new AnalyticsControlador($database);

    header('Content-Type: application/json');

    try {
        $dias = isset($_GET['dias']) ? intval($_GET['dias']) : 30;
        $agrupacion = isset($_GET['agrupacion']) ? $_GET['agrupacion'] : 'weekly';

        switch ($_GET['action']) {
            case 'estadisticas_generales':
                $data = [
                    'stats' => $analyticsControlador->obtenerEstadisticasGenerales($dias),
                    'tendencias' => $analyticsControlador->obtenerTendencias($dias)
                ];
                echo json_encode($data);
                break;

            case 'reportes_por_tipo':
                $data = $analyticsControlador->obtenerReportesPorTipo($dias);
                echo json_encode($data);
                break;

            case 'distribucion_estado':
                $data = $analyticsControlador->obtenerDistribucionEstado($dias);
                echo json_encode($data);
                break;

            case 'evolucion_temporal':
                $data = $analyticsControlador->obtenerEvolucionTemporal($dias, $agrupacion);
                echo json_encode($data);
                break;

            case 'usuarios_activos':
                $data = $analyticsControlador->obtenerUsuariosActivos($dias);
                echo json_encode($data);
                break;

            default:
                echo json_encode(['error' => 'Acción no válida']);
        }

    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
}
?>