<!-- ════════════════ TAB: REPORTES ════════════════ -->
<div id="tab-reportes" class="tab-content">
    <div class="section-card">
        <div class="section-card-header">
            <h4><i class="fas fa-map-marker-alt"></i> Gestión de Reportes
                <span style="background:#eaf3fb;color:#2471a3;padding:3px 10px;border-radius:20px;font-size:.78rem;font-weight:600;margin-left:8px;" id="count-reportes"><?= count($reportesList) ?></span>
            </h4>
            <div class="section-filters">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" class="form-control" id="buscar-reporte" placeholder="Buscar reporte..." style="width:200px;">
                </div>
                <select class="form-control" id="filtro-estado-reporte" style="width:160px;">
                    <option value="">Todos los estados</option>
                    <option value="pendiente">Pendiente</option>
                    <option value="en_revision">En Revisión</option>
                    <option value="notificado">Notificado</option>
                    <option value="resuelto">Resuelto</option>
                </select>
                <select class="form-control" id="filtro-tipo-reporte" style="width:150px;">
                    <option value="">Todos los tipos</option>
                    <?php
                    $tiposUnicos = array_unique(array_column($reportesList, 'tipo'));
                    sort($tiposUnicos);
                    foreach ($tiposUnicos as $t): ?>
                        <option value="<?= htmlspecialchars($t) ?>"><?= htmlspecialchars($t) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="table-wrap">
            <table id="tabla-reportes">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Tipo</th>
                        <th>Descripción</th>
                        <th>Usuario</th>
                        <th>Fecha</th>
                        <th>Estado</th>
                        <th>Cambiar Estado</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($reportesList as $i => $r): ?>
                    <tr data-id="<?= $r['id'] ?>"
                        data-estado="<?= htmlspecialchars($r['estado']) ?>"
                        data-tipo="<?= htmlspecialchars($r['tipo']) ?>"
                        data-texto="<?= htmlspecialchars(strtolower($r['tipo'].' '.$r['descripcion'].' '.$r['usuario'])) ?>">
                        <td style="color:#aaa;font-size:.8rem;"><?= $i+1 ?></td>
                        <td><span style="font-weight:600;"><?= htmlspecialchars($r['tipo']) ?></span></td>
                        <td>
                            <span class="text-truncate" style="display:block;max-width:200px;" title="<?= htmlspecialchars($r['descripcion']) ?>">
                                <?= htmlspecialchars(mb_substr($r['descripcion'], 0, 60)) ?><?= mb_strlen($r['descripcion']) > 60 ? '…' : '' ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($r['usuario']) ?></td>
                        <td style="white-space:nowrap;"><?= htmlspecialchars($r['fecha']) ?></td>
                        <td>
                            <span class="badge badge-<?= htmlspecialchars($r['estado']) ?>">
                                <?= ucfirst(str_replace('_',' ', $r['estado'])) ?>
                            </span>
                        </td>
                        <td>
                            <select class="form-control select-estado-reporte" style="font-size:.8rem;padding:4px 8px;width:130px;" data-id="<?= $r['id'] ?>">
                                <option value="pendiente"   <?= $r['estado']==='pendiente'   ?'selected':'' ?>>Pendiente</option>
                                <option value="en_revision" <?= $r['estado']==='en_revision' ?'selected':'' ?>>En Revisión</option>
                                <option value="notificado"  <?= $r['estado']==='notificado'  ?'selected':'' ?>>Notificado</option>
                                <option value="resuelto"    <?= $r['estado']==='resuelto'    ?'selected':'' ?>>Resuelto</option>
                            </select>
                        </td>
                        <td>
                            <button class="btn btn-danger btn-sm btn-icon eliminar-reporte" data-id="<?= $r['id'] ?>" title="Eliminar reporte">
                                <i class="fas fa-trash"></i>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($reportesList)): ?>
                    <tr><td colspan="8"><div class="empty-state"><i class="fas fa-inbox"></i>No hay reportes</div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div><!-- /tab-reportes -->
