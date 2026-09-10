document.addEventListener('DOMContentLoaded', () => {
    const btn = document.getElementById('calc-flux-btn');
    if (!btn) return;

    btn.addEventListener('click', (e) => {
        e.preventDefault();
        const areaEl = document.getElementById('flux-area');
        const typeEl = document.getElementById('flux-type');
        const resultDiv = document.getElementById('flux-result');

        if (!areaEl || !typeEl || !resultDiv) return;

        const area = parseFloat(areaEl.value);
        const type = typeEl.value;

        if (!area || area <= 0) {
            resultDiv.style.display = 'block';
            resultDiv.className = 'callout important';
            resultDiv.innerHTML = '<div class="callout-title">⚠️ Ошибка</div><p>Введите корректную площадь платы в см².</p>';
            return;
        }

        // Простая формула: 0.1г на см² для RMA, 0.08г для NC
        const amount = type === 'rma' ? (area * 0.1).toFixed(2) : (area * 0.08).toFixed(2);
        const tip = type === 'rma' ? 'Не забудьте промыть плату изопропиловым спиртом!' : 'Можно не смывать, но промывка улучшит долговечность схемы.';

        resultDiv.style.display = 'block';
        resultDiv.className = 'callout tip';
        resultDiv.innerHTML = `<div class="callout-title">💡 Результат расчёта</div><p>Вам понадобится примерно <strong>${amount} г</strong> флюса. ${tip}</p>`;
    });
});
