<?php
// Requiere que $logoutUrl esté definida antes de incluir este archivo.
// Ejemplo: $logoutUrl = '../../index.php';
$logoutUrl = $logoutUrl ?? '../../index.php';
?>
<style>
    .modal-logout-overlay {
        display: none;
        position: fixed;
        inset: 0;
        z-index: 9999;
        background: rgba(0, 0, 0, .60);
        align-items: center;
        justify-content: center;
    }
    .modal-logout-overlay.abierto {
        display: flex;
    }
    .modal-logout-box {
        background: #fff;
        border-radius: 16px;
        padding: 36px 32px 28px;
        width: 100%;
        max-width: 400px;
        box-shadow: 0 20px 60px rgba(0,0,0,.3);
        text-align: center;
        animation: mlSlideIn .2s ease;
    }
    @keyframes mlSlideIn {
        from { transform: translateY(-20px); opacity: 0; }
        to   { transform: translateY(0);     opacity: 1; }
    }
    .modal-logout-icon {
        width: 64px;
        height: 64px;
        background: #fff0f0;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 18px;
    }
    .modal-logout-icon i {
        font-size: 1.8rem;
        color: #e74c3c;
    }
    .modal-logout-box h3 {
        font-size: 1.25rem;
        font-weight: 700;
        color: #1a2332;
        margin-bottom: 8px;
    }
    .modal-logout-box p {
        color: #6b7280;
        font-size: .95rem;
        margin-bottom: 28px;
        line-height: 1.5;
    }
    .modal-logout-actions {
        display: flex;
        gap: 12px;
        justify-content: center;
    }
    .modal-logout-actions button,
    .modal-logout-actions .btn-logout-confirm {
        padding: 11px 28px;
        border-radius: 8px;
        font-size: .95rem;
        font-weight: 600;
        cursor: pointer;
        border: none;
        transition: .2s;
    }
    .btn-logout-cancel {
        background: #f3f4f6;
        color: #374151;
    }
    .btn-logout-cancel:hover {
        background: #e5e7eb;
    }
    .btn-logout-confirm {
        background: #e74c3c;
        color: #fff;
        display: inline-block;
        text-decoration: none;
    }
    .btn-logout-confirm:hover {
        background: #c0392b;
    }
</style>

<div id="modalLogout" class="modal-logout-overlay" onclick="if(event.target===this)cerrarModalLogout()">
    <div class="modal-logout-box">
        <div class="modal-logout-icon">
            <i class="fas fa-sign-out-alt"></i>
        </div>
        <h3>Cerrar sesión</h3>
        <p>¿Estás seguro que deseas salir de tu cuenta?</p>
        <div class="modal-logout-actions">
            <button class="btn-logout-cancel" onclick="cerrarModalLogout()">Cancelar</button>
            <form action="<?= htmlspecialchars($logoutUrl) ?>" method="POST" style="margin:0;">
                <input type="hidden" name="accion" value="logout">
                <button type="submit" class="btn-logout-confirm">Sí, cerrar sesión</button>
            </form>
        </div>
    </div>
</div>

<script>
function abrirModalLogout()  { document.getElementById('modalLogout').classList.add('abierto'); }
function cerrarModalLogout() { document.getElementById('modalLogout').classList.remove('abierto'); }
</script>
