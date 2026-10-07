document.addEventListener('DOMContentLoaded', () => {
    document.querySelector('[data-portal-nav]')?.addEventListener('click', () => {
        document.getElementById('portal-nav')?.classList.toggle('is-open');
    });
    const builder = document.querySelector('[data-quote-builder]');
    if (!builder) return;
    const total = builder.querySelector('[data-total]');
    const recalc = () => {
        let sum = 0;
        builder.querySelectorAll('[data-line]').forEach((row) => {
            const qty = parseFloat(row.querySelector('[data-qty]')?.value || '0');
            const price = parseFloat(row.querySelector('[data-price]')?.value || '0');
            const line = Math.round(qty * price * 100) / 100;
            const out = row.querySelector('[data-line-total]');
            if (out) out.textContent = line.toFixed(2);
            sum += line;
        });
        const discount = parseFloat(builder.querySelector('[name="discount_amount"]')?.value || '0');
        const taxRate = parseFloat(builder.querySelector('[name="tax_rate"]')?.value || '0');
        const extra = parseFloat(builder.querySelector('[name="additional_amount"]')?.value || '0');
        const taxable = Math.max(0, sum - discount);
        const grand = taxable + (taxable * taxRate / 100) + extra;
        if (total) total.textContent = grand.toFixed(2);
    };
    builder.addEventListener('input', recalc);
    recalc();
});
