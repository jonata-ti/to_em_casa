(function () {
  function sanitizeMoneyText(rawValue) {
    if (rawValue === null || rawValue === undefined) {
      return '';
    }

    return String(rawValue)
      .replace(/R\$/gi, '')
      .replace(/\s+/g, '')
      .replace(/[^\d,.-]/g, '');
  }

  function normalizeMoneyValue(rawValue) {
    const value = sanitizeMoneyText(rawValue);
    if (value === '' || value === '-' || value === '.' || value === ',' || value === '-.' || value === '-,') {
      return '0.00';
    }

    const negative = value.startsWith('-');
    const digits = value.replace(/-/g, '').replace(/\./g, '').replace(/,/g, '');

    if (!/^\d+$/.test(digits)) {
      return '0.00';
    }

    const numeric = Number(digits) / 100;
    if (!Number.isFinite(numeric)) {
      return '0.00';
    }

    return (negative ? -numeric : numeric).toFixed(2);
  }

  function formatMoneyDisplay(rawValue) {
    const numeric = Number(normalizeMoneyValue(rawValue));
    if (!Number.isFinite(numeric)) {
      return 'R$ 0,00';
    }

    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL'
    }).format(numeric);
  }

  function applyMaskValue(input) {
    const normalized = normalizeMoneyValue(input.value || '0');
    input.dataset.normalized = normalized;
    input.value = formatMoneyDisplay(normalized);
    return normalized;
  }

  function attachMoneyMask(selector) {
    const elements = typeof selector === 'string'
      ? document.querySelectorAll(selector)
      : selector instanceof Element
        ? [selector]
        : Array.from(selector || []);

    elements.forEach((input) => {
      if (!input || input.dataset.moneyMaskBound === 'true') {
        return;
      }

      input.dataset.moneyMaskBound = 'true';
      input.type = 'text';
      input.inputMode = 'decimal';
      input.autocomplete = 'off';
      input.spellcheck = false;

      const initialValue = input.value || '0';
      input.dataset.normalized = normalizeMoneyValue(initialValue);
      input.value = formatMoneyDisplay(initialValue);

      input.addEventListener('input', function () {
        const current = sanitizeMoneyText(this.value || '');
        if (current === '') {
          this.dataset.normalized = '0.00';
          this.value = 'R$ 0,00';
          return;
        }

        const masked = Number(current.replace(/\D/g, '')) / 100;
        const normalized = Number.isFinite(masked) ? masked.toFixed(2) : '0.00';
        this.dataset.normalized = normalized;
        this.value = formatMoneyDisplay(normalized);
      });

      input.addEventListener('blur', function () {
        applyMaskValue(this);
      });

      input.addEventListener('paste', function (event) {
        const pasted = (event.clipboardData || window.clipboardData).getData('text') || '';
        if (/[^\d,.-]/.test(pasted.replace(/R\$/gi, ''))) {
          event.preventDefault();
        }
      });
    });
  }

  function normalizeFieldValue(field) {
    if (!field) {
      return '0.00';
    }

    const normalized = normalizeMoneyValue(field.value || '0');
    field.dataset.normalized = normalized;
    field.value = normalized;
    return normalized;
  }

  window.moneyMask = {
    normalizeMoneyValue,
    formatMoneyDisplay,
    attachMoneyMask,
    normalizeFieldValue
  };
})();
