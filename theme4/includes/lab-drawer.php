<?php
/**
 * lab-drawer.php — Выдвижная приборная панель верстака (Quick Lab Drawer)
 * По архитектурному паттерну Emil Kowalski (accessible drawer) & Teenage Engineering.
 */
?>
<!-- Lab Drawer Backdrop & Container -->
<div id="lab-drawer-backdrop" class="lab-drawer-backdrop" role="dialog" aria-modal="true" aria-labelledby="lab-drawer-title" aria-hidden="true">
  <div class="lab-drawer-panel" id="lab-drawer-panel">
    
    <!-- Drawer Header -->
    <div class="p-4 border-b border-paper-border bg-paper flex items-center justify-between shrink-0">
      <div class="flex items-center gap-2">
        <span class="w-2.5 h-2.5 rounded-full bg-accent inline-block animate-pulse" aria-hidden="true"></span>
        <div>
          <span class="text-[10px] font-mono uppercase tracking-widest text-ink-muted block">[ЛАБОРАТОРНЫЙ ИНСТРУМЕНТ]</span>
          <h3 id="lab-drawer-title" class="text-sm font-bold text-ink font-mono uppercase tracking-wider">
            ЭКСПРЕСС-ВЕРСТАК // ТЧП
          </h3>
        </div>
      </div>
      <button type="button" id="lab-drawer-close" class="p-1.5 text-ink-muted hover:text-ink transition-colors rounded border border-paper-border bg-paper-subtle min-w-[36px] min-h-[36px] flex items-center justify-center tactile-press" aria-label="Закрыть выдвижную панель (Esc)">
        <?= tp_icon('x', 'w-4 h-4') ?>
      </button>
    </div>

    <!-- Drawer Body (Scrollable) -->
    <div class="flex-grow overflow-y-auto p-4 space-y-5 font-sans text-sm">
      
      <!-- 1. Экспресс-селектор припоя с цифровым табло -->
      <div class="framework-card p-3.5 space-y-3 bg-card border border-paper-border rounded">
        <div class="flex items-center justify-between text-xs font-mono">
          <span class="module-tag">[MOD.01 // СПЛАВ]</span>
          <div id="drawer-readout" class="readout-display">
            <span class="readout-dot"></span>
            <span id="drawer-readout-temp">280 °C</span>
          </div>
        </div>

        <label for="drawer-alloy-select" class="block text-xs font-bold text-ink">Выберите припой / задачу:</label>
        <select id="drawer-alloy-select" class="field text-xs font-mono py-1.5 bg-paper">
          <option value="pos61" data-temp="280 °C" data-solidus="183 °C" data-liquidus="190 °C" data-flux="ROL0 / RMA" data-tal="2.5–3.0 с">ПОС-61 (Sn61Pb39) — Стандарт РЭА</option>
          <option value="sac305" data-temp="240 °C" data-solidus="217 °C" data-liquidus="217 °C" data-flux="ROL0 No-Clean" data-tal="45–60 с">SAC305 (Sn96.5Ag3Cu0.5) — Бессвинцовый</option>
          <option value="pos40" data-temp="310 °C" data-solidus="183 °C" data-liquidus="238 °C" data-flux="RMA" data-tal="3.0–4.0 с">ПОС-40 (Sn40Pb60) — Силовой монтаж</option>
          <option value="rose" data-temp="150 °C" data-solidus="94 °C" data-liquidus="94 °C" data-flux="Спиртоканифоль" data-tal="1.5–2.0 с">Сплав Розе (Bi50Pb32Sn18) — Демонтаж</option>
          <option value="wood" data-temp="120 °C" data-solidus="68 °C" data-liquidus="72 °C" data-flux="Канифоль" data-tal="1.0–2.0 с">Сплав Вуда (Bi50Pb25Sn12.5Cd12.5)</option>
        </select>

        <div class="quick-spec-grid text-xs">
          <div class="quick-spec-item">
            <span class="quick-spec-label">Ликвидус</span>
            <span class="quick-spec-val" id="drawer-spec-liq">190 °C</span>
          </div>
          <div class="quick-spec-item">
            <span class="quick-spec-label">Флюс</span>
            <span class="quick-spec-val" id="drawer-spec-flux">ROL0 / RMA</span>
          </div>
          <div class="quick-spec-item">
            <span class="quick-spec-label">Время контакта</span>
            <span class="quick-spec-val" id="drawer-spec-tal">2.5–3.0 с</span>
          </div>
        </div>
      </div>

      <!-- 2. Экспресс-калькулятор падения напряжения в проводе -->
      <div class="framework-card p-3.5 space-y-3 bg-card border border-paper-border rounded">
        <div class="flex items-center justify-between text-xs font-mono">
          <span class="module-tag">[MOD.02 // ПРОВОД]</span>
          <span class="text-[11px] font-bold text-accent font-mono" id="drawer-wire-drop">ΔU = 0.09 В</span>
        </div>
        <p class="text-xs text-ink-muted">Быстрый расчет сечения медного провода:</p>
        <div class="grid grid-cols-2 gap-2 text-xs font-mono">
          <div>
            <label class="block text-[10px] uppercase text-ink-faint mb-1">Ток (Ампер):</label>
            <input type="number" id="drawer-current" value="2.5" step="0.5" min="0.1" max="100" class="field py-1 text-xs">
          </div>
          <div>
            <label class="block text-[10px] uppercase text-ink-faint mb-1">Сечение (мм²):</label>
            <select id="drawer-section" class="field py-1 text-xs">
              <option value="0.2">0.20 мм² (AWG 24)</option>
              <option value="0.35">0.35 мм² (AWG 22)</option>
              <option value="0.5" selected>0.50 мм² (AWG 20)</option>
              <option value="0.75">0.75 мм² (AWG 18)</option>
              <option value="1.5">1.50 мм² (AWG 16)</option>
              <option value="2.5">2.50 мм² (AWG 14)</option>
            </select>
          </div>
        </div>
        <div class="text-[11px] font-mono text-ink-muted bg-paper-subtle p-2 rounded border border-paper-border">
          <span>Плотность тока: </span><strong id="drawer-density" class="text-ink">5.0 А/мм²</strong> 
          <span class="text-emerald-600 dark:text-emerald-400 font-bold ml-1" id="drawer-status">✓ НОРМА</span>
        </div>
      </div>

      <!-- 3. Памятка по классам флюсов IPC J-STD-004 -->
      <div class="framework-card p-3.5 space-y-2 bg-card border border-paper-border rounded">
        <div class="flex items-center justify-between text-xs font-mono">
          <span class="module-tag">[MOD.03 // ХИМИЯ]</span>
          <span class="text-[10px] text-ink-faint">IPC J-STD-004</span>
        </div>
        <div class="space-y-1.5 text-xs font-mono">
          <div class="p-1.5 rounded bg-paper-subtle border border-paper-border flex items-center justify-between">
            <span class="font-bold text-ink">ROL0 (No-Clean)</span>
            <span class="text-[10px] text-emerald-600 dark:text-emerald-400 font-bold">БЕЗОПАСЕН</span>
          </div>
          <div class="p-1.5 rounded bg-paper-subtle border border-paper-border flex items-center justify-between">
            <span class="font-bold text-ink">REL0 / RMA</span>
            <span class="text-[10px] text-amber-600 font-bold">СМЫВКА ЖЕЛАТЕЛЬНА</span>
          </div>
          <div class="p-1.5 rounded bg-paper-subtle border border-paper-border flex items-center justify-between">
            <span class="font-bold text-ink">ORM0 / Кислотный</span>
            <span class="text-[10px] text-red-600 font-bold">ОПАСЕН ДЛЯ ПЛАТ</span>
          </div>
        </div>
      </div>

    </div>

    <!-- Drawer Footer -->
    <div class="p-3 border-t border-paper-border bg-paper flex items-center justify-between shrink-0 font-mono text-xs">
      <a href="<?= esc_url($site_root) ?>interactive.php" class="inline-flex items-center gap-1.5 text-ink hover:text-accent font-bold transition-colors">
        <span>Полный верстак (8 тулзов)</span>
        <span>→</span>
      </a>
      <span class="text-[10px] text-ink-faint">ESC ДЛЯ ЗАКРЫТИЯ</span>
    </div>

  </div>
</div>

<!-- Drawer Controller Script -->
<script>
  (function() {
    const backdrop = document.getElementById('lab-drawer-backdrop');
    const panel = document.getElementById('lab-drawer-panel');
    const closeBtn = document.getElementById('lab-drawer-close');
    let lastActiveElement = null;

    window.openLabDrawer = function(saveState = true) {
      if (!backdrop) return;
      lastActiveElement = document.activeElement;
      backdrop.classList.add('active');
      backdrop.setAttribute('aria-hidden', 'false');
      document.body.style.overflow = 'hidden';
      if (saveState) {
        try { sessionStorage.setItem('tp_drawer_open', '1'); } catch(e) {}
      }
      if (closeBtn) closeBtn.focus();
    };

    window.closeLabDrawer = function() {
      if (!backdrop) return;
      backdrop.classList.remove('active');
      backdrop.setAttribute('aria-hidden', 'true');
      document.body.style.overflow = '';
      try { sessionStorage.removeItem('tp_drawer_open'); } catch(e) {}
      if (lastActiveElement && typeof lastActiveElement.focus === 'function') {
        lastActiveElement.focus();
      }
    };

    if (closeBtn) {
      closeBtn.addEventListener('click', window.closeLabDrawer);
    }

    if (backdrop) {
      backdrop.addEventListener('click', function(e) {
        if (e.target === backdrop) {
          window.closeLabDrawer();
        }
      });
    }

    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape' && backdrop && backdrop.classList.contains('active')) {
        window.closeLabDrawer();
      }
    });

    try {
      if (sessionStorage.getItem('tp_drawer_open') === '1') {
        window.openLabDrawer(false);
      }
    } catch(e) {}

    // Интерактив сплавов в шторке
    const alloySelect = document.getElementById('drawer-alloy-select');
    const readoutTemp = document.getElementById('drawer-readout-temp');
    const specLiq = document.getElementById('drawer-spec-liq');
    const specFlux = document.getElementById('drawer-spec-flux');
    const specTal = document.getElementById('drawer-spec-tal');

    if (alloySelect && readoutTemp) {
      alloySelect.addEventListener('change', function() {
        const opt = alloySelect.options[alloySelect.selectedIndex];
        readoutTemp.textContent = opt.dataset.temp || '280 °C';
        if (specLiq) specLiq.textContent = opt.dataset.liquidus || '190 °C';
        if (specFlux) specFlux.textContent = opt.dataset.flux || 'ROL0';
        if (specTal) specTal.textContent = opt.dataset.tal || '3 с';
      });
    }

    // Интерактив расчета провода
    const curIn = document.getElementById('drawer-current');
    const secIn = document.getElementById('drawer-section');
    const dropOut = document.getElementById('drawer-wire-drop');
    const densOut = document.getElementById('drawer-density');
    const statOut = document.getElementById('drawer-status');

    function calcWire() {
      if (!curIn || !secIn) return;
      const i = parseFloat(curIn.value) || 1;
      const s = parseFloat(secIn.value) || 0.5;
      const lengthM = 1.0; // 1 метр
      const rho = 0.0175; // медь Ом*мм2/м
      const r = (rho * lengthM * 2) / s; // туда и обратно
      const du = i * r;
      const density = i / s;

      if (dropOut) dropOut.textContent = 'ΔU = ' + du.toFixed(2) + ' В/м';
      if (densOut) densOut.textContent = density.toFixed(1) + ' А/мм²';
      if (statOut) {
        if (density <= 6.0) {
          statOut.textContent = '✓ НОРМА';
          statOut.className = 'text-emerald-600 dark:text-emerald-400 font-bold ml-1';
        } else if (density <= 10.0) {
          statOut.textContent = '▲ ПРЕДЕЛ';
          statOut.className = 'text-amber-600 font-bold ml-1';
        } else {
          statOut.textContent = '✖ ПЕРЕГРЕВ';
          statOut.className = 'text-red-600 font-bold ml-1';
        }
      }
    }

    if (curIn && secIn) {
      curIn.addEventListener('input', calcWire);
      secIn.addEventListener('change', calcWire);
    }
  })();
</script>
