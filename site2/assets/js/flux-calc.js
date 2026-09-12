/**
 * flux-calc.js — Интерактивный верстак: калькулятор расхода флюса и паяльной пасты (IPC-7095C)
 * Реактивный расчет в реальном времени, пресеты плат, масштабируемый SVG и буфер обмена
 */
document.addEventListener('DOMContentLoaded', () => {
  const slider = document.getElementById('flux-area-slider');
  const numberInput = document.getElementById('flux-area');
  const typeSelect = document.getElementById('flux-type');
  const presetButtons = document.querySelectorAll('.preset-btn');
  const batchButtons = document.querySelectorAll('.batch-btn');

  const resVolumeEl = document.getElementById('res-volume');
  const resDescEl = document.getElementById('res-desc');
  const batchNoteEl = document.getElementById('batch-note');
  const washTipEl = document.getElementById('wash-tip');
  const pcbDimensionsEl = document.getElementById('pcb-dimensions');
  const pcbSvg = document.getElementById('pcb-svg');
  const copyBtn = document.getElementById('copy-flux-btn');
  const copyText = document.getElementById('copy-flux-text');

  // Matrix elements
  const matrixArea = document.getElementById('matrix-area');
  const matrixBga = document.getElementById('matrix-bga');
  const matrixRma = document.getElementById('matrix-rma');
  const matrixPaste = document.getElementById('matrix-paste');
  const matrixWs = document.getElementById('matrix-ws');

  let currentArea = 35;
  let currentBatch = 1;
  let currentType = 'bga_nc';

  function updateCalculator() {
    if (numberInput) {
      currentArea = parseFloat(numberInput.value) || 35;
    }
    if (typeSelect) {
      currentType = typeSelect.value;
    }

    // Boundary constraints
    if (currentArea < 1) currentArea = 1;
    if (currentArea > 500) currentArea = 500;

    // Rates per cm2
    const rates = {
      bga_nc: { rate: 0.005, unit: 'мл', name: 'Гель BGA (NC-559)', wash: 'Отмывка: опциональна (No-Clean)', desc: `Для ${currentArea} см² при BGA реболлинге наносите тонкий слой 50-70 мкм. Избыток вызывает кипение и сдвиг чипа.` },
      smd_rma: { rate: 0.008, unit: 'мл', name: 'Канифольный (RMA-223)', wash: 'Отмывка: изопропиловый спирт (IPA 99.7%)', desc: `Для ${currentArea} см² SMD монтажа наносите кистью или дозатором. Обязательна отмывка от ионных остатков канифоли.` },
      paste_sac: { rate: 0.015, unit: 'г', name: 'Паста SAC305', wash: 'Отмывка: по типу флюса в пасте', desc: `Для ${currentArea} см² через трафарет (толщина 120 мкм, апертура 85%) потребуется паяльная паста со сферическими частицами Type 4.` },
      clean_ws: { rate: 0.006, unit: 'мл', name: 'Водосмывной WS', wash: 'Отмывка: деионизированная вода в УЗ-ванне (50°C)', desc: `Для ${currentArea} см² высокая активность смывает стойкие оксиды. Не оставляйте неотмытым более 2 часов (коррозия!).` }
    };

    const cfg = rates[currentType] || rates.bga_nc;
    const singleVol = currentArea * cfg.rate;
    const totalVol = singleVol * currentBatch;

    // Output main results
    if (resVolumeEl) {
      const volStr = totalVol < 0.1 ? totalVol.toFixed(3) : totalVol.toFixed(2);
      resVolumeEl.textContent = `~${volStr} ${cfg.unit}`;
    }

    if (batchNoteEl) {
      batchNoteEl.textContent = currentBatch > 1 ? `(на партию из ${currentBatch} плат)` : '(на 1 плату)';
    }

    if (resDescEl) {
      resDescEl.textContent = cfg.desc;
    }

    if (washTipEl) {
      washTipEl.textContent = cfg.wash;
    }

    // PCB Visualization update
    const sideMm = Math.round(Math.sqrt(currentArea * 100));
    if (pcbDimensionsEl) {
      pcbDimensionsEl.textContent = `${sideMm} × ${sideMm} мм (${currentArea} см²)`;
    }

    if (pcbSvg) {
      // Dynamic SVG size between 50px and 125px
      const svgSize = Math.min(125, Math.max(50, 50 + (currentArea / 200) * 75));
      pcbSvg.setAttribute('width', Math.round(svgSize));
      pcbSvg.setAttribute('height', Math.round(svgSize));
    }

    // Comparison matrix update
    if (matrixArea) matrixArea.textContent = `${currentArea} см²`;
    if (matrixBga) matrixBga.textContent = `${(currentArea * rates.bga_nc.rate * currentBatch).toFixed(2)} мл`;
    if (matrixRma) matrixRma.textContent = `${(currentArea * rates.smd_rma.rate * currentBatch).toFixed(2)} мл`;
    if (matrixPaste) matrixPaste.textContent = `${(currentArea * rates.paste_sac.rate * currentBatch).toFixed(2)} г`;
    if (matrixWs) matrixWs.textContent = `${(currentArea * rates.clean_ws.rate * currentBatch).toFixed(2)} мл`;
  }

  // Slider events
  if (slider && numberInput) {
    slider.addEventListener('input', (e) => {
      numberInput.value = e.target.value;
      updateCalculator();
      highlightActivePreset(e.target.value);
    });

    numberInput.addEventListener('input', (e) => {
      slider.value = e.target.value;
      updateCalculator();
      highlightActivePreset(e.target.value);
    });
  }

  // Type change
  if (typeSelect) {
    typeSelect.addEventListener('change', updateCalculator);
  }

  // Preset Buttons
  function highlightActivePreset(val) {
    presetButtons.forEach(btn => {
      const pArea = btn.getAttribute('data-area');
      if (parseFloat(pArea) === parseFloat(val)) {
        btn.className = 'preset-btn px-3 py-1.5 rounded border border-ink bg-ink text-paper font-medium transition-all shadow-sm';
      } else {
        btn.className = 'preset-btn px-3 py-1.5 rounded border border-paper-border hover:border-ink transition-all bg-paper text-ink';
      }
    });
  }

  presetButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      const areaVal = btn.getAttribute('data-area');
      if (numberInput) numberInput.value = areaVal;
      if (slider) slider.value = areaVal;
      highlightActivePreset(areaVal);
      updateCalculator();
    });
  });

  // Batch Multiplier Buttons
  batchButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      currentBatch = parseInt(btn.getAttribute('data-qty'), 10) || 1;
      batchButtons.forEach(b => {
        b.className = 'batch-btn px-3.5 py-1 text-xs font-mono rounded border border-paper-border bg-paper text-ink hover:border-ink cursor-pointer';
      });
      btn.className = 'batch-btn px-3.5 py-1 text-xs font-mono rounded border border-ink bg-ink text-paper font-semibold cursor-pointer';
      updateCalculator();
    });
  });

  // Copy to clipboard
  if (copyBtn) {
    copyBtn.addEventListener('click', () => {
      const rates = {
        bga_nc: 'Гель BGA (NC-559)',
        smd_rma: 'Канифольный (RMA-223)',
        paste_sac: 'Паста SAC305',
        clean_ws: 'Водосмывной WS'
      };
      const typeName = rates[currentType] || currentType;
      const volText = resVolumeEl ? resVolumeEl.textContent : '';
      const text = `ТОЧКА ПЛАВЛЕНИЯ // Расчет дозировки: Площадь: ${currentArea} см² | Состав: ${typeName} | Серия: ${currentBatch} шт | Дозировка: ${volText}`;

      navigator.clipboard.writeText(text).then(() => {
        if (copyText) {
          copyText.textContent = 'Скопировано!';
          setTimeout(() => {
            copyText.textContent = 'Копировать';
          }, 2000);
        }
      });
    });
  }

  // Initial calculation run
  updateCalculator();
});
