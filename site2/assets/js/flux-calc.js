/**
 * flux-calc.js — Инженерный калькулятор расхода флюса и паяльной пасты (IPC-7095C)
 * Единый скрипт для автономного стенда (site2) и темы WordPress (tchp)
 */
document.addEventListener('DOMContentLoaded', () => {
  const calcBtn = document.getElementById('calc-flux-btn');
  if (!calcBtn) return;

  calcBtn.addEventListener('click', (e) => {
    e.preventDefault();
    const areaEl = document.getElementById('flux-area');
    const typeEl = document.getElementById('flux-type');
    const resVolumeEl = document.getElementById('res-volume');
    const resDescEl = document.getElementById('res-desc');
    const fluxResultDiv = document.getElementById('flux-result');

    const area = parseFloat(areaEl ? areaEl.value : 35) || 35;
    const type = typeEl ? typeEl.value : 'bga_nc';

    let vol = 0;
    let unit = 'мл';
    let desc = '';

    if (type === 'bga_nc' || type === 'nc') {
      vol = (area * 0.005).toFixed(2);
      desc = `Для ${area} см² (BGA реболлинг) требуется ~${vol} мл флюса-геля. Наносите равномерно шпателем толщиной 50-70 мкм.`;
    } else if (type === 'smd_rma' || type === 'rma') {
      vol = (area * 0.008).toFixed(2);
      desc = `Для ${area} см² (SMD монтаж) требуется ~${vol} мл. Требуется обязательная отмывка изопропиловым спиртом после пайки.`;
    } else if (type === 'paste_sac') {
      vol = (area * 0.015).toFixed(2);
      unit = 'г';
      desc = `Для ${area} см² нанесения через трафарет потребуется ~${vol} г паяльной пасты SAC305.`;
    } else if (type === 'clean_ws') {
      vol = (area * 0.006).toFixed(2);
      desc = `Для ${area} см² водосмывного флюса требуется ~${vol} мл. Обязательна деионизированная промывка в УЗ-ванне.`;
    } else {
      vol = (area * 0.006).toFixed(2);
      desc = `Для ${area} см² расчетный расход составляет ~${vol} ${unit}.`;
    }

    if (resVolumeEl) {
      resVolumeEl.textContent = `~${vol} ${unit}`;
    }
    if (resDescEl) {
      resDescEl.textContent = desc;
    }

    if (fluxResultDiv && !resVolumeEl) {
      fluxResultDiv.style.display = 'block';
      fluxResultDiv.className = 'p-4 rounded-lg bg-paper-subtle border border-paper-border font-mono text-xs space-y-2';
      fluxResultDiv.innerHTML = `
        <div class="text-ink font-bold flex items-center justify-between">
          <span>РЕЗУЛЬТАТ РАСЧЕТА ДОЗИРОВКИ:</span>
          <span class="text-accent font-bold">~${vol} ${unit}</span>
        </div>
        <p class="text-ink-muted text-[11px] leading-relaxed">${desc}</p>
      `;
    }
  });
});
