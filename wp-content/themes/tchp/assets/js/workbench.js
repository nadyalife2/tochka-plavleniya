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

  function updateTempCalculator() {
    if (!tempSolder || !tempWork || !tempRangeDisplay) return;

    const sVal = tempSolder.value;
    const wVal = tempWork.value;

    const solderGroup = tempRules[sVal] || {};
    const item = solderGroup[wVal];

    if (!item || (item.t_min === 0 && item.t_max === 0)) {
      tempRangeDisplay.textContent = 'Не применяется';
      tempRangeDisplay.style.fontSize = '1.75rem';
      if (tempBarFill) tempBarFill.style.width = '0%';
      if (tempTipDisplay) tempTipDisplay.textContent = item?.tip || 'Паяльник не используется для этого сценария';
      if (tempAdviceDisplay) tempAdviceDisplay.textContent = item?.advice || 'Для данной операции используется специализированное оборудование (горелка, преднагрев или фен).';
      if (tempWarnDisplay && tempWarnBox) {
        if (item?.warn) {
          tempWarnDisplay.textContent = item.warn;
          tempWarnBox.style.display = 'block';
        } else {
          tempWarnBox.style.display = 'none';
        }
      }
      return;
    }

    tempRangeDisplay.style.fontSize = '';
    tempRangeDisplay.textContent = `${item.t_min} – ${item.t_max} °C`;

    // Visual temperature meter gauge (scaled from 100 to 450 °C)
    if (tempBarFill) {
      const avg = (item.t_min + item.t_max) / 2;
      const pct = Math.min(100, Math.max(10, ((avg - 100) / 300) * 100));
      tempBarFill.style.width = `${pct}%`;
      if (avg < 200) {
        tempBarFill.style.backgroundColor = '#10b981'; // green / low temp
      } else if (avg <= 320) {
        tempBarFill.style.backgroundColor = '#eb5211'; // standard soldering orange
      } else {
        tempBarFill.style.backgroundColor = '#ef4444'; // hot / caution red
      }
    }

    if (tempTipDisplay) {
      tempTipDisplay.textContent = item.tip ? `Форма жала: ${item.tip}` : 'Стандартное жало';
    }

    if (tempAdviceDisplay) {
      tempAdviceDisplay.textContent = item.advice || 'Контролируйте время контакта — не более 2–3 секунд на точку.';
    }

    if (tempWarnDisplay && tempWarnBox) {
      if (item.warn) {
        tempWarnDisplay.textContent = item.warn;
        tempWarnBox.style.display = 'flex';
      } else {
        tempWarnBox.style.display = 'none';
      }
    }
  }

  if (tempSolder && tempWork) {
    tempSolder.addEventListener('change', updateTempCalculator);
    tempWork.addEventListener('change', updateTempCalculator);
    updateTempCalculator();
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

    const taskGroup = ironRules[tVal] || {};
    const item = taskGroup[iVal];

    if (!item) return;

    ironClassDisplay.textContent = item.class || 'Универсальная станция';
    if (ironControlDisplay) ironControlDisplay.textContent = item.control || 'Цифровой термостат';
    if (ironTipDisplay) ironTipDisplay.textContent = item.tip_type || 'T12 / C245 совместимые';
    if (ironNoteDisplay) ironNoteDisplay.textContent = item.note || '';

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

});
