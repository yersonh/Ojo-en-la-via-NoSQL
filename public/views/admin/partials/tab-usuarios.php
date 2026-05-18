<!-- ════════════════ TAB: USUARIOS ════════════════ -->
<div id="tab-usuarios" class="tab-content">
    <div class="section-card">
        <div class="section-card-header">
            <h4><i class="fas fa-users"></i> Gestión de Usuarios
                <span style="background:#eaf3fb;color:#2471a3;padding:3px 10px;border-radius:20px;font-size:.78rem;font-weight:600;margin-left:8px;" id="count-usuarios"><?= count($usuariosList) ?></span>
            </h4>
            <a href="exportar.php?tipo=usuarios" class="btn btn-sm" style="background:#1e88e5;color:#fff;font-weight:600;border-radius:8px;padding:7px 14px;text-decoration:none;display:inline-flex;align-items:center;gap:6px;white-space:nowrap;">
                <i class="fas fa-download"></i> Exportar CSV
            </a>
            <div class="section-filters">
                <div class="search-box">
                    <i class="fas fa-search"></i>
                    <input type="text" class="form-control" id="buscar-usuario" placeholder="Buscar usuario..." style="width:200px;">
                </div>
                <select class="form-control" id="filtro-rol" style="width:140px;">
                    <option value="">Todos los roles</option>
                    <option value="ciudadano">Ciudadano</option>
                    <option value="admin">Admin</option>
                </select>
                <select class="form-control" id="filtro-estado-usuario" style="width:140px;">
                    <option value="">Todos los estados</option>
                    <option value="1">Activos</option>
                    <option value="0">Inactivos</option>
                </select>
            </div>
        </div>
        <div class="table-wrap">
            <table id="tabla-usuarios">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nombre</th>
                        <th>Email</th>
                        <th>Teléfono</th>
                        <th>Rol</th>
                        <th>Estado</th>
                        <th>Registro</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($usuariosList as $i => $u): ?>
                    <tr data-id="<?= $u['id'] ?>"
                        data-rol="<?= htmlspecialchars($u['rol']) ?>"
                        data-estado="<?= $u['estado'] ? '1' : '0' ?>"
                        data-texto="<?= htmlspecialchars(strtolower($u['nombre'].' '.$u['email'].' '.$u['telefono'])) ?>">
                        <td style="color:#aaa;font-size:.8rem;"><?= $i+1 ?></td>
                        <td>
                            <div style="display:flex;align-items:center;gap:9px;">
                                <div style="width:32px;height:32px;border-radius:50%;background:#3498db;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.85rem;flex-shrink:0;">
                                    <?= strtoupper(substr($u['nombre'], 0, 1)) ?>
                                </div>
                                <span style="font-weight:600;"><?= htmlspecialchars($u['nombre']) ?></span>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td><?= htmlspecialchars($u['telefono']) ?></td>
                        <td>
                            <span class="badge badge-<?= htmlspecialchars($u['rol']) ?>">
                                <?= ucfirst($u['rol']) ?>
                            </span>
                        </td>
                        <td>
                            <span class="badge badge-<?= $u['estado'] ? 'activo' : 'inactivo' ?>" id="estado-badge-<?= $u['id'] ?>">
                                <?= $u['estado'] ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </td>
                        <td style="white-space:nowrap;font-size:.82rem;color:#777;"><?= htmlspecialchars($u['fecha']) ?></td>
                        <td>
                            <div style="display:flex;gap:6px;">
                                <button class="btn btn-secondary btn-sm btn-icon toggle-usuario"
                                    data-id="<?= $u['id'] ?>"
                                    data-estado="<?= $u['estado'] ? '1' : '0' ?>"
                                    title="<?= $u['estado'] ? 'Desactivar' : 'Activar' ?>">
                                    <i class="fas fa-<?= $u['estado'] ? 'ban' : 'check' ?>"></i>
                                </button>
                                <button class="btn btn-warning btn-sm btn-icon cambiar-rol"
                                    data-id="<?= $u['id'] ?>"
                                    data-rol="<?= htmlspecialchars($u['rol']) ?>"
                                    title="Cambiar rol">
                                    <i class="fas fa-user-cog"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($usuariosList)): ?>
                    <tr><td colspan="8"><div class="empty-state"><i class="fas fa-user-slash"></i>No hay usuarios</div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div><!-- /tab-usuarios -->
