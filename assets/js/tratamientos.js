/**
 * DentiSoft 1.0 - Treatment Module JavaScript
 * Handles interactions for treatment detail, form, and session pages
 */

document.addEventListener('DOMContentLoaded', function() {
    // Initialize collapsible form toggle
    initFormToggle();
    
    // Initialize progress bar animations
    initProgressAnimations();
    
    // Initialize session card interactions
    initSessionCards();
    
    // Initialize form field animations
    initFormFieldAnimations();

    initTreatmentWizard();
    initFormLoading();
});

/**
 * Initialize collapsible form toggle
 */
function initFormToggle() {
    const toggleButtons = document.querySelectorAll('.treatment-form-toggle');
    
    toggleButtons.forEach(button => {
        const targetSelector = button.getAttribute('data-bs-target');
        const collapseElement = targetSelector ? document.querySelector(targetSelector) : null;
        if (!collapseElement) return;

        collapseElement.addEventListener('shown.bs.collapse', () => {
            button.setAttribute('aria-expanded', 'true');
        });
        collapseElement.addEventListener('hidden.bs.collapse', () => {
            button.setAttribute('aria-expanded', 'false');
        });
    });
}

/**
 * Initialize progress bar animations
 */
function initProgressAnimations() {
    const progressBars = document.querySelectorAll('.progress-bar-fill');
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const targetWidth = entry.target.style.width;
                entry.target.style.width = '0%';
                
                setTimeout(() => {
                    entry.target.style.transition = 'width 1s cubic-bezier(0.4, 0, 0.2, 1)';
                    entry.target.style.width = targetWidth;
                }, 100);
                
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1 });
    
    progressBars.forEach(bar => observer.observe(bar));
}

/**
 * Initialize session card interactions
 */
function initSessionCards() {
    const sessionCards = document.querySelectorAll('.session-card');
    
    sessionCards.forEach(card => {
        // Add hover sound effect (optional - can be removed)
        card.addEventListener('mouseenter', function() {
            this.style.transition = 'transform 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
        });
        
        // Handle view button click
        const viewButton = card.querySelector('.session-action.view');
        if (viewButton) {
            viewButton.addEventListener('click', function(e) {
                // Add ripple effect
                createRipple(this, e);
            });
        }
    });
}

/**
 * Initialize form field animations
 */
function initFormFieldAnimations() {
    const inputs = document.querySelectorAll('.treatment-input, .treatment-select, .treatment-textarea');
    
    inputs.forEach(input => {
        // Focus animation
        input.addEventListener('focus', function() {
            this.parentElement.style.transform = 'scale(1.02)';
            this.parentElement.style.transition = 'transform 0.2s ease';
        });
        
        input.addEventListener('blur', function() {
            this.parentElement.style.transform = 'scale(1)';
        });
        
        // Input validation visual feedback
        input.addEventListener('input', function() {
            if (this.checkValidity()) {
                this.style.borderColor = 'rgba(34, 230, 168, 0.5)';
                this.style.boxShadow = '0 0 0 4px rgba(34, 230, 168, 0.1)';
            } else {
                this.style.borderColor = '';
                this.style.boxShadow = '';
            }
        });
    });
}

/**
 * Create ripple effect on button click
 */
function createRipple(button, event) {
    const ripple = document.createElement('span');
    const rect = button.getBoundingClientRect();
    const size = Math.max(rect.width, rect.height);
    const x = event.clientX - rect.left - size / 2;
    const y = event.clientY - rect.top - size / 2;
    
    ripple.style.width = ripple.style.height = size + 'px';
    ripple.style.left = x + 'px';
    ripple.style.top = y + 'px';
    ripple.classList.add('ripple');
    
    const existingRipple = button.querySelector('.ripple');
    if (existingRipple) {
        existingRipple.remove();
    }
    
    button.appendChild(ripple);
    
    setTimeout(() => {
        ripple.remove();
    }, 600);
}

/**
 * Smooth scroll to element
 */
function smoothScrollTo(element, offset = 0) {
    const targetPosition = element.getBoundingClientRect().top + window.pageYOffset - offset;
    
    window.scrollTo({
        top: targetPosition,
        behavior: 'smooth'
    });
}

/**
 * Show loading state on form submit
 */
function initFormLoading() {
    const forms = document.querySelectorAll('form[data-prevent-double]');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitButton = this.querySelector('button[type="submit"]');
            const spinner = submitButton.querySelector('.spinner-border');
            
            if (spinner) {
                spinner.classList.remove('d-none');
                submitButton.disabled = true;
            }
        });
    });
}

