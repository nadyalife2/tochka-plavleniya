/**
 * flux-calc.js — Калькулятор расхода паяльной пасты и флюса
 * Физическая модель апертурного переноса (Indium Corporation / IPC-7525B Stencil Design Guide)
 * 
 * Модель расчёта пасты:
 *   S_board    = площадь платы / монтажной зоны (см²)
 *   k_aperture = доля площади контактных площадок (~18% для типового SMD монтажа)
 *   h_stencil  = толщина металлического трафарета (100, 120, 130, 150 мкм)
 *   eta        = коэффициент выхода пасты из апертур (Transfer Efficiency ~80% по IPC-7525)
 *   rho_paste  = плотность паяльной пасты:
 *                - SAC305 (88.5% металл): ~4.35 г/см³
 *                - Sn63Pb37 (90% металл): ~4.80 г/см³
 *   k_waste    = технологический отход на ракеле, остаток на стенках и промывку (+20%, k = 1.20)
 * 
 *   V_theor = S_board * k_aperture * (h_stencil / 10000)   [см³ = мл]
 *   V_dep   = V_theor * eta                                [см³ = мл]
 *   M_board = V_dep * rho_paste * k_waste                  [г]
 *   M_total = M_board * batch_count                        [г]
 * 
 * Модель дозирования флюсов:
 *   - BGA реболлинг/монтаж чипов (NC-559, ROL0): равномерная пленка 50–70 мкм (0.006 мл/см²)
 *   - SMD ручное нанесение RMA (ROM1): дозатор/кисть на площадки (0.008 мл/см²)
 *   - Водосмывной жидкий/гель WS (ORH1): нанесение под смыв (0.006 мл/см²)
 */

