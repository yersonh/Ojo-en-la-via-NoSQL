<!-- ════════════════ TAB: REGLAS DE NOTIFICACIÓN ════════════════ -->
<div id="tab-notificar" class="tab-content">
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:22px;">

        <!-- GESTIÓN DE REGLAS -->
        <div class="section-card">
            <div class="section-card-header">
                <h4><i class="fas fa-cogs"></i> Reglas de Notificación Automática</h4>
                <button class="btn btn-primary btn-sm" onclick="abrirModalRegla()">
                    <i class="fas fa-plus"></i> Nueva regla
                </button>
            </div>
            <p style="font-size:.85rem;color:#7f8c8d;margin-bottom:16px;">
                Cada vez que un ciudadano crea un reporte, el sistema busca la regla correspondiente
                al tipo de incidente y envía automáticamente el correo a la entidad.
            </p>

            <div id="reglas-lista">
            <?php if (empty($reglasList)): ?>
                <div class="empty-state" id="reglas-empty">
                    <i class="fas fa-cogs"></i>
                    <p>No hay reglas configuradas aún.</p>
                </div>
            <?php else: ?>
            <?php foreach ($reglasList as $reg): ?>
                <div class="regla-item" id="regla-<?= $reg['id'] ?>"
                    data-reg="<?= htmlspecialchars(json_encode($reg), ENT_QUOTES) ?>"
                    style="border:1px solid #e8ecef;border-radius:8px;padding:12px 14px;margin-bottom:10px;background:<?= $reg['activa'] ? '#fff' : '#f8f9fa' ?>;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;">
                        <div style="flex:1;">
                            <div style="font-weight:600;font-size:.9rem;margin-bottom:2px;">
                                <?= htmlspecialchars($reg['tipo_incidente']) ?>
                                <span class="badge badge-<?= $reg['prioridad'] ?>" style="margin-left:6px;"><?= ucfirst($reg['prioridad']) ?></span>
                                <?php if (!$reg['activa']): ?>
                                <span class="badge" style="background:#95a5a6;color:#fff;margin-left:4px;">Inactiva</span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size:.82rem;color:#7f8c8d;">→ <?= htmlspecialchars($reg['entidad']) ?></div>
                        </div>
                        <div style="display:flex;gap:6px;flex-shrink:0;">
                            <button class="btn btn-secondary btn-sm btn-editar-regla" title="Editar"><i class="fas fa-pen"></i></button>
                            <button class="btn btn-sm btn-toggle-regla" style="background:<?= $reg['activa'] ? '#f39c12' : '#27ae60' ?>;color:#fff;" title="<?= $reg['activa'] ? 'Desactivar' : 'Activar' ?>">
                                <i class="fas fa-<?= $reg['activa'] ? 'pause' : 'play' ?>"></i>
                            </button>
                            <button class="btn btn-sm btn-eliminar-regla" style="background:#e74c3c;color:#fff;" title="Eliminar"><i class="fas fa-trash"></i></button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php endif; ?>
            </div>
        </div>

        <!-- HISTORIAL DE NOTIFICACIONES AUTOMÁTICAS -->
        <div class="section-card">
            <div class="section-card-header">
                <h4><i class="fas fa-history"></i> Historial de Envíos</h4>
                <span style="background:#eaf3fb;color:#2471a3;padding:3px 10px;border-radius:20px;font-size:.78rem;font-weight:600;"><?= count($notifEntList) ?></span>
            </div>

            <div class="timeline" style="max-height:600px;overflow-y:auto;">
                <?php if (empty($notifEntList)): ?>
                <div class="empty-state">
                    <i class="fas fa-inbox"></i>
                    <p>Aún no se han enviado notificaciones automáticas.</p>
                </div>
                <?php else: ?>
                <?php foreach ($notifEntList as $n): ?>
                <div class="timeline-item">
                    <div class="timeline-dot <?= htmlspecialchars($n['prioridad']) ?>"></div>
                    <div class="timeline-content">
                        <div class="timeline-header">
                            <span class="timeline-title"><?= htmlspecialchars($n['entidad']) ?></span>
                            <span class="badge badge-<?= htmlspecialchars($n['prioridad']) ?>"><?= ucfirst($n['prioridad']) ?></span>
                        </div>
                        <div style="font-weight:600;font-size:.85rem;margin-bottom:4px;"><?= htmlspecialchars($n['asunto']) ?></div>
                        <div class="timeline-body"><?= nl2br(htmlspecialchars(mb_substr($n['mensaje'], 0, 200))) ?><?= mb_strlen($n['mensaje']) > 200 ? '…' : '' ?></div>
                        <div class="timeline-meta" style="margin-top:6px;">
                            <i class="fas fa-robot"></i> <?= htmlspecialchars($n['origen']) ?>
                            &nbsp;·&nbsp;
                            <i class="fas fa-clock"></i> <?= htmlspecialchars($n['fecha']) ?>
                            <?php if ($n['reporte_id']): ?>
                            &nbsp;·&nbsp;<i class="fas fa-link"></i> Reporte vinculado
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div><!-- /tab-notificar -->

<!-- Modal regla -->
<div class="modal-overlay" id="modal-regla" style="display:none;">
    <div class="modal" style="max-width:500px;width:95%;">
        <h3 id="modal-regla-titulo">Nueva regla</h3>
        <input type="hidden" id="regla-edit-id">
        <div class="form-group" style="margin-top:14px;">
            <label class="form-label">Tipo de incidente</label>
            <input type="text" class="form-control" id="regla-tipo" placeholder="Ej: Hueco en vía">
        </div>
        <div class="form-group">
            <label class="form-label">Entidad responsable</label>
            <input type="text" class="form-control" id="regla-entidad" placeholder="Ej: Secretaría de Infraestructura">
        </div>
        <div class="form-group">
            <label class="form-label">Prioridad</label>
            <select class="form-control" id="regla-prioridad">
                <option value="alta">Alta</option>
                <option value="media" selected>Media</option>
                <option value="baja">Baja</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">Asunto del correo</label>
            <input type="text" class="form-control" id="regla-asunto" placeholder="Usa {tipo}, {direccion}, {reporte_id}" maxlength="120">
        </div>
        <div class="form-group">
            <label class="form-label">Mensaje del correo</label>
            <textarea class="form-control" id="regla-mensaje" rows="4" placeholder="Usa {tipo}, {direccion}, {reporte_id}" maxlength="1000"></textarea>
            <div class="form-hint">Variables disponibles: <code>{tipo}</code>, <code>{direccion}</code>, <code>{reporte_id}</code></div>
        </div>
        <div class="modal-actions">
            <button class="btn btn-secondary" onclick="cerrarModalRegla()">Cancelar</button>
            <button class="btn btn-primary" onclick="guardarRegla()"><i class="fas fa-save"></i> Guardar</button>
        </div>
    </div>
</div>
