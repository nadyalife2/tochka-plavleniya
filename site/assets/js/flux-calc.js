/**
 * flux-calc.js — Калькулятор флюса
 * Точка Плавления
 */
(function () {
  const form   = document.getElementById('flux-calc-form');
  const result = document.getElementById('flux-result');
  if (!form) return;

  const FLUX_DENSITY = {
    'rma223':     { name: 'RMA-223', coverage: 0.06, application: 'нанесите шприцем точечно на контакты' },
    'nc559':      { name: 'NC-559',  coverage: 0.05, application: 'нанесите кистью тонким слоем' },
    'flux_paste': { name: 'Flux Paste', coverage: 0.08, application: 'нанесите шпателем на площадки' },
    'liquid':     { name: 'Жидкий флюс', coverage: 0.04, application: 'нанесите кистью или капельницей' },
  };

  form.addEventListener('submit', e => {
    e.preventDefault();
    calculate();
  });

  // Also calculate on any input change
  form.querySelectorAll('select, input').forEach(el => {
    el.addEventListener('input', calculate);
    el.addEventListener('change', calculate);
  });

  function calculate() {
    const fluxType = form.querySelector('#flux-type')?.value;
    const area     = parseFloat(form.querySelector('#board-area')?.value) || 0;
    const compType = form.querySelector('#comp-type')?.value;

    if (!fluxType || area <= 0) {
      result.classList.remove('visible');
      return;
    }

    const flux = FLUX_DENSITY[fluxType];
    if (!flux) return;

    let multiplier = 1;
    if (compType === 'smd') multiplier = 1.2;
    if (compType === 'bga') multiplier = 1.5;
    if (compType === 'mix') multiplier = 1.35;

    const amount = (area * flux.coverage * multiplier).toFixed(2);
    const resultValue = document.getElementById('result-value');
    const resultDesc  = document.getElementById('result-desc');

    if (resultValue) resultValue.textContent = amount + ' мл';
    if (resultDesc) resultDesc.textContent = flux.name + ': ' + flux.application + '. Площадь: ' + area + ' см².';

    result.classList.add('visible');
  }
})();
