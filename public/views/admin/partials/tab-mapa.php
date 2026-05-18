<!-- ════════════════ TAB: MAPA ════════════════ -->
<div id="tab-mapa" class="tab-content">
    <div class="section-card">
        <div class="section-card-header">
            <h4><i class="fas fa-map-marked-alt"></i> Mapa de Reportes en Vivo</h4>
            <div style="display:flex;gap:10px;align-items:center;">
                <select class="form-control" id="mapa-filtro-estado" style="width:160px;" onchange="filtrarMapa()">
                    <option value="">Todos los estados</option>
                    <option value="pendiente">Pendiente</option>
                    <option value="en_revision">En Revisión</option>
                    <option value="notificado">Notificado</option>
                    <option value="resuelto">Resuelto</option>
                </select>
                <button class="btn btn-primary btn-sm" onclick="recargarMapa()">
                    <i class="fas fa-sync-alt"></i> Actualizar
                </button>
            </div>
        </div>
        <!-- Leyenda -->
        <div style="display:flex;gap:16px;flex-wrap:wrap;margin-bottom:14px;">
            <?php
            $leyenda = [
                'pendiente'   => ['color'=>'#f39c12','label'=>'Pendiente'],
                'en_revision' => ['color'=>'#3498db','label'=>'En Revisión'],
                'notificado'  => ['color'=>'#9b59b6','label'=>'Notificado'],
                'resuelto'    => ['color'=>'#27ae60','label'=>'Resuelto'],
            ];
            foreach ($leyenda as $leg): ?>
            <div style="display:flex;align-items:center;gap:6px;font-size:.82rem;">
                <span style="width:12px;height:12px;border-radius:50%;background:<?= $leg['color'] ?>;display:inline-block;"></span>
                <?= $leg['label'] ?>
            </div>
            <?php endforeach; ?>
        </div>
        <div id="adminMap"></div>
    </div>
</div><!-- /tab-mapa -->