function initTreatmentWizard() {
    const wizard = document.querySelector('[data-treatment-wizard]');
    if (!wizard) return;

    const steps = Array.from(wizard.querySelectorAll('[data-wizard-target]'));
    const panels = Array.from(wizard.querySelectorAll('[data-wizard-panel]'));
    const prevBtn = wizard.querySelector('[data-wizard-prev]');
    const nextBtn = wizard.querySelector('[data-wizard-next]');
    const submitBtn = wizard.querySelector('[data-wizard-submit]');
    const procedureList = wizard.querySelector('[data-procedure-list]');
    const addProcedureBtn = wizard.querySelector('[data-add-procedure]');
    let currentStep = 1;

    function showStep(step) {
        currentStep = Math.min(4, Math.max(1, step));
        steps.forEach(btn => btn.classList.toggle('is-active', Number(btn.dataset.wizardTarget) === currentStep));
        panels.forEach(panel => panel.classList.toggle('is-active', Number(panel.dataset.wizardPanel) === currentStep));

        if (prevBtn) prevBtn.disabled = currentStep === 1;
        if (nextBtn) nextBtn.classList.toggle('d-none', currentStep === 4);
        if (submitBtn) submitBtn.classList.toggle('d-none', currentStep !== 4);
        updateWizardSummary();
        updateFinancePreview();
    }

    steps.forEach(btn => {
        btn.addEventListener('click', () => showStep(Number(btn.dataset.wizardTarget)));
    });

    prevBtn?.addEventListener('click', () => showStep(currentStep - 1));
    nextBtn?.addEventListener('click', () => showStep(currentStep + 1));

    addProcedureBtn?.addEventListener('click', () => {
        if (!procedureList) return;
        const firstCard = procedureList.querySelector('.procedure-card');
        if (!firstCard) return;

        const clone = firstCard.cloneNode(true);
        clone.querySelectorAll('input, textarea').forEach(input => {
            input.value = input.name.includes('procedimiento_sesiones') ? '1' : '';
        });
        procedureList.appendChild(clone);
        renumberProcedures();
    });

    procedureList?.addEventListener('click', event => {
        const remove = event.target.closest('[data-remove-procedure]');
        if (!remove || !procedureList) return;
        const cards = procedureList.querySelectorAll('.procedure-card');
        if (cards.length <= 1) {
            cards[0].querySelectorAll('input, textarea').forEach(input => {
                input.value = input.name.includes('procedimiento_sesiones') ? '1' : '';
            });
            updateFinancePreview();
            return;
        }
        remove.closest('.procedure-card')?.remove();
        renumberProcedures();
        updateFinancePreview();
    });

    wizard.addEventListener('input', event => {
        if (event.target.matches('[data-cost-input]')) {
            syncTotalFromProcedures();
        }
        updateWizardSummary();
        updateFinancePreview();
    });
    wizard.addEventListener('change', () => {
        updateWizardSummary();
        updateFinancePreview();
    });

    function renumberProcedures() {
        procedureList?.querySelectorAll('.procedure-card').forEach((card, index) => {
            const title = card.querySelector('.procedure-card-head strong');
            if (title) title.textContent = `Procedimiento ${index + 1}`;
        });
    }

    function syncTotalFromProcedures() {
        const totalField = wizard.querySelector('[data-total-cost]');
        const costInputs = Array.from(wizard.querySelectorAll('[data-cost-input]'));
        const total = costInputs.reduce((sum, input) => sum + normalizeMoney(input.value), 0);
        if (totalField && total > 0) {
            totalField.value = String(Math.round(total));
        }
    }

    function updateFinancePreview() {
        const total = normalizeMoney(wizard.querySelector('[data-total-cost]')?.value);
        const paid = normalizeMoney(wizard.querySelector('[data-initial-payment]')?.value);
        const safePaid = Math.min(paid, total);
        const pending = Math.max(0, total - safePaid);
        const percent = total > 0 ? Math.min(100, Math.round((safePaid / total) * 100)) : 0;

        setText('[data-finance-paid]', formatMoney(safePaid));
        setText('[data-finance-total]', formatMoney(total));
        setText('[data-finance-pending]', formatMoney(pending));
        const bar = wizard.querySelector('[data-finance-bar]');
        if (bar) bar.style.width = `${percent}%`;
    }

    function updateWizardSummary() {
        const summaryFields = wizard.querySelectorAll('[data-summary]');
        summaryFields.forEach(field => {
            const key = field.dataset.summary;
            let value = field.value || '';
            if (field.tagName === 'SELECT') {
                value = field.options[field.selectedIndex]?.text || '';
            }
            if (key === 'costo') {
                value = formatMoney(normalizeMoney(value));
            }
            setText(`[data-summary-output="${key}"]`, value || 'No definido');
        });
    }

    function setText(selector, value) {
        const node = wizard.querySelector(selector);
        if (node) node.textContent = value;
    }

    function normalizeMoney(value) {
        const number = Number.parseFloat(String(value || '0').replace(/[^\d.]/g, ''));
        return Number.isFinite(number) ? number : 0;
    }

    function formatMoney(value) {
        return new Intl.NumberFormat('es-CO', {
            style: 'currency',
            currency: 'COP',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(value || 0);
    }

    showStep(1);
}

/**
 * Add CSS for ripple effect dynamically
 */
const style = document.createElement('style');
style.textContent = `
    .ripple {
        position: absolute;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.4);
        transform: scale(0);
        animation: ripple-animation 0.6s linear;
        pointer-events: none;
    }
    
    @keyframes ripple-animation {
        to {
            transform: scale(4);
            opacity: 0;
        }
    }
    
    .treatment-input:valid,
    .treatment-select:valid {
        border-color: rgba(34, 230, 168, 0.5);
    }
    
    .treatment-input:invalid:not(:placeholder-shown),
    .treatment-select:invalid:not(:placeholder-shown) {
        border-color: rgba(255, 115, 115, 0.5);
        animation: shake 0.3s ease;
    }
    
    @keyframes shake {
        0%, 100% { transform: translateX(0); }
        25% { transform: translateX(-5px); }
        75% { transform: translateX(5px); }
    }
`;
document.head.appendChild(style);
