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
    updateTempCalculator();
  }

  quickTaskButtons.forEach(btn => {
    btn.addEventListener('click', () => {
      setQuickTask(btn.dataset.task);
    });
  });

  function updateTempCalculator() {
    if (!tempSolder || !tempWork || !tempRangeDisplay) return;

    // Сценарий «Не знаю» — не выдавать температуру вслепую
    if (activeQuickTask === 'unknown' && (!tempWork.value || !tempSolder.value)) {
      if (tempEmptyState) tempEmptyState.classList.add('hidden');
      if (tempResultContent) tempResultContent.classList.remove('hidden');
      if (tempMeterBox) tempMeterBox.style.display = 'none';

      if (tempStatusBadge) {
        tempStatusBadge.textContent = 'ТРЕБУЕТСЯ ВЫБОР';
        tempStatusBadge.className = 'font-mono text-[11px] px-2 py-0.5 rounded font-bold uppercase bg-ink/75 text-white';
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
    
    // State: Валидация выбора
    if (!sVal) {
      if (window.TCHP_UI) window.TCHP_UI.showFieldError(tempSolder, 'Выберите марку припоя');
      return;
    } else if (window.TCHP_UI) {
      window.TCHP_UI.clearFieldError(tempSolder);
    }

    if (!wVal) {
      if (window.TCHP_UI) window.TCHP_UI.showFieldError(tempWork, 'Выберите тип монтажной операции');
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
        tempStatusBadge.className = 'font-mono text-[11px] px-2 py-0.5 rounded font-bold uppercase badge-danger';
      }

      tempRangeDisplay.textContent = 'Паяльник не подходит';
      tempRangeDisplay.style.fontSize = '1.35rem';
      if (tempBarFill) tempBarFill.style.width = '0%';
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
      tempStatusBadge.textContent = 'ОРИЕНТИР';
      tempStatusBadge.className = 'font-mono text-[11px] px-2 py-0.5 rounded font-bold uppercase bg-accent text-ink';
    }

    tempRangeDisplay.style.fontSize = '';
    tempRangeDisplay.textContent = `${item.t_min} – ${item.t_max} °C`;

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
        tempArticleLink.href = 'article.php?slug=chto-kupit-dlya-pervoj-payki';
        tempArticleTitle.textContent = 'Что купить для первой пайки и как правильно лудить провод';
      } else {
        tempArticleLink.href = 'article.php?slug=temperaturnye-profili';
        tempArticleTitle.textContent = 'Температурные профили: как не перегреть плату и компоненты';
      }
    }
  }

  // State: До ввода
  if (tempCalcBtn) {
    tempCalcBtn.addEventListener('click', updateTempCalculator);
  }
  if (tempSolder) {
    tempSolder.addEventListener('change', () => {
      if (window.TCHP_UI) window.TCHP_UI.clearFieldError(tempSolder);
    });
  }
  if (tempWork) {
    tempWork.addEventListener('change', () => {
      if (window.TCHP_UI) window.TCHP_UI.clearFieldError(tempWork);
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
  } else if (quickTaskButtons.length > 0) {
    setQuickTask('wire');
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

    const type = consType.value;
    const diam = parseFloat(consDiam.value);
    const count = parseInt(consCount.value) || 1;

    // mm per point for 0.8mm wire as baseline
    let mmPerPoint = 10;
    if (type === 'smd') mmPerPoint = 3;
    if (type === 'wire') mmPerPoint = 20;

    // Adjust for diameter (volume preservation: r^2 * h = const)
    // base diam = 0.8, r = 0.4. V ~ 0.16 * h
    // new diam = d, r = d/2. V ~ (d/2)^2 * h_new
    // h_new = h_old * (0.8 / d)^2
    const lengthMm = mmPerPoint * count * Math.pow(0.8 / diam, 2);
    
    // Add 15% margin
    const totalLengthMm = lengthMm * 1.15;
    
    // Convert to cm
    const totalLengthCm = totalLengthMm / 10;
    
    // Weight: density of POS-61 is ~8.5 g/cm3
    // Vol in cm3 = pi * (diam/20)^2 * totalLengthCm
    const rCm = diam / 20;
    const volCm3 = Math.PI * rCm * rCm * totalLengthCm;
    const weightG = volCm3 * 8.5;

    consLength.textContent = `~ ${Math.ceil(totalLengthCm)} см`;
    consWeight.textContent = `Вес: ~ ${weightG.toFixed(1)} г`;
  }

  if (consType && consDiam && consCount) {
    consType.addEventListener('change', updateConsumption);
    consDiam.addEventListener('change', updateConsumption);
    consCount.addEventListener('input', updateConsumption);
    updateConsumption();
  }

});
