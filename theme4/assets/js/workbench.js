/**
 * workbench.js — Интерактивная логика P0 инструментов верстака ТЧП
 *
 * Обрабатывает:
 *   1. Термокалькулятор (#temp)
 *   2. Подбор паяльника (#iron)
 *   3. Дерево дефектов (#defect)
 */
document.addEventListener('DOMContentLoaded', () => {
  const rules = window.TCHP_RULES || {};
  const tempRules = rules.temp || {};
  const ironRules = rules.iron || {};
  const defectTree = rules.defect || {};
  const fluxRules = rules.flux || {};

  // ───────────────────────────────────────────────────────────────────────────
  // 1. ТЕРМОКАЛЬКУЛЯТОР (#temp)
  // ───────────────────────────────────────────────────────────────────────────
  const tempSolder = document.getElementById('temp-solder');
  const tempWork = document.getElementById('temp-work');
  const tempRangeDisplay = document.getElementById('temp-range-display');
  const tempTipDisplay = document.getElementById('temp-tip-display');
  const tempAdviceDisplay = document.getElementById('temp-advice-display');
  const tempWarnDisplay = document.getElementById('temp-warn-display');
  const tempWarnBox = document.getElementById('temp-warn-box');
  const tempBarFill = document.getElementById('temp-bar-fill');

  const tempEmptyState = document.getElementById('temp-empty-state');
  const tempResultContent = document.getElementById('temp-result-content');
  const tempCalcBtn = document.getElementById('temp-calc-btn');
  const tempMeterBox = document.getElementById('temp-meter-box');
  const tempStatusBadge = document.getElementById('temp-status-badge');
  const tempArticleBox = document.getElementById('temp-article-box');
  const tempArticleLink = document.getElementById('temp-article-link');
  const tempArticleTitle = document.getElementById('temp-article-title');
  const quickTaskButtons = document.querySelectorAll('.quick-task-btn');
  const heatmapContainer = document.getElementById('temp-heatmap-container');
  const thermalCanvas = document.getElementById('thermal-canvas');
  const heatmapTalDisplay = document.getElementById('heatmap-tal-display');
  const heatmapTipTemp = document.getElementById('heatmap-tip-temp');
  const heatmapJointTemp = document.getElementById('heatmap-joint-temp');
  let thermalSim = null;

  let activeQuickTask = 'wire';

  function setQuickTask(taskKey) {
    activeQuickTask = taskKey;
    quickTaskButtons.forEach(btn => {
      if (btn.dataset.task === taskKey) {
        btn.classList.add('bg-accent', 'text-ink');
        btn.classList.remove('bg-paper', 'text-white');
      } else {
        btn.classList.remove('bg-accent', 'text-ink');
        btn.classList.add('bg-paper');
      }
    });

    if (taskKey === 'wire') {
      if (tempWork) tempWork.value = 'wire_medium';
      if (tempSolder) tempSolder.value = 'pos61';
    } else if (taskKey === 'pcb') {
      if (tempWork) tempWork.value = 'smd_medium';
      if (tempSolder) tempSolder.value = 'pos61';
    } else if (taskKey === 'pipe') {
      if (tempWork) tempWork.value = 'copper_pipe';
      if (tempSolder) tempSolder.value = 'pos61';
    } else if (taskKey === 'unknown') {
      if (tempWork) tempWork.value = '';
      if (tempSolder) tempSolder.value = '';
    }
    try {
      localStorage.setItem('tp_temp_task', taskKey);
      if (tempSolder) localStorage.setItem('tp_temp_solder', tempSolder.value);
      if (tempWork) localStorage.setItem('tp_temp_work', tempWork.value);
    } catch(e) {}
    updateTempCalculator();
  }

  quickTaskButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      setQuickTask(btn.dataset.task);
    });
  });

  // ───────────────────────────────────────────────────────────────────────────
  // ИНТЕРАКТИВНЫЙ СИМУЛЯТОР ТЕПЛОПРОВОДНОСТИ (CANVAS 2D HEATMAP // 60 FPS)
  // ───────────────────────────────────────────────────────────────────────────
  class ThermalSimulator {
    constructor(canvas) {
      this.canvas = canvas;
      this.ctx = canvas.getContext('2d', { alpha: false });
      this.w = canvas.width || 480;
      this.h = canvas.height || 130;
      this.cols = 60;
      this.rows = 20;
      this.cellW = this.w / this.cols;
      this.cellH = this.h / this.rows;

      this.grid = new Float32Array(this.cols * this.rows);
      this.prevGrid = new Float32Array(this.cols * this.rows);

      this.ambientTemp = 24.0;
      this.tipTemp = 280.0;
      this.meltTemp = 183.0;
      this.conductivity = 0.22;
      this.coolingRate = 0.018;

      this.tipX = this.cols / 2;
      this.tipY = this.rows / 2;
      this.isContactActive = false;
      this.touchTimeSec = 0.0;
      this.timeAccumulator = 0.0;
      this.lastFrameTime = performance.now();
      this.animId = null;
      this.isRunning = false;
      this.isVisible = true;
      this.shouldRun = false;

      this.palette = this.generateIronbowPalette();
      this.initEvents();
      this.initVisibilityObserver();
      this.reset();
    }

    generateIronbowPalette() {
      const p = new Uint32Array(256);
      for (let i = 0; i < 256; i++) {
        const t = i / 255;
        let r = 0, g = 0, b = 0;
        if (t < 0.25) {
          const f = t / 0.25;
          r = Math.floor(20 + 70 * f);
          g = Math.floor(22 + 20 * f);
          b = Math.floor(35 + 120 * f);
        } else if (t < 0.55) {
          const f = (t - 0.25) / 0.30;
          r = Math.floor(90 + 130 * f);
          g = Math.floor(42 + 60 * f);
          b = Math.floor(155 * (1 - f));
        } else if (t < 0.85) {
          const f = (t - 0.55) / 0.30;
          r = Math.min(255, Math.floor(220 + 35 * f));
          g = Math.floor(102 + 97 * f);
          b = Math.floor(10 * f);
        } else {
          const f = (t - 0.85) / 0.15;
          r = 255;
          g = Math.floor(199 + 56 * f);
          b = Math.floor(150 * f);
        }
        p[i] = (255 << 24) | (b << 16) | (g << 8) | r;
      }
      return p;
    }

    releaseContact() {
      this.isContactActive = false;
      this.isToggleActive = false;
      const heatBtn = document.getElementById('sim-heat-btn');
      const heatLabel = document.getElementById('sim-heat-label');
      if (heatBtn) {
        heatBtn.setAttribute('aria-pressed', 'false');
        heatBtn.classList.remove('ring-2', 'ring-ink');
      }
      if (heatLabel) {
        heatLabel.textContent = '● Начать нагрев';
      }
    }

    setContactActive(active, isToggle = false) {
      if (active) {
        this.isContactActive = true;
        this.isToggleActive = isToggle;
        const heatBtn = document.getElementById('sim-heat-btn');
        const heatLabel = document.getElementById('sim-heat-label');
        if (heatBtn) {
          heatBtn.setAttribute('aria-pressed', 'true');
          heatBtn.classList.add('ring-2', 'ring-ink');
        }
        if (heatLabel) {
          heatLabel.textContent = '■ Остановить нагрев';
        }
        this.start();
      } else {
        this.releaseContact();
      }
    }

    toggleHeating() {
      if (this.isContactActive) {
        this.releaseContact();
      } else {
        this.setContactActive(true, true);
      }
    }

    reset() {
      for (let i = 0; i < this.grid.length; i++) {
        this.grid[i] = this.ambientTemp;
        this.prevGrid[i] = this.ambientTemp;
      }
      this.touchTimeSec = 0;
      this.timeAccumulator = 0;
      this.lastFrameTime = performance.now();
      this.releaseContact();
    }

    configure(tipTemp, meltTemp, workType) {
      this.reset();
      this.tipTemp = tipTemp || 280;
      this.meltTemp = meltTemp || 183;

      // Von Neumann stability criterion: k <= 0.25 for 2D forward Euler
      if (workType === 'smd_small') {
        this.conductivity = 0.08;
        this.coolingRate = 0.025;
      } else if (workType === 'smd_large' || workType === 'copper_pipe') {
        this.conductivity = 0.20;
        this.coolingRate = 0.035;
      } else if (workType && workType.startsWith('wire')) {
        this.conductivity = 0.16;
        this.coolingRate = 0.018;
      } else {
        this.conductivity = 0.14;
        this.coolingRate = 0.020;
      }

      this.start();
    }

    initEvents() {
      const getPos = (e) => {
        const rect = this.canvas.getBoundingClientRect();
        const clientX = e.clientX !== undefined ? e.clientX : (e.touches ? e.touches[0].clientX : rect.left + rect.width / 2);
        const clientY = e.clientY !== undefined ? e.clientY : (e.touches ? e.touches[0].clientY : rect.top + rect.height / 2);
        const x = Math.max(0, Math.min(this.w, (clientX - rect.left) * (this.w / rect.width)));
        const y = Math.max(0, Math.min(this.h, (clientY - rect.top) * (this.h / rect.height)));
        return {
          c: Math.floor(x / this.cellW),
          r: Math.floor(y / this.cellH)
        };
      };

      // Pointer Events для объединения мыши, стилуса и тачскрина на холсте
      this.canvas.addEventListener('pointerdown', (e) => {
        try { this.canvas.setPointerCapture(e.pointerId); } catch(err) {}
        const pos = getPos(e);
        this.tipX = pos.c;
        this.tipY = pos.r;
        this.setContactActive(true, false);
      });

      this.canvas.addEventListener('pointermove', (e) => {
        const pos = getPos(e);
        this.tipX = pos.c;
        this.tipY = pos.r;
        // Координаты обновляются без перезапуска цикла, не сбивая delta-time
      });

      this.canvas.addEventListener('pointerup', (e) => {
        try { this.canvas.releasePointerCapture(e.pointerId); } catch(err) {}
        if (!this.isToggleActive) {
          this.releaseContact();
        }
      });

      this.canvas.addEventListener('pointercancel', (e) => {
        try { this.canvas.releasePointerCapture(e.pointerId); } catch(err) {}
        if (!this.isToggleActive) {
          this.releaseContact();
        }
      });

      this.canvas.addEventListener('pointerleave', (e) => {
        if (!this.canvas.hasPointerCapture || !this.canvas.hasPointerCapture(e.pointerId)) {
          if (!this.isToggleActive) {
            this.releaseContact();
          }
        }
      });

      // Аппаратные кнопки доступности (клавиатура / клик-переключатель)
      const heatBtn = document.getElementById('sim-heat-btn');
      const releaseBtn = document.getElementById('sim-release-btn');
      const resetBtn = document.getElementById('sim-reset-btn');

      if (heatBtn) {
        heatBtn.addEventListener('click', (e) => {
          e.preventDefault();
          this.toggleHeating();
        });
        heatBtn.addEventListener('keydown', (e) => {
          if (e.key === ' ' || e.key === 'Enter') {
            e.preventDefault();
            this.toggleHeating();
          }
        });
      }

      if (releaseBtn) {
        releaseBtn.addEventListener('click', () => {
          this.releaseContact();
        });
      }

      if (resetBtn) {
        resetBtn.addEventListener('click', () => {
          this.reset();
          this.render();
          if (heatmapTalDisplay) heatmapTalDisplay.textContent = 'TAL: 0.0 с';
          if (heatmapJointTemp) heatmapJointTemp.textContent = `${Math.round(this.ambientTemp)} °C ○ ТВЁРДОЕ`;
        });
      }

      // Гарантированный сброс контакта при потере фокуса страницы
      window.addEventListener('blur', () => {
        if (!this.isToggleActive) {
          this.releaseContact();
        }
      });

      // Сворачивание / разворачивание модели
      const toggleBtn = document.getElementById('toggle-sim-btn');
      const simBody = document.getElementById('temp-heatmap-body');
      const toggleStatus = document.getElementById('toggle-sim-status');
      if (toggleBtn && simBody) {
        toggleBtn.addEventListener('click', () => {
          const isHidden = simBody.classList.contains('hidden');
          if (isHidden) {
            simBody.classList.remove('hidden');
            toggleBtn.setAttribute('aria-expanded', 'true');
            if (toggleStatus) toggleStatus.textContent = 'Скрыть симуляцию ↑';
            this.start();
          } else {
            simBody.classList.add('hidden');
            toggleBtn.setAttribute('aria-expanded', 'false');
            if (toggleStatus) toggleStatus.textContent = 'Показать симуляцию ↓';
            this.releaseContact();
            this.stop();
          }
        });
      }
    }

    step() {
      const now = performance.now();
      let frameTime = (now - (this.lastFrameTime || now)) / 1000;
      if (frameTime > 0.2) frameTime = 0.2; // Предотвращение лавинообразных скачков при зависании вкладки
      this.lastFrameTime = now;
      this.timeAccumulator += frameTime;

      // Фиксированный шаг моделирования (Fixed DT = 1/60 c) с субстеппингом для стабильности физики
      const FIXED_DT = 1 / 60;
      let substeps = 0;
      while (this.timeAccumulator >= FIXED_DT && substeps < 4) {
        this.substep(FIXED_DT);
        this.timeAccumulator -= FIXED_DT;
        substeps++;
      }

      // Обновление контрольных приборов интерфейса
      const cols = this.cols;
      const rows = this.rows;
      const padX = Math.floor(cols / 2);
      const padY = Math.floor(rows / 2);
      const padTemp = this.grid[padY * cols + padX];

      if (heatmapTipTemp) heatmapTipTemp.textContent = `${Math.round(this.tipTemp)} °C`;
      if (heatmapJointTemp) {
        const isLiquid = padTemp >= this.meltTemp;
        heatmapJointTemp.textContent = `${Math.round(padTemp)} °C ${isLiquid ? '● РАСПЛАВ' : '○ ТВЁРДОЕ'}`;
        heatmapJointTemp.className = isLiquid ? 'text-accent font-bold' : 'text-cyan font-bold';
      }
      if (heatmapTalDisplay) {
        heatmapTalDisplay.textContent = `TAL: ${this.touchTimeSec.toFixed(1)} с`;
      }
    }

    substep(dt) {
      const cols = this.cols;
      const rows = this.rows;
      const prev = this.prevGrid;
      const curr = this.grid;
      const k = this.conductivity;
      const loss = this.coolingRate;
      const ambient = this.ambientTemp;

      prev.set(curr);

      const tx = Math.max(1, Math.min(cols - 2, Math.floor(this.tipX)));
      const ty = Math.max(1, Math.min(rows - 2, Math.floor(this.tipY)));

      // Нагрев подаётся строго при активном контакте жала
      if (this.isContactActive) {
        prev[ty * cols + tx] = this.tipTemp;
        if (tx > 0) prev[ty * cols + (tx - 1)] = Math.max(prev[ty * cols + (tx - 1)], this.tipTemp * 0.92);
        if (tx < cols - 1) prev[ty * cols + (tx + 1)] = Math.max(prev[ty * cols + (tx + 1)], this.tipTemp * 0.92);
        if (ty > 0) prev[(ty - 1) * cols + tx] = Math.max(prev[(ty - 1) * cols + tx], this.tipTemp * 0.92);
        if (ty < rows - 1) prev[(ty + 1) * cols + tx] = Math.max(prev[(ty + 1) * cols + tx], this.tipTemp * 0.92);
      }

      // 2D-уравнение теплопроводности (разностная схема Лапласа)
      for (let r = 1; r < rows - 1; r++) {
        const rowOffset = r * cols;
        for (let c = 1; c < cols - 1; c++) {
          const idx = rowOffset + c;
          const left = prev[idx - 1];
          const right = prev[idx + 1];
          const up = prev[idx - cols];
          const down = prev[idx + cols];
          const center = prev[idx];

          const laplacian = left + right + up + down - 4 * center;
          let newT = center + k * laplacian - loss * (center - ambient);
          curr[idx] = Math.max(ambient, Math.min(this.tipTemp + 20, newT));
        }
      }

      // Граничные условия: естественный теплоотвод в текстолит и окружающую среду по периметру
      for (let c = 0; c < cols; c++) {
        curr[c] += (ambient - curr[c]) * 0.12;
        curr[(rows - 1) * cols + c] += (ambient - curr[(rows - 1) * cols + c]) * 0.12;
      }
      for (let r = 0; r < rows; r++) {
        curr[r * cols] += (ambient - curr[r * cols]) * 0.12;
        curr[r * cols + (cols - 1)] += (ambient - curr[r * cols + (cols - 1)]) * 0.12;
      }

      // TAL (Time Above Liquidus) — накопленное интегральное время выше точки ликвидуса контрольной площадки
      const padX = Math.floor(cols / 2);
      const padY = Math.floor(rows / 2);
      const padTemp = curr[padY * cols + padX];
      if (padTemp >= this.meltTemp) {
        this.touchTimeSec += dt;
      }
    }

    render() {
      const imgData = this.ctx.createImageData(this.w, this.h);
      const buf = new Uint32Array(imgData.data.buffer);
      const cols = this.cols;
      const cellW = this.cellW;
      const cellH = this.cellH;
      const maxT = Math.max(350, this.tipTemp + 30);
      const rangeT = maxT - this.ambientTemp;

      for (let y = 0; y < this.h; y++) {
        const r = Math.min(this.rows - 1, Math.floor(y / cellH));
        const rowOffset = r * cols;
        const pyOffset = y * this.w;

        for (let x = 0; x < this.w; x++) {
          const c = Math.min(this.cols - 1, Math.floor(x / cellW));
          const tVal = this.grid[rowOffset + c];
          const norm = Math.max(0, Math.min(1, (tVal - this.ambientTemp) / rangeT));
          const palIdx = Math.floor(norm * 255);
          buf[pyOffset + x] = this.palette[palIdx];
        }
      }

      this.ctx.putImageData(imgData, 0, 0);
      this.drawOverlays();
    }

    drawOverlays() {
      const ctx = this.ctx;

      // Draw faint CAD grid lines
      ctx.strokeStyle = 'rgba(255, 255, 255, 0.08)';
      ctx.lineWidth = 1;
      ctx.beginPath();
      for (let x = 0; x < this.w; x += 40) {
        ctx.moveTo(x, 0); ctx.lineTo(x, this.h);
      }
      for (let y = 0; y < this.h; y += 35) {
        ctx.moveTo(0, y); ctx.lineTo(this.w, y);
      }
      ctx.stroke();

      // Draw virtual copper pad & track
      const cx = this.w / 2;
      const cy = this.h / 2;
      ctx.strokeStyle = 'rgba(255, 199, 0, 0.4)';
      ctx.lineWidth = 3;
      ctx.beginPath();
      ctx.moveTo(cx - 90, cy);
      ctx.lineTo(cx + 90, cy);
      ctx.stroke();

      ctx.beginPath();
      ctx.arc(cx, cy, 14, 0, Math.PI * 2);
      ctx.strokeStyle = 'rgba(255, 199, 0, 0.8)';
      ctx.lineWidth = 2;
      ctx.stroke();

      // Active tip crosshair
      const tx = this.tipX * this.cellW + this.cellW / 2;
      const ty = this.tipY * this.cellH + this.cellH / 2;
      ctx.strokeStyle = '#ffffff';
      ctx.lineWidth = 1.5;
      ctx.beginPath();
      ctx.arc(tx, ty, 6, 0, Math.PI * 2);
      ctx.moveTo(tx - 10, ty); ctx.lineTo(tx + 10, ty);
      ctx.moveTo(tx, ty - 10); ctx.lineTo(tx, ty + 10);
      ctx.stroke();
    }

    initVisibilityObserver() {
      if ('IntersectionObserver' in window) {
        this.observer = new IntersectionObserver((entries) => {
          entries.forEach(entry => {
            this.isVisible = entry.isIntersecting;
            if (this.isVisible && this.shouldRun) {
              this.start();
            } else if (!this.isVisible && this.isRunning) {
              this.stopLoopOnly();
            }
          });
        }, { threshold: 0.05 });
        this.observer.observe(this.canvas);
      }

      document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
          this.releaseContact();
          if (this.isRunning) this.stopLoopOnly();
        } else if (this.isVisible && this.shouldRun) {
          this.lastFrameTime = performance.now();
          this.start();
        }
      });
    }

    start() {
      this.shouldRun = true;
      this.lastFrameTime = performance.now();
      if (!this.isVisible || this.isRunning) return;
      this.isRunning = true;

      const loop = () => {
        this.step();
        this.render();
        if (this.isRunning) {
          this.animId = requestAnimationFrame(loop);
        }
      };

      this.animId = requestAnimationFrame(loop);
    }

    stopLoopOnly() {
      this.isRunning = false;
      if (this.animId) cancelAnimationFrame(this.animId);
      this.animId = null;
    }

    stop() {
      this.shouldRun = false;
      this.stopLoopOnly();
    }
  }

  if (thermalCanvas) {
    thermalSim = new ThermalSimulator(thermalCanvas);
    window.thermalSim = thermalSim;
  }

  function updateTempCalculator() {
    if (!tempSolder || !tempWork || !tempRangeDisplay) return;

    // Сценарий «Не знаю» — не выдавать температуру вслепую
    if (activeQuickTask === 'unknown' && (!tempWork.value || !tempSolder.value)) {
      if (tempEmptyState) tempEmptyState.classList.add('hidden');
      if (tempResultContent) tempResultContent.classList.remove('hidden');
      if (tempMeterBox) tempMeterBox.style.display = 'none';
      if (heatmapContainer) heatmapContainer.style.display = 'none';
      if (thermalSim) thermalSim.stop();

      if (tempStatusBadge) {
        tempStatusBadge.textContent = 'ТРЕБУЕТСЯ ВЫБОР';
        tempStatusBadge.className = 'font-mono text-xs px-2 py-0.5 rounded font-bold uppercase bg-ink/75 text-white';
      }

      tempRangeDisplay.textContent = 'Определите задачу';
      tempRangeDisplay.style.fontSize = '1.35rem';
      if (tempTipDisplay) tempTipDisplay.textContent = 'Зависит от размера детали и металла';

      if (tempAdviceDisplay) {
        tempAdviceDisplay.innerHTML = '<strong>С чего начать выбор:</strong><br>1. <strong>Что соединяем:</strong> провод, детали на плате или сантехнику?<br>2. <strong>Сечение и масса:</strong> для тонких SMD нужна точность, для толстых жил — широкое жало.<br>3. Выберите соответствующий пункт в выпадающем списке слева, чтобы получить расчёт.';
      }

      if (tempWarnDisplay && tempWarnBox) {
        tempWarnDisplay.textContent = 'Не начинайте пайку вслепую на случайной температуре: для тонких дорожек перегрев выше 300 °C грозит отслоением меди, а для толстых проводов слабая температура приведёт к холодной пайке и залипанию жала.';
        tempWarnBox.style.display = 'block';
      }

      if (tempArticleLink && tempArticleTitle) {
        tempArticleLink.href = 'article.php?slug=chto-kupit-dlya-pervoj-payki';
        tempArticleTitle.textContent = 'С чего начать: базовые правила пайки и безопасность';
      }
      return;
    }

    const sVal = tempSolder.value;
    const wVal = tempWork.value;

    try {
      localStorage.setItem('tp_temp_solder', sVal);
      localStorage.setItem('tp_temp_work', wVal);
      localStorage.setItem('tp_temp_task', activeQuickTask);
    } catch (e) {}
    
    // State: Валидация выбора
    if (!sVal) {
      if (window.TCHP_UI) window.TCHP_UI.showFieldError(tempSolder, 'Укажите марку припоя (например, ПОС-61 или SAC305) для расчёта точки ликвидуса');
      return;
    } else if (window.TCHP_UI) {
      window.TCHP_UI.clearFieldError(tempSolder);
    }

    if (!wVal) {
      if (window.TCHP_UI) window.TCHP_UI.showFieldError(tempWork, 'Укажите тип детали или проводника: теплоёмкость меди определяет нужный запас тепла жала');
      return;
    } else if (window.TCHP_UI) {
      window.TCHP_UI.clearFieldError(tempWork);
    }

    const solderGroup = tempRules[sVal] || {};
    const item = solderGroup[wVal];

    // State: Результат
    if (tempEmptyState) {
      tempEmptyState.classList.add('hidden');
    }
    if (tempResultContent) {
      tempResultContent.classList.remove('hidden');
    }
    if (tempMeterBox) tempMeterBox.style.display = 'block';

    const isPipe = wVal === 'copper_pipe' || activeQuickTask === 'pipe';
    const isNoRec = !item || item.no_recommendation || (item.t_min === 0 && item.t_max === 0) || isPipe;

    if (isNoRec) {
      if (tempStatusBadge) {
        tempStatusBadge.textContent = 'НЕ ДЛЯ ЭТОГО';
        tempStatusBadge.className = 'font-mono text-xs px-2 py-0.5 rounded font-bold uppercase badge-danger';
      }

      tempRangeDisplay.textContent = 'Паяльник не подходит';
      tempRangeDisplay.style.fontSize = '1.35rem';
      tempRangeDisplay.classList.remove('temp-heat-pulse');
      if (tempMeterBox) tempMeterBox.style.display = 'none';
      if (tempBarFill) tempBarFill.style.width = '0%';
      if (heatmapContainer) heatmapContainer.style.display = 'none';
      if (thermalSim) thermalSim.stop();
      if (tempTipDisplay) tempTipDisplay.textContent = 'Требуется газовая горелка';
      
      if (tempAdviceDisplay) {
        tempAdviceDisplay.innerHTML = '<strong>Технологическое ограничение:</strong> Медные трубы и массивные фитинги обладают огромной теплоёмкостью. Пайка выполняется исключительно газовой горелкой с сантехническим мягким припоем и пастой-флюсом (ГОСТ 52948-2008).';
      }
      
      if (tempWarnDisplay && tempWarnBox) {
        tempWarnDisplay.textContent = 'Категорически нельзя паять медные трубы радиомонтажным паяльником — он мгновенно остынет, припой застынет комком. Для медных труб нужна горелка и сантехнические материалы; калькулятор паяльника не применим. Сантехнический кислотный флюс категорически запрещено переносить на платы или радиоэлектронику!';
        tempWarnBox.style.display = 'block';
      }

      if (tempArticleLink && tempArticleTitle) {
        tempArticleLink.href = 'article.php?slug=temperaturnye-profili';
        tempArticleTitle.textContent = 'Температурные профили и ограничения оборудования';
      }
      return;
    }

    // Режим нормального ориентира
    if (tempStatusBadge) {
      const avg = (item.t_min + item.t_max) / 2;
      if (avg <= 300) {
        tempStatusBadge.textContent = 'СТАРТОВЫЙ ОРИЕНТИР // СТАНДАРТНЫЙ РЕЖИМ';
        tempStatusBadge.className = 'font-mono text-xs px-2 py-0.5 rounded font-bold uppercase bg-paper-subtle border border-paper-border text-ink';
      } else {
        tempStatusBadge.textContent = 'ПОВЫШЕННЫЙ НАГРЕВ // ТРЕБУЕТСЯ ПРЕДНАГРЕВ';
        tempStatusBadge.className = 'font-mono text-xs px-2 py-0.5 rounded font-bold uppercase bg-amber-400 text-ink';
      }
    }

    tempRangeDisplay.style.fontSize = '';
    tempRangeDisplay.textContent = `${item.t_min} – ${item.t_max} °C`;

    // Tactile micro-animation: trigger temperature heat pulse
    tempRangeDisplay.classList.remove('temp-heat-pulse');
    void tempRangeDisplay.offsetWidth;
    tempRangeDisplay.classList.add('temp-heat-pulse');

    // Visual temperature meter gauge (чтение динамических токенов)
    if (tempBarFill) {
      const avg = (item.t_min + item.t_max) / 2;
      const pct = Math.min(100, Math.max(10, ((avg - 100) / 300) * 100));
      tempBarFill.style.width = `${pct}%`;

      const accent = getComputedStyle(document.documentElement)
        .getPropertyValue('--color-accent')
        .trim() || '#FF6B2B';

      if (avg < 200) {
        tempBarFill.style.backgroundColor = 'var(--color-success, #1A7F4C)';
      } else if (avg <= 320) {
        tempBarFill.style.backgroundColor = accent;
      } else {
        tempBarFill.style.backgroundColor = 'var(--color-danger, #B3261E)';
      }
    }

    if (tempTipDisplay) {
      tempTipDisplay.textContent = item.tip ? `Форма жала: ${item.tip}` : 'Стандартное жало';
    }

    // Разделение практических рекомендаций по сценарию
    if (tempAdviceDisplay) {
      const jointText = item.t_joint ? `<div class="mb-1.5 text-xs text-accent font-mono font-bold">Ожидаемая T в зоне контакта: ~${item.t_joint}</div>` : '';
      let adviceText = item.advice || 'Контролируйте время контакта — достаточное для смачивания, но без перегрева.';
      
      if (wVal.startsWith('wire')) {
        adviceText = 'Нанесите каплю нейтрального флюса на проводник перед касанием жала. Используйте жало типа «клин» 2.4 мм для надежной теплопередачи.';
      } else if (wVal.startsWith('smd') || wVal === 'pth') {
        adviceText = 'Контролируйте время контакта — не более 2–3 секунд на площадку. При затрудненном плавлении увеличьте площадь жала, а не силу нажима.';
      }
      
      tempAdviceDisplay.innerHTML = `<strong>Уставка станции:</strong> ${item.t_min}–${item.t_max} °C (компенсирует тепловой градиент).<br>${jointText}<strong>Практический шаг:</strong> ${adviceText}`;
    }

    // Разделение предупреждений по сценарию (Провод vs Плата)
    if (tempWarnDisplay && tempWarnBox) {
      if (wVal.startsWith('wire')) {
        tempWarnDisplay.textContent = 'Риск расплавить изоляцию: не удерживайте жало на жиле дольше 2–3 секунд. При пайке многожильного провода прогревайте жилу и контактную клемму одновременно, не пережигая канифоль в чёрный шлак.';
      } else {
        tempWarnDisplay.textContent = 'Активные кислотные флюсы строго запрещены (разрушают текстолит и вызывают замыкания). Нельзя давить жалом на контактную площадку — отрыв дорожки происходит от механического нажима при перегреве!';
      }
      tempWarnBox.style.display = 'block';
    }

    if (tempArticleLink && tempArticleTitle) {
      if (wVal.startsWith('wire')) {
        tempArticleLink.href = 'article.php?slug=luzhenie-provoda';
        tempArticleTitle.textContent = 'Микроурок: Лужение провода без «деревянного» кончика';
      } else if (wVal === 'gnd' || wVal === 'pth' || wVal === 'smd_heavy') {
        tempArticleLink.href = 'article.php?slug=podbor-zhala-k-polygonu';
        tempArticleTitle.textContent = 'Микроурок: Подбор жала к массивному полигону (GND)';
      } else {
        tempArticleLink.href = 'article.php?slug=pravilo-treh-sekund';
        tempArticleTitle.textContent = 'Микроурок: Тепловое окно и правило 2–3 секунд';
      }
    }

    // Reveal and configure thermal simulation
    if (heatmapContainer) heatmapContainer.style.display = 'block';
    if (thermalCanvas) {
      if (!thermalSim) {
        thermalSim = new ThermalSimulator(thermalCanvas);
      }
      // Получаем физические параметры сплава из единого реестра TCHP_MATERIALS
      const mat = (window.TCHP_MATERIALS && window.TCHP_MATERIALS[sVal]) || {};
      const meltT = mat.t_melt_max || 190;
      const avg = (item.t_min + item.t_max) / 2;
      thermalSim.configure(avg, meltT, wVal);
    }

  }

  // State: До ввода
  if (tempCalcBtn) {
    tempCalcBtn.addEventListener('click', updateTempCalculator);
  }
  if (tempSolder) {
    tempSolder.addEventListener('change', () => {
      if (window.TCHP_UI) window.TCHP_UI.clearFieldError(tempSolder);
      try { localStorage.setItem('tp_temp_solder', tempSolder.value); } catch(e) {}
    });
  }
  if (tempWork) {
    tempWork.addEventListener('change', () => {
      if (window.TCHP_UI) window.TCHP_UI.clearFieldError(tempWork);
      try { localStorage.setItem('tp_temp_work', tempWork.value); } catch(e) {}
    });
  }

  // Handle URL query parameters (?tool=temp&task=wire, ?tool=iron, ?tool=checklist)
  const urlParams = new URLSearchParams(window.location.search);
  const toolParam = urlParams.get('tool');
  const taskParam = urlParams.get('task');

  if (taskParam) {
    setQuickTask(taskParam);
  } else if (toolParam === 'temp') {
    setQuickTask('wire');
  } else {
    let restored = false;
    try {
      const savedTask = localStorage.getItem('tp_temp_task');
      const savedSolder = localStorage.getItem('tp_temp_solder');
      const savedWork = localStorage.getItem('tp_temp_work');
      if (savedTask && savedSolder && savedWork) {
        activeQuickTask = savedTask;
        quickTaskButtons.forEach(btn => {
          if (btn.dataset.task === savedTask) {
            btn.classList.add('bg-accent', 'text-ink');
            btn.classList.remove('bg-paper', 'text-white');
          } else {
            btn.classList.remove('bg-accent', 'text-ink');
            btn.classList.add('bg-paper');
          }
        });
        if (tempSolder) tempSolder.value = savedSolder;
        if (tempWork) tempWork.value = savedWork;
        updateTempCalculator();
        restored = true;
      }
    } catch(e) {}

    if (!restored && quickTaskButtons.length > 0) {
      setQuickTask('wire');
    }
  }

  if (toolParam) {
    const targetElem = document.getElementById(toolParam);
    if (targetElem) {
      setTimeout(() => {
        targetElem.scrollIntoView({ behavior: 'smooth' });
      }, 100);
    }
  }


  // ───────────────────────────────────────────────────────────────────────────
  // 2. ПОДБОР ПАЯЛЬНИКА (#iron)
  // ───────────────────────────────────────────────────────────────────────────
  const ironTask = document.getElementById('iron-task');
  const ironIntensity = document.getElementById('iron-intensity');
  const ironClassDisplay = document.getElementById('iron-class-display');
  const ironControlDisplay = document.getElementById('iron-control-display');
  const ironTipDisplay = document.getElementById('iron-tip-display');
  const ironMustDisplay = document.getElementById('iron-must-display');
  const ironNiceDisplay = document.getElementById('iron-nice-display');
  const ironAvoidDisplay = document.getElementById('iron-avoid-display');
  const ironAvoidBox = document.getElementById('iron-avoid-box');
  const ironNoteDisplay = document.getElementById('iron-note-display');

  function updateIronSelector() {
    if (!ironTask || !ironIntensity || !ironClassDisplay) return;

    const tVal = ironTask.value;
    const iVal = ironIntensity.value;

    try {
      localStorage.setItem('tp_iron_task', tVal);
      localStorage.setItem('tp_iron_intensity', iVal);
    } catch(e) {}

    const taskGroup = ironRules[tVal] || {};
    const item = taskGroup[iVal];

    if (!item) return;

    ironClassDisplay.textContent = item.class || 'Универсальная станция';
    if (ironControlDisplay) ironControlDisplay.textContent = item.control || 'Цифровой термостат';
    if (ironTipDisplay) ironTipDisplay.textContent = item.tip_type || 'T12 / C245 совместимые';

    if (ironNoteDisplay) ironNoteDisplay.textContent = item.note || '';

    const presetsDisplay = document.getElementById('iron-presets-display');
    if (presetsDisplay) {
      const presetsMap = {
        wire: ['Pinecil V2 (USB-PD)', 'TS101', 'T12 Mini'],
        boards_tht: ['KSGER T12', 'Quick 957D', 'Hakko FX-888D'],
        smd: ['KSGER T12 (T12-BC2)', 'Aixun T3A (C245)', 'Quick 957D'],
        smd_fine: ['Sugon T26D (JBC C210)', 'Aixun T3B', 'JBC CD-2BQF'],
        connectors: ['Aixun T3A (C245-784)', 'JBC Heavy Duty C245', 'T12 Топорик'],
        bga_rework: ['Quick 861DW (1000W)', 'Atten ST-862D', 'JBC CD-2BQF'],
        pipes: ['Горелка MAPP Gas', 'Мощный паяльник 150-200W']
      };
      const list = presetsMap[tVal] || ['KSGER T12', 'Aixun T3A', 'Quick 861DW', 'JBC CD-2BQF'];
      presetsDisplay.innerHTML = list.map(name => `<span class="px-2 py-0.5 rounded border border-paper-border bg-paper text-ink font-bold">${name}</span>`).join('');
    }


    // Must have list
    if (ironMustDisplay) {
      ironMustDisplay.innerHTML = '';
      if (item.must_have && item.must_have.length > 0) {
        item.must_have.forEach(str => {
          const li = document.createElement('li');
          li.className = 'flex items-center gap-1.5 text-xs font-mono text-ink';
          li.innerHTML = `<span class="text-accent font-bold">✓</span> ${str}`;
          ironMustDisplay.appendChild(li);
        });
      }
    }

    // Nice to have list
    if (ironNiceDisplay) {
      ironNiceDisplay.innerHTML = '';
      if (item.nice_to_have && item.nice_to_have.length > 0) {
        item.nice_to_have.forEach(str => {
          const li = document.createElement('li');
          li.className = 'flex items-center gap-1.5 text-xs font-mono text-ink-muted';
          li.innerHTML = `<span class="text-ink-faint">+</span> ${str}`;
          ironNiceDisplay.appendChild(li);
        });
      } else {
        const li = document.createElement('li');
        li.className = 'text-xs font-mono text-ink-faint italic';
        li.textContent = 'Базового комплекта достаточно';
        ironNiceDisplay.appendChild(li);
      }
    }

    // Avoid box
    if (ironAvoidBox && ironAvoidDisplay) {
      if (item.avoid && item.avoid.length > 0) {
        ironAvoidDisplay.textContent = item.avoid.join('. ');
        ironAvoidBox.style.display = 'flex';
      } else {
        ironAvoidBox.style.display = 'none';
      }
    }
  }

  if (ironTask && ironIntensity) {
    ironTask.addEventListener('change', updateIronSelector);
    ironIntensity.addEventListener('change', updateIronSelector);

    try {
      const savedIronTask = localStorage.getItem('tp_iron_task');
      const savedIronIntensity = localStorage.getItem('tp_iron_intensity');
      if (savedIronTask && ironRules[savedIronTask]) ironTask.value = savedIronTask;
      if (savedIronIntensity) ironIntensity.value = savedIronIntensity;
    } catch(e) {}

    updateIronSelector();
  }


  // ───────────────────────────────────────────────────────────────────────────
  // 3. ДЕРЕВО ДИАГНОСТИКИ ДЕФЕКТОВ (#defect)
  // ───────────────────────────────────────────────────────────────────────────
  const defectContainer = document.getElementById('defect-tree-container');
  const defectRestartBtn = document.getElementById('defect-restart-btn');

  let currentNodeId = 'root';

  function renderDefectNode(nodeId) {
    if (!defectContainer) return;
    const node = defectTree[nodeId];
    if (!node) return;

    currentNodeId = nodeId;
    defectContainer.innerHTML = '';

    if (nodeId !== 'root' && defectRestartBtn) {
      defectRestartBtn.style.display = 'inline-flex';
    } else if (defectRestartBtn) {
      defectRestartBtn.style.display = 'none';
    }

    // If node is question or symptom root
    if (node.type === 'symptom' || node.type === 'question') {
      const wrapper = document.createElement('div');
      wrapper.className = 'space-y-4';

      const title = document.createElement('h3');
      title.className = 'text-base sm:text-lg font-bold text-ink font-sans flex items-center gap-2';
      title.innerHTML = `<span class="sketch-pill-yellow text-ink font-mono text-xs">ШАГ</span> ${node.label}`;
      wrapper.appendChild(title);

      const optionsGrid = document.createElement('div');
      optionsGrid.className = 'defect-grid pt-1';

      if (node.options && node.options.length > 0) {
        node.options.forEach(opt => {
          const btn = document.createElement('button');
          btn.type = 'button';
          btn.className = 'defect-option-btn text-left p-3.5 rounded-lg border border-paper-border bg-paper hover:border-accent hover:bg-paper-subtle transition-all cursor-pointer font-mono text-xs text-ink flex items-center justify-between group shadow-sm';
          btn.innerHTML = `
            <span>${opt.label}</span>
            <span class="text-accent group-hover:translate-x-0.5 transition-transform font-bold ml-2">→</span>
          `;
          btn.addEventListener('click', () => {
            renderDefectNode(opt.next);
          });
          optionsGrid.appendChild(btn);
        });
      }

      wrapper.appendChild(optionsGrid);
      defectContainer.appendChild(wrapper);
    } 
    // If node is final diagnosis result
    else if (node.type === 'result') {
      const card = document.createElement('div');
      card.className = 'p-5 sm:p-6 rounded-lg border border-paper-border bg-paper-subtle space-y-4';

      // Header with Severity badge
      const header = document.createElement('div');
      header.className = 'flex flex-wrap items-center justify-between gap-2 pb-3 border-b border-paper-border';
      
      const title = document.createElement('h3');
      title.className = 'text-lg font-bold text-ink font-sans';
      title.textContent = node.label || 'Результат диагностики';

      let sevBadge = '<span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-amber-100 text-amber-900 dark:bg-amber-900/40 dark:text-amber-300">Средняя критичность</span>';
      if (node.severity === 'high') {
        sevBadge = '<span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-red-100 text-red-900 dark:bg-red-900/40 dark:text-red-300">Критический дефект</span>';
      } else if (node.severity === 'low') {
        sevBadge = '<span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-green-100 text-green-900 dark:bg-green-900/40 dark:text-green-300">Легко устранимо</span>';
      }

      header.appendChild(title);
      header.innerHTML += sevBadge;
      card.appendChild(header);

      // Causes
      if (node.causes && node.causes.length > 0) {
        const cBlock = document.createElement('div');
        cBlock.className = 'space-y-1.5';
        cBlock.innerHTML = `
          <div class="text-[11px] font-mono uppercase font-bold text-accent">Первопричины:</div>
          <ul class="space-y-1 pl-1">
            ${node.causes.map(c => `<li class="text-xs font-mono text-ink flex items-start gap-2"><span class="text-accent font-bold">•</span> ${c}</li>`).join('')}
          </ul>
        `;
        card.appendChild(cBlock);
      }

      // Actions
      if (node.actions && node.actions.length > 0) {
        const aBlock = document.createElement('div');
        aBlock.className = 'space-y-1.5 p-3 rounded bg-paper border border-paper-border';
        aBlock.innerHTML = `
          <div class="text-[11px] font-mono uppercase font-bold text-green-700 dark:text-green-400">Что сделать прямо сейчас:</div>
          <ol class="space-y-1 pl-1">
            ${node.actions.map((a, i) => `<li class="text-xs font-mono text-ink flex items-start gap-2"><span class="font-bold text-ink-muted">${i+1}.</span> ${a}</li>`).join('')}
          </ol>
        `;
        card.appendChild(aBlock);
      }

      // Don't
      if (node.dont && node.dont.length > 0) {
        const dBlock = document.createElement('div');
        dBlock.className = 'space-y-1 text-xs font-mono text-red-700 dark:text-red-400';
        dBlock.innerHTML = `
          <div class="text-[11px] uppercase font-bold">Чего НЕ делать:</div>
          <div>${node.dont.join('. ')}</div>
        `;
        card.appendChild(dBlock);
      }

      // Articles links
      if (node.links && node.links.length > 0) {
        const lBlock = document.createElement('div');
        lBlock.className = 'pt-2 border-t border-paper-border flex flex-wrap items-center gap-2 text-xs font-mono';
        lBlock.innerHTML = `
          <span class="text-ink-muted">Статьи по теме:</span>
          ${node.links.map(l => `<a href="/article.php?slug=${encodeURIComponent(l)}" class="text-accent hover:underline font-bold">${l} →</a>`).join(', ')}
        `;
        card.appendChild(lBlock);
      }

      defectContainer.appendChild(card);
    }
  }

  if (defectContainer && defectTree.root) {
    renderDefectNode('root');
  }

  if (defectRestartBtn) {
    defectRestartBtn.addEventListener('click', () => {
      renderDefectNode('root');
    });
  }

  // ───────────────────────────────────────────────────────────────────────────
  // 4. СЕЛЕКТОР ФЛЮСА (#flux-selector)
  // ───────────────────────────────────────────────────────────────────────────
  const fluxMetal = document.getElementById('flux-metal');
  const fluxProcess = document.getElementById('flux-process');
  const fluxWash = document.getElementById('flux-wash');
  const fluxCodeDisplay = document.getElementById('flux-code-display');
  const fluxTitleDisplay = document.getElementById('flux-title-display');
  const fluxWashDisplay = document.getElementById('flux-wash-display');
  const fluxWarnDisplay = document.getElementById('flux-warn-display');
  const fluxWarnBox = document.getElementById('flux-warn-box');
  const fluxArticleLink = document.getElementById('flux-article-link');

  function updateFluxSelector() {
    if (!fluxMetal || !fluxProcess || !fluxWash) return;
    
    const metal = fluxMetal.value;
    const process = fluxProcess.value;
    const wash = fluxWash.value;

    try {
      localStorage.setItem('tp_flux_metal', metal);
      localStorage.setItem('tp_flux_process', process);
      localStorage.setItem('tp_flux_wash', wash);
    } catch(e) {}

    const metalGroup = fluxRules[metal] || {};
    const processGroup = metalGroup[process] || {};
    const item = processGroup[wash];

    if (!item) return;

    if (fluxCodeDisplay) {
      fluxCodeDisplay.textContent = item.class_code;
      if (item.class_code === 'Недопустимо') {
        fluxCodeDisplay.className = 'text-2xl font-bold font-mono text-red-600 dark:text-red-400 mt-0.5';
      } else {
        fluxCodeDisplay.className = 'text-2xl font-bold font-mono text-ink mt-0.5';
      }
    }

    if (fluxTitleDisplay) {
      fluxTitleDisplay.textContent = item.title;
    }

    if (fluxWashDisplay) {
      fluxWashDisplay.textContent = item.wash_rule;
    }

    if (fluxWarnBox && fluxWarnDisplay) {
      if (item.warning) {
        fluxWarnDisplay.textContent = item.warning;
        if (item.hazard === 'high') {
          fluxWarnBox.className = 'p-3 rounded bg-red-50 border border-red-300 dark:border-red-800 dark:bg-red-950/30 flex items-start gap-2';
          fluxWarnDisplay.className = 'text-xs font-mono text-red-800 dark:text-red-300 leading-normal font-medium';
        } else {
          fluxWarnBox.className = 'p-3 rounded bg-amber-50 border border-amber-300 dark:border-amber-800 dark:bg-amber-950/30 flex items-start gap-2';
          fluxWarnDisplay.className = 'text-xs font-mono text-amber-800 dark:text-amber-300 leading-normal';
        }
        fluxWarnBox.style.display = 'flex';
      } else {
        fluxWarnBox.style.display = 'none';
      }
    }

    if (fluxArticleLink) fluxArticleLink.style.display = 'block';
  }

  if (fluxMetal && fluxProcess && fluxWash) {
    fluxMetal.addEventListener('change', updateFluxSelector);
    fluxProcess.addEventListener('change', updateFluxSelector);
    fluxWash.addEventListener('change', updateFluxSelector);

    try {
      const savedFluxMetal = localStorage.getItem('tp_flux_metal');
      const savedFluxProcess = localStorage.getItem('tp_flux_process');
      const savedFluxWash = localStorage.getItem('tp_flux_wash');
      if (savedFluxMetal && fluxRules[savedFluxMetal]) fluxMetal.value = savedFluxMetal;
      if (savedFluxProcess) fluxProcess.value = savedFluxProcess;
      if (savedFluxWash) fluxWash.value = savedFluxWash;
    } catch(e) {}

    // 1-Click Quick Scenario Presets
    const quickChips = document.querySelectorAll('.flux-quick-chip');
    quickChips.forEach(chip => {
      chip.addEventListener('click', function() {
        quickChips.forEach(c => {
          c.classList.remove('border-accent', 'text-accent', 'font-bold');
        });
        this.classList.add('border-accent', 'text-accent', 'font-bold');
        if (this.dataset.metal) fluxMetal.value = this.dataset.metal;
        if (this.dataset.process) fluxProcess.value = this.dataset.process;
        if (this.dataset.wash) fluxWash.value = this.dataset.wash;
        updateFluxSelector();
      });
    });

    updateFluxSelector();
  }

  // ───────────────────────────────────────────────────────────────────────────
  // 5. РАСХОД ПРИПОЯ (#solder-consumption)
  // ───────────────────────────────────────────────────────────────────────────
  const consType = document.getElementById('cons-type');
  const consDiam = document.getElementById('cons-diam');
  const consCount = document.getElementById('cons-count');
  const consLength = document.getElementById('cons-length');
  const consWeight = document.getElementById('cons-weight');

  function updateConsumption() {
    if (!consType || !consDiam || !consCount || !consLength || !consWeight) return;

    const type = consType.value || 'point';
    const rawDiam = parseFloat(consDiam.value);
    const diam = (isNaN(rawDiam) || rawDiam <= 0) ? 0.8 : Math.max(0.2, Math.min(5.0, rawDiam));
    const rawCount = parseInt(consCount.value, 10);
    const count = (isNaN(rawCount) || rawCount <= 0) ? 1 : Math.max(1, Math.min(100000, rawCount));

    try {
      localStorage.setItem('tp_cons_type', type);
      localStorage.setItem('tp_cons_diam', diam.toString());
      localStorage.setItem('tp_cons_count', count.toString());
    } catch(e) {}

    // mm per point for 0.8mm wire as baseline
    let mmPerPoint = 10;
    if (type === 'smd') mmPerPoint = 3;
    if (type === 'wire') mmPerPoint = 20;

    // Adjust for diameter (volume preservation: r^2 * h = const)
    const lengthMm = mmPerPoint * count * Math.pow(0.8 / diam, 2);
    
    // Add 15% margin
    const totalLengthMm = lengthMm * 1.15;
    
    // Convert to cm
    const totalLengthCm = totalLengthMm / 10;
    
    // Weight: density of POS-61 is ~8.5 g/cm3
    const rCm = diam / 20;
    const volCm3 = Math.PI * rCm * rCm * totalLengthCm;
    const weightG = volCm3 * 8.5;

    if (!isFinite(totalLengthCm) || totalLengthCm <= 0) {
      consLength.textContent = '~ 0 см';
      consWeight.textContent = 'Вес: ~ 0.0 г';
      return;
    }

    consLength.textContent = `~ ${Math.ceil(totalLengthCm)} см`;
    consWeight.textContent = `Вес: ~ ${weightG.toFixed(1)} г`;
  }

  if (consType && consDiam && consCount) {
    consType.addEventListener('change', updateConsumption);
    consDiam.addEventListener('change', updateConsumption);
    consCount.addEventListener('input', updateConsumption);

    try {
      const savedConsType = localStorage.getItem('tp_cons_type');
      const savedConsDiam = localStorage.getItem('tp_cons_diam');
      const savedConsCount = localStorage.getItem('tp_cons_count');
      if (savedConsType) consType.value = savedConsType;
      if (savedConsDiam) consDiam.value = savedConsDiam;
      if (savedConsCount) consCount.value = savedConsCount;
    } catch(e) {}

    updateConsumption();
  }

  // ───────────────────────────────────────────────────────────────────────────
  // 6. SCROLL-SPY & QUICK RAIL TOC / MOBILE SUBNAV NAVIGATION
  // ───────────────────────────────────────────────────────────────────────────
  const toolSectionIds = ['temp', 'iron', 'checklist', 'defect', 'calculator', 'flux-selector', 'solder-consumption', 'table'];
  const tocLinks = document.querySelectorAll('.workbench-toc-link');
  const subnavChips = document.querySelectorAll('.workbench-subnav-chip');

  function setActiveWorkbenchNav(activeId) {
    tocLinks.forEach(link => {
      const href = link.getAttribute('href');
      if (href === `#${activeId}`) {
        link.classList.add('active');
        link.setAttribute('aria-current', 'true');
      } else {
        link.classList.remove('active');
        link.removeAttribute('aria-current');
      }
    });

    subnavChips.forEach(chip => {
      const href = chip.getAttribute('href');
      if (href === `#${activeId}`) {
        chip.classList.add('active');
        chip.setAttribute('aria-current', 'true');
        chip.scrollIntoView({ behavior: 'smooth', inline: 'nearest', block: 'nearest' });
      } else {
        chip.classList.remove('active');
        chip.removeAttribute('aria-current');
      }
    });
  }

  // Smooth scroll click handler
  document.querySelectorAll('.workbench-toc-link, .workbench-subnav-chip').forEach(link => {
    link.addEventListener('click', (e) => {
      const href = link.getAttribute('href');
      if (!href || !href.startsWith('#')) return;
      const targetId = href.substring(1);
      const targetEl = document.getElementById(targetId);
      if (targetEl) {
        e.preventDefault();
        targetEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
        history.replaceState(null, '', href);
        setActiveWorkbenchNav(targetId);
      }
    });
  });

  // IntersectionObserver Scroll-Spy
  if ('IntersectionObserver' in window && toolSectionIds.length > 0) {
    const observerState = new Map();

    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        observerState.set(entry.target.id, entry.isIntersecting);
      });

      // Find first visible section in order
      for (const id of toolSectionIds) {
        if (observerState.get(id)) {
          setActiveWorkbenchNav(id);
          break;
        }
      }
    }, {
      rootMargin: '-15% 0px -65% 0px',
      threshold: 0
    });

    toolSectionIds.forEach(id => {
      const el = document.getElementById(id);
      if (el) observer.observe(el);
    });
  }

});

