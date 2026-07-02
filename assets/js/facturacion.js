document.addEventListener('DOMContentLoaded', function () {
    initInvoiceShare();
    initPaymentRegister();

    const form = document.querySelector('[data-billing-form]');
    if (!form) return;

    const itemsWrap = form.querySelector('[data-invoice-items]');
    const addItem = form.querySelector('[data-add-item]');

    addItem?.addEventListener('click', function () {
        const first = itemsWrap?.querySelector('.invoice-item');
        if (!first || !itemsWrap) return;
        const clone = first.cloneNode(true);
        clone.querySelectorAll('input').forEach(input => {
            input.value = input.name.includes('item_cantidad') ? '1' : (input.name.includes('item_precio') ? '0' : '');
        });
        itemsWrap.appendChild(clone);
        updateSummary();
    });

    itemsWrap?.addEventListener('click', function (event) {
        const remove = event.target.closest('[data-remove-item]');
        if (!remove) return;
        const rows = itemsWrap.querySelectorAll('.invoice-item');
        if (rows.length <= 1) {
            rows[0].querySelectorAll('input').forEach(input => {
                input.value = input.name.includes('item_cantidad') ? '1' : (input.name.includes('item_precio') ? '0' : '');
            });
        } else {
            remove.closest('.invoice-item')?.remove();
        }
        updateSummary();
    });

    form.addEventListener('input', updateSummary);
    form.addEventListener('change', function (event) {
        if (event.target.name === 'plan_id') {
            const selected = event.target.options[event.target.selectedIndex];
            const cost = normalize(selected?.dataset?.planCost);
            const firstEmptyPrice = Array.from(form.querySelectorAll('[data-item-price]')).find(input => normalize(input.value) === 0);
            const firstEmptyDescription = Array.from(form.querySelectorAll('input[name="item_descripcion[]"]')).find(input => input.value.trim() === '');
            if (cost > 0 && firstEmptyPrice) firstEmptyPrice.value = String(Math.round(cost));
            if (cost > 0 && firstEmptyDescription) firstEmptyDescription.value = selected.textContent.trim();
        }
        updateSummary();
    });

    function updateSummary() {
        const subtotal = Array.from(form.querySelectorAll('.invoice-item')).reduce((sum, item) => {
            const qty = normalize(item.querySelector('[data-item-qty]')?.value) || 1;
            const price = normalize(item.querySelector('[data-item-price]')?.value);
            return sum + (qty * price);
        }, 0);

        const discount = Math.min(normalize(form.querySelector('[data-discount]')?.value), subtotal);
        const total = Math.max(0, subtotal - discount);
        const paid = Math.min(normalize(form.querySelector('[data-initial-payment]')?.value), total);
        const pending = Math.max(0, total - paid);
        const percent = total > 0 ? Math.min(100, Math.round((paid / total) * 100)) : 0;

        setText('[data-summary-subtotal]', money(subtotal));
        setText('[data-summary-discount]', money(discount));
        setText('[data-summary-total]', money(total));
        setText('[data-summary-paid]', money(paid));
        setText('[data-summary-pending]', money(pending));
        const bar = form.querySelector('[data-summary-bar]');
        if (bar) bar.style.width = `${percent}%`;
    }

    function setText(selector, value) {
        const node = form.querySelector(selector);
        if (node) node.textContent = value;
    }

    function normalize(value) {
        const number = Number.parseFloat(String(value || '0').replace(/[^\d.]/g, ''));
        return Number.isFinite(number) ? number : 0;
    }

    function money(value) {
        return new Intl.NumberFormat('es-CO', {
            style: 'currency',
            currency: 'COP',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(value || 0);
    }

    updateSummary();
});

function initPaymentRegister() {
    const form = document.querySelector('[data-payment-form]');
    if (!form) return;

    const amountInput = form.querySelector('[data-cop-input]');
    const feedback = form.querySelector('[data-payment-feedback]');
    const submit = form.querySelector('.payment-submit');
    const maxPayment = normalizeMoney(form.dataset.maxPayment);

    if (amountInput) {
        const initialValue = normalizeMoney(amountInput.value);
        if (initialValue > 0) amountInput.value = formatCOP(initialValue);

        amountInput.addEventListener('input', () => {
            const value = normalizeMoney(amountInput.value);
            amountInput.value = value > 0 ? formatCOP(value) : '';
            validateAmount();
        });

        amountInput.addEventListener('blur', validateAmount);
    }

    form.addEventListener('submit', event => {
        if (!validateAmount()) {
            event.preventDefault();
            amountInput?.focus();
            return;
        }

        form.classList.add('is-submitting');
        form.closest('.payment-form-card')?.classList.add('is-loading');
        submit?.setAttribute('disabled', 'disabled');
        form.querySelectorAll('input, button').forEach(control => {
            if (control.name !== 'csrf_token') control.setAttribute('readonly', 'readonly');
        });
    });

    function validateAmount() {
        if (!amountInput) return true;

        const value = normalizeMoney(amountInput.value);
        const field = amountInput.closest('.payment-field');
        let message = `Disponible: ${formatCOP(maxPayment)}`;
        let valid = true;

        if (value <= 0) {
            valid = false;
            message = 'Ingresa un monto mayor a $0.';
        } else if (value > maxPayment) {
            valid = false;
            message = `El maximo permitido es ${formatCOP(maxPayment)}.`;
        }

        field?.classList.toggle('has-error', !valid);
        if (feedback) feedback.textContent = message;
        if (submit) submit.disabled = !valid || maxPayment <= 0;
        return valid;
    }

    function normalizeMoney(value) {
        const number = Number.parseInt(String(value || '0').replace(/\D/g, ''), 10);
        return Number.isFinite(number) ? number : 0;
    }

    function formatCOP(value) {
        return new Intl.NumberFormat('es-CO', {
            style: 'currency',
            currency: 'COP',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(value || 0);
    }

    validateAmount();
}

function initInvoiceShare() {
    const shareButtons = document.querySelectorAll('[data-billing-share]');

    shareButtons.forEach(button => {
        button.addEventListener('click', async () => {
            const title = button.dataset.shareTitle || document.title;
            const url = window.location.href;

            try {
                if (navigator.share) {
                    await navigator.share({ title, url });
                } else if (navigator.clipboard) {
                    await navigator.clipboard.writeText(url);
                    flashButton(button, 'Copiado');
                }
            } catch (error) {
                if (navigator.clipboard) {
                    await navigator.clipboard.writeText(url);
                    flashButton(button, 'Copiado');
                }
            }
        });
    });

    function flashButton(button, text) {
        const label = button.querySelector('span');
        if (!label) return;
        const original = label.textContent;
        label.textContent = text;
        window.setTimeout(() => {
            label.textContent = original;
        }, 1600);
    }
}
