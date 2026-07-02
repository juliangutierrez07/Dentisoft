<?php
/**
 * Toast para avisos de correo - DentiSoft 1.0
 */

$emailWarning = $_SESSION['email_warning'] ?? null;

if ($emailWarning):
    unset($_SESSION['email_warning']);
?>

<style>
.email-warning-toast {
    position: fixed;
    right: 24px;
    bottom: 24px;
    z-index: 10000;
    width: min(420px, calc(100vw - 32px));
    display: grid;
    grid-template-columns: 40px 1fr 34px;
    gap: 12px;
    align-items: start;
    padding: 16px;
    color: #eaf4ff;
    background: linear-gradient(135deg, #111d2d 0%, #16283b 100%);
    border: 1px solid rgba(53, 208, 255, .22);
    border-left: 4px solid #f5b84b;
    border-radius: 12px;
    box-shadow: 0 18px 45px rgba(0, 0, 0, .32);
    animation: email-toast-in .28s ease-out;
}

.email-warning-toast-icon {
    width: 40px;
    height: 40px;
    display: grid;
    place-items: center;
    color: #101826;
    background: #f5b84b;
    border-radius: 10px;
}

.email-warning-toast-title {
    margin: 0 0 4px;
    font-weight: 800;
    color: #ffffff;
}

.email-warning-toast-text {
    margin: 0;
    color: #b9cbe0;
    font-size: 13px;
    line-height: 1.45;
}

.email-warning-toast-close {
    width: 34px;
    height: 34px;
    display: grid;
    place-items: center;
    border: 0;
    border-radius: 8px;
    color: #c7d8e8;
    background: rgba(255, 255, 255, .08);
    cursor: pointer;
}

.email-warning-toast-close:hover {
    color: #ffffff;
    background: rgba(255, 255, 255, .14);
}

@keyframes email-toast-in {
    from { opacity: 0; transform: translateY(16px); }
    to { opacity: 1; transform: translateY(0); }
}

@media (max-width: 640px) {
    .email-warning-toast {
        right: 16px;
        bottom: 16px;
    }
}
</style>

<div class="email-warning-toast" role="status" aria-live="polite">
    <div class="email-warning-toast-icon">
        <i class="bi bi-envelope-exclamation" aria-hidden="true"></i>
    </div>
    <div>
        <p class="email-warning-toast-title">Correo no enviado</p>
        <p class="email-warning-toast-text"><?= htmlspecialchars($emailWarning, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></p>
    </div>
    <button type="button" class="email-warning-toast-close" aria-label="Cerrar aviso de correo" onclick="this.closest('.email-warning-toast').remove();">
        <i class="bi bi-x-lg" aria-hidden="true"></i>
    </button>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toast = document.querySelector('.email-warning-toast');
    if (!toast) return;

    window.setTimeout(function() {
        toast.style.opacity = '0';
        toast.style.transform = 'translateY(16px)';
        toast.style.transition = 'opacity .24s ease, transform .24s ease';
        window.setTimeout(function() {
            toast.remove();
        }, 260);
    }, 8000);
});
</script>

<?php endif; ?>
