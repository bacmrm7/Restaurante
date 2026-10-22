(() => {
    const icon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><path d="M12 3.5 14.7 9l6.1.9-4.4 4.3 1 6.1-5.4-2.9-5.4 2.9 1-6.1-4.4-4.3 6.1-.9z"/></svg>';
    const messages = {
        producto_creado: 'Insumo registrado correctamente.',
        combo_creado: 'Combo agregado al menú.',
        actualizado: 'Los cambios se guardaron correctamente.',
        eliminado: 'El registro se eliminó correctamente.',
        historial_limpio: 'El historial se vació correctamente.',
        venta_exitosa: 'Venta procesada correctamente.'
    };

    function getToastHost() {
        let host = document.querySelector('.toast-host');
        if (!host) {
            host = document.createElement('div');
            host.className = 'toast-host';
            host.setAttribute('aria-live', 'polite');
            host.setAttribute('aria-atomic', 'false');
            document.body.append(host);
        }
        return host;
    }

    function showToast(message, kind = 'success') {
        if (!message) return;
        const toast = document.createElement('div');
        toast.className = `app-toast app-toast-${kind}`;
        toast.innerHTML = `<span class="toast-icon">${icon}</span><span class="toast-message"></span><button class="toast-close" type="button" aria-label="Cerrar notificación">&times;</button>`;
        toast.querySelector('.toast-message').textContent = message;
        toast.querySelector('.toast-close').addEventListener('click', () => dismissToast(toast));
        getToastHost().append(toast);
        requestAnimationFrame(() => toast.classList.add('is-visible'));
        window.setTimeout(() => dismissToast(toast), 3000);
    }

    function dismissToast(toast) {
        toast.classList.remove('is-visible');
        window.setTimeout(() => toast.remove(), 220);
    }

    function renderStatus() {
        const shell = document.querySelector('.sidebar');
        const content = document.querySelector('.container, .main .content, .login-card, body > .card');
        if (!content || content.querySelector('.page-status')) return;

        const status = document.createElement('div');
        status.className = `page-status${shell ? '' : ' page-status-floating'}`;
        const date = new Intl.DateTimeFormat('es-VE', {
            weekday: 'long', day: 'numeric', month: 'long', year: 'numeric'
        }).format(new Date());
        status.innerHTML = `<span class="page-date"></span><span class="system-state"><i aria-hidden="true"></i><span>Sistema en línea</span></span><span class="system-state system-state-pos"><i aria-hidden="true"></i><span>POS disponible</span></span>`;
        status.querySelector('.page-date').textContent = date.charAt(0).toLocaleUpperCase('es-VE') + date.slice(1);
        content.prepend(status);
    }

    function renderConfirmDialog() {
        let dialog = document.querySelector('.confirm-dialog');
        if (dialog) return dialog;
        dialog = document.createElement('div');
        dialog.className = 'confirm-dialog';
        dialog.setAttribute('aria-hidden', 'true');
        dialog.innerHTML = `<div class="confirm-backdrop" data-confirm-cancel></div><section class="confirm-panel" role="dialog" aria-modal="true" aria-labelledby="confirm-title" aria-describedby="confirm-description"><span class="confirm-symbol" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 2.8 19h18.4L12 3Z"/><path d="M12 9v4m0 3h.01"/></svg></span><h2 id="confirm-title">Confirmar acción</h2><p id="confirm-description"></p><div class="confirm-actions"><button type="button" class="confirm-cancel" data-confirm-cancel>Cancelar</button><button type="button" class="confirm-accept">Confirmar</button></div></section>`;
        document.body.append(dialog);
        dialog.querySelectorAll('[data-confirm-cancel]').forEach(button => button.addEventListener('click', closeConfirm));
        dialog.querySelector('.confirm-accept').addEventListener('click', () => {
            if (!pendingForm) return;
            const form = pendingForm;
            const submitter = pendingSubmitter;
            pendingForm = null;
            pendingSubmitter = null;
            closeConfirm();
            form.dataset.confirmed = 'true';
            if (form.requestSubmit) form.requestSubmit(submitter || undefined);
            else form.submit();
        });
        return dialog;
    }

    let pendingForm = null;
    let pendingSubmitter = null;

    function closeConfirm() {
        const dialog = document.querySelector('.confirm-dialog');
        if (!dialog) return;
        dialog.classList.remove('is-open');
        dialog.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
    }

    function openConfirm(form, submitter) {
        const dialog = renderConfirmDialog();
        pendingForm = form;
        pendingSubmitter = submitter;
        dialog.querySelector('#confirm-description').textContent = form.dataset.confirm || '¿Deseas continuar con esta acción?';
        dialog.classList.add('is-open');
        dialog.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        dialog.querySelector('.confirm-cancel').focus();
    }

    function setLoadingState(form, submitter) {
        if (form.dataset.processing === 'true') return;
        const button = submitter || form.querySelector('button[type="submit"], input[type="submit"]');
        if (!button || button.dataset.loading === 'true') return;
        form.dataset.processing = 'true';
        button.dataset.loading = 'true';
        button.setAttribute('aria-busy', 'true');
        button.classList.add('is-loading');
        if (button.tagName === 'INPUT') {
            button.dataset.originalValue = button.value;
            button.value = 'Procesando...';
        } else {
            button.dataset.originalHtml = button.innerHTML;
            button.innerHTML = '<span class="button-spinner" aria-hidden="true"></span>Procesando...';
        }
    }

    document.addEventListener('DOMContentLoaded', () => {
        renderStatus();

        const url = new URL(window.location.href);
        const messageKey = url.searchParams.get('msg');
        if (messageKey && messages[messageKey]) {
            showToast(messages[messageKey]);
            url.searchParams.delete('msg');
            window.history.replaceState({}, '', url);
        }

        document.querySelectorAll('.alert-success').forEach(alert => {
            const message = alert.textContent.trim();
            if (message) showToast(message);
            alert.hidden = true;
        });

        const bodyMessage = document.body.dataset.toastSuccess;
        if (bodyMessage) showToast(bodyMessage);

        document.addEventListener('submit', event => {
            const form = event.target;
            if (!(form instanceof HTMLFormElement)) return;
            if (form.dataset.processing === 'true') {
                event.preventDefault();
                return;
            }
            if (form.dataset.confirm && form.dataset.confirmed !== 'true') {
                event.preventDefault();
                openConfirm(form, event.submitter);
                return;
            }
            delete form.dataset.confirmed;
            setLoadingState(form, event.submitter);
        }, true);

        document.addEventListener('keydown', event => {
            if (event.key === 'Escape') closeConfirm();
        });
    });
})();