document.addEventListener('DOMContentLoaded', () => {
  const slider = document.getElementById('flux-area-slider');
  const numberInput = document.getElementById('flux-area');
  const typeSelect = document.getElementById('flux-type');
  const stencilSelect = document.getElementById('stencil-thickness');
  const stencilGroup = document.getElementById('stencil-thickness-group');
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
  let currentType = 'paste_sac';
  let currentStencilH = 120; // мкм

  function calculatePasteMass(areaCm2, stencilHUm, alloyType, batchQty) {
    // k_aperture = 0.18 (18% площадок от площади платы)
    const kAperture = 0.18;
    const hCm = stencilHUm / 10000; // мкм -> см
    const etaTransfer = 0.80; // 80% per IPC-7525
    const kWaste = 1.20; // +20% технологический отход на валике ракеля и смыв
    const rho = alloyType === 'paste_sn63' ? 4.80 : 4.35; // г/см³

    const vTheorCm3 = areaCm2 * kAperture * hCm;
    const vDepCm3 = vTheorCm3 * etaTransfer;
    const mBoardG = vDepCm3 * rho * kWaste;
    return mBoardG * batchQty;
  }

  function calculateFluxVolume(areaCm2, rateMlPerCm2, batchQty) {
    return areaCm2 * rateMlPerCm2 * batchQty;
  }

  function updateCalculator() {
    // Безопасное считывание числа без перезаписи на 35 при пустом/нулевом вводе
    if (numberInput) {
      const raw = numberInput.value.trim();
      if (raw !== '') {
        const parsed = parseFloat(raw);
        if (!isNaN(parsed) && parsed > 0) {
          currentArea = Math.min(500, Math.max(1, parsed));
        }
      }
    }

    if (typeSelect) {
      currentType = typeSelect.value;
    }

    if (stencilSelect) {
      currentStencilH = parseInt(stencilSelect.value, 10) || 120;
    }

    const isPaste = currentType.startsWith('paste_');
    if (stencilGroup) {
      stencilGroup.style.display = isPaste ? 'block' : 'none';
    }

    let resultValueStr = '';
    let resultUnit = '';
    let resultDesc = '';
    let washText = '';

    if (currentType === 'paste_sac' || currentType === 'paste_sn63') {
      const isLead = currentType === 'paste_sn63';
      const alloyName = isLead ? 'Sn63Pb37 (эвтектика, 90% металла)' : 'SAC305 (Sn96.5Ag3Cu0.5, 88.5% металла)';
      const massG = calculatePasteMass(currentArea, currentStencilH, currentType, currentBatch);
      resultValueStr = massG < 1 ? massG.toFixed(2) : massG.toFixed(1);
      resultUnit = 'г';
      washText = isLead 
        ? 'Отмывка: зависит от флюса в пасте (RMA — IPA 99.7%, No-Clean — по требованиям изделия)' 
        : 'Отмывка: зависит от классификации флюса в пасте (ROL0 / REL0 обычно не требуют смыва)';
      resultDesc = `Расчёт по модели Indium Corp для ${currentArea} см²: трафарет ${currentStencilH} мкм, апертурное покрытие 18%, перенос пасты η = 80%, тех. отход на ракеле +20%. Паста: ${alloyName}.`;
    } else if (currentType === 'bga_nc') {
      const volMl = calculateFluxVolume(currentArea, 0.006, currentBatch);
      resultValueStr = volMl < 0.1 ? volMl.toFixed(3) : volMl.toFixed(2);
      resultUnit = 'мл';
      washText = 'Отмывка: опциональна для Class 1/2 (ROL0 / NC-559-V2); для Class 3 и перед лакированием — спирто-бензин или IPA';
      resultDesc = `Для ${currentArea} см² BGA реболлинга и монтажа BGA/QFN: тонкий равномерный слой 50–70 мкм. Избыток геля вызывает смещение шариков и подрыв чипа.`;
    } else if (currentType === 'smd_rma') {
      const volMl = calculateFluxVolume(currentArea, 0.008, currentBatch);
      resultValueStr = volMl < 0.1 ? volMl.toFixed(3) : volMl.toFixed(2);
      resultUnit = 'мл';
      washText = 'Отмывка: спирто-бензиновая смесь (1:1) или изопропиловый спирт 99.7%. В сухих условиях Class 1 канифоль химически инертна';
      resultDesc = `Для ${currentArea} см² ручного монтажа: точечное дозирование на контактные площадки. Умеренная активация (ROM1) защищает от окисления.`;
    } else if (currentType === 'clean_ws') {
      const volMl = calculateFluxVolume(currentArea, 0.006, currentBatch);
      resultValueStr = volMl < 0.1 ? volMl.toFixed(3) : volMl.toFixed(2);
      resultUnit = 'мл';
      washText = 'Отмывка: ОБЯЗАТЕЛЬНА деионизированной водой (50–60°C в УЗ-ванне) не позднее 2–4 часов после пайки!';
      resultDesc = `Для ${currentArea} см² водосмывной химии (ORH1): водорастворимый флюс агрессивно смывает тяжелые оксиды. Неотмытые остатки гигроскопичны и электропроводны.`;
    }

    // Output main results
    if (resVolumeEl) {
      resVolumeEl.textContent = `~${resultValueStr} ${resultUnit}`;
    }

    if (batchNoteEl) {
      batchNoteEl.textContent = currentBatch > 1 ? `(на партию из ${currentBatch} плат)` : '(на 1 плату)';
    }

    if (resDescEl) {
      resDescEl.textContent = resultDesc;
    }

    if (washTipEl) {
      washTipEl.textContent = washText;
    }

    // PCB Visualization update
    const sideMm = Math.round(Math.sqrt(currentArea * 100));
    if (pcbDimensionsEl) {
      pcbDimensionsEl.textContent = `${sideMm} × ${sideMm} мм (${currentArea} см²)`;
    }

    if (pcbSvg) {
      const svgSize = Math.min(125, Math.max(50, 50 + (currentArea / 200) * 75));
      pcbSvg.setAttribute('width', Math.round(svgSize));
      pcbSvg.setAttribute('height', Math.round(svgSize));
    }

    // Comparison matrix update (per current area and batch)
    if (matrixArea) matrixArea.textContent = `${currentArea} см²`;
    if (matrixBga) {
      const bgaVol = calculateFluxVolume(currentArea, 0.006, currentBatch);
      matrixBga.textContent = `${bgaVol < 0.1 ? bgaVol.toFixed(3) : bgaVol.toFixed(2)} мл`;
    }
    if (matrixRma) {
      const rmaVol = calculateFluxVolume(currentArea, 0.008, currentBatch);
      matrixRma.textContent = `${rmaVol < 0.1 ? rmaVol.toFixed(3) : rmaVol.toFixed(2)} мл`;
    }
    if (matrixPaste) {
      const pasteM = calculatePasteMass(currentArea, currentStencilH, 'paste_sac', currentBatch);
      matrixPaste.textContent = `${pasteM < 1 ? pasteM.toFixed(2) : pasteM.toFixed(1)} г`;
    }
    if (matrixWs) {
      const wsVol = calculateFluxVolume(currentArea, 0.006, currentBatch);
      matrixWs.textContent = `${wsVol < 0.1 ? wsVol.toFixed(3) : wsVol.toFixed(2)} мл`;
    }
  }

  // Slider and number events
  if (slider && numberInput) {
    slider.addEventListener('input', (e) => {
      numberInput.value = e.target.value;
      updateCalculator();
      highlightActivePreset(e.target.value);
    });

    numberInput.addEventListener('input', (e) => {
      const val = parseFloat(e.target.value);
      if (!isNaN(val) && val >= 1 && val <= 500) {
        if (slider) slider.value = Math.min(200, Math.max(1, val));
        updateCalculator();
        highlightActivePreset(val);
      }
    });

    numberInput.addEventListener('blur', () => {
      const raw = numberInput.value.trim();
      if (raw === '' || isNaN(parseFloat(raw)) || parseFloat(raw) <= 0) {
        numberInput.value = currentArea;
      } else {
        currentArea = Math.min(500, Math.max(1, parseFloat(raw)));
        numberInput.value = currentArea;
      }
      if (slider) slider.value = Math.min(200, Math.max(1, currentArea));
      updateCalculator();
    });
  }

  // Type change
  if (typeSelect) {
    typeSelect.addEventListener('change', updateCalculator);
  }

  // Stencil thickness change
  if (stencilSelect) {
    stencilSelect.addEventListener('change', updateCalculator);
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
      currentArea = parseFloat(areaVal) || 35;
      if (numberInput) numberInput.value = currentArea;
      if (slider) slider.value = Math.min(200, currentArea);
      highlightActivePreset(currentArea);
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
      const types = {
        paste_sac: 'Паста SAC305',
        paste_sn63: 'Паста Sn63Pb37',
        bga_nc: 'Гель BGA (ROL0)',
        smd_rma: 'Канифольный RMA (ROM1)',
        clean_ws: 'Водосмывной WS (ORH1)'
      };
      const typeName = types[currentType] || currentType;
      const volText = resVolumeEl ? resVolumeEl.textContent : '';
      const text = `ТОЧКА ПЛАВЛЕНИЯ // Дозировка материалов: Площадь монтажа: ${currentArea} см² | Тип: ${typeName} | Трафарет: ${currentStencilH} мкм | Серия: ${currentBatch} шт | Расход: ${volText}`;

      if (window.TCHP_UI && window.TCHP_UI.copyToClipboard) {
        window.TCHP_UI.copyToClipboard(text, copyBtn, 'Скопировано!');
      } else {
        navigator.clipboard.writeText(text).then(() => {
          if (copyText) {
            copyText.textContent = 'Скопировано!';
            setTimeout(() => {
              copyText.textContent = 'Скопировать параметры в журнал';
            }, 2000);
          }
        });
      }
    });
  }

  // Initial calculation run
  updateCalculator();
});
