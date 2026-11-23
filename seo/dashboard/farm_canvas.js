/**
 * ИНЖЕНЕРНЫЙ ГОРОДОК АРТЕЛИ МАСТЕРОВ (AI Town / Generative Agents Game Engine)
 * Полная интерактивная 2D-симуляция городка мастеров по мотивам a16z-infra/ai-town и Stanford generative_agents.
 * Персонажи живут в общем пространстве, ходят между верстаками, обмениваются предметами и репликами.
 * 60 FPS Canvas Engine, чистый Vanilla JS, 0 внешних зависимостей.
 */

class AITownEngine {
  constructor(canvasId) {
    this.canvas = document.getElementById(canvasId);
    if (!this.canvas) return;
    this.ctx = this.canvas.getContext('2d');

    this.width = 1000;
    this.height = 560;
    this.speed = 1.0;
    this.paused = false;
    this.selectedAgent = null;
    this.activeTask = null;

    // Станции / Верстаки мастерской
    this.workstations = {
      soroka:   { id: 'ws_soroka', name: 'Бюро Разведки (Сорока)', icon: '📡', x: 120, y: 110, color: '#38BDF8', agentKey: 'soroka' },
      bobr:     { id: 'ws_bobr', name: 'Конструкторское Бюро (Бобёр)', icon: '🪵', x: 340, y: 110, color: '#B45309', agentKey: 'bobr' },
      triad:    { id: 'ws_triad', name: 'Серверная Triad-Omni', icon: '🖥️', x: 550, y: 90, color: '#10B981', agentKey: 'triad' },
      kot:      { id: 'ws_kot', name: 'Паяльный Цех (Кот-Паяльщик)', icon: '🔥', x: 130, y: 310, color: '#EA580C', agentKey: 'kot' },
      bear:     { id: 'ws_bear', name: 'Круглый Стол Бригадира', icon: '☕', x: 420, y: 280, color: '#EAB308', agentKey: 'bear' },
      lis:      { id: 'ws_lis', name: 'Сметное Бюро (Лис-Прагматик)', icon: '⚖️', x: 680, y: 290, color: '#F59E0B', agentKey: 'lis' },
      barsuk:   { id: 'ws_barsuk', name: 'Кульман Иллюстраций (Барсук)', icon: '📐', x: 180, y: 470, color: '#FBBF24', agentKey: 'barsuk' },
      filin:    { id: 'ws_filin', name: 'Стенд ОТК и SlopMonster (Филин)', icon: '🔬', x: 480, y: 470, color: '#8B5CF6', agentKey: 'filin' },
      dyatel:   { id: 'ws_dyatel', name: 'Метрологический Стенд (Дятел)', icon: '📊', x: 740, y: 470, color: '#EC4899', agentKey: 'dyatel' },
      wp:       { id: 'ws_wp', name: 'Шлюз Релиза (WordPress)', icon: '🚀', x: 910, y: 380, color: '#06B6D4', agentKey: 'wp' }
    };

    // Персонажи Артели (AI Agents)
    this.agents = [
      {
        id: 'bear', name: 'Медведь-Бригадир', icon: '🐻', role: 'Староста Артели',
        x: 420, y: 250, targetX: 420, targetY: 250, homeX: 420, homeY: 250,
        speed: 1.2, state: 'idle', stateTimer: 0,
        bubble: 'Добро пожаловать в Инженерный Городок Артели!', bubbleTimer: 280,
        holdingItem: null, color: '#CA8A04', thoughts: 'Слежу за порядком и энергопотреблением кластера'
      },
      {
        id: 'soroka', name: 'Сорока-Разведчица', icon: '🪶', role: '00-research',
        x: 120, y: 150, targetX: 120, targetY: 150, homeX: 120, homeY: 150,
        speed: 1.8, state: 'idle', stateTimer: 0,
        bubble: 'Парсю топ Яндекса и ищу фишки конкурентов!', bubbleTimer: 240,
        holdingItem: '📜 LSI-ядро', color: '#38BDF8', thoughts: 'Нашла 4 калькулятора припоя в выдаче'
      },
      {
        id: 'bobr', name: 'Бобёр-Конструктор', icon: '🦫', role: '01-researcher',
        x: 340, y: 150, targetX: 340, targetY: 150, homeX: 340, homeY: 150,
        speed: 1.3, state: 'idle', stateTimer: 0,
        bubble: 'Строю плотину стандартов по ГОСТ Р МЭК 61191!', bubbleTimer: 200,
        holdingItem: '🪵 Бриф ТЗ', color: '#B45309', thoughts: 'Сверяю 4 обязательных вопроса FAQ'
      },
      {
        id: 'kot', name: 'Кот-Паяльщик (Practico 1)', icon: '🐱', role: '02-writer',
        x: 130, y: 350, targetX: 130, targetY: 350, homeX: 130, homeY: 350,
        speed: 1.4, state: 'working', stateTimer: 180,
        bubble: 'Прогреваю жало T12 до 285°C. Физика шва превыше всего.', bubbleTimer: 250,
        holdingItem: '📟 Черновик', color: '#EA580C', thoughts: 'Триада: факт -> физика -> последствие'
      },
      {
        id: 'lis', name: 'Лис-Прагматик (Practico 2)', icon: '🦊', role: '02-writer-2',
        x: 680, y: 330, targetX: 680, targetY: 330, homeX: 680, homeY: 330,
        speed: 1.5, state: 'idle', stateTimer: 0,
        bubble: 'Считаем смету: флюс за 300₽ спасет плату за 15 000₽.', bubbleTimer: 220,
        holdingItem: '💰 Смета', color: '#F59E0B', thoughts: 'Окупаемость станции новичка: 3 месяца'
      },
      {
        id: 'barsuk', name: 'Барсук-Иллюстратор', icon: '🦡', role: '05-illustrator',
        x: 180, y: 440, targetX: 180, targetY: 440, homeX: 180, homeY: 440,
        speed: 1.3, state: 'working', stateTimer: 200,
        bubble: 'Черчу векторный термопрофиль ликвидуса ПОС-61 (183–190°C).', bubbleTimer: 260,
        holdingItem: '📐 SVG-схема', color: '#FBBF24', thoughts: 'Векторная графика: 0 токенов, чистая геометрия'
      },
      {
        id: 'filin', name: 'Филин-Ревизор [ОТК]', icon: '🦉', role: '03-reviewer',
        x: 480, y: 440, targetX: 480, targetY: 440, homeX: 480, homeY: 440,
        speed: 1.2, state: 'idle', stateTimer: 0,
        bubble: 'SlopMonster заряжен. Штамп [ОТК-2026] готов к проверке.', bubbleTimer: 230,
        holdingItem: '🔍 Лупа ОТК', color: '#8B5CF6', thoughts: 'Детектирую нейроштампы и сверяю ликвидус'
      },
      {
        id: 'dyatel', name: 'Дятел-Метролог', icon: '🪵', role: '04-scorer',
        x: 740, y: 440, targetX: 740, targetY: 440, homeX: 740, homeY: 440,
        speed: 1.4, state: 'idle', stateTimer: 0,
        bubble: 'Четко 100 баллов по 6 объективным блокам!', bubbleTimer: 210,
        holdingItem: '📊 Шкала 100', color: '#EC4899', thoughts: 'AST-анализ текста без эмоций'
      }
    ];

    // Частицы окружения (паяльный дым, искры, пыль)
    this.ambientParticles = [];
    this.initParticles();

    this.setupEvents();
    this.resize();
    window.addEventListener('resize', () => this.resize());
    this.animate();
  }

  resize() {
    if (!this.canvas) return;
    const rect = this.canvas.parentElement.getBoundingClientRect();
    const dpr = window.devicePixelRatio || 1;
    this.width = rect.width || 1000;
    this.height = 540;
    this.canvas.width = this.width * dpr;
    this.canvas.height = this.height * dpr;
    this.canvas.style.width = this.width + 'px';
    this.canvas.style.height = this.height + 'px';
    this.ctx.scale(dpr, dpr);
  }

  initParticles() {
    // Паяльный дым у верстака Кота
    for (let i = 0; i < 25; i++) {
      this.ambientParticles.push({
        type: 'smoke',
        x: 130 + (Math.random() - 0.5) * 15,
        y: 290 - Math.random() * 40,
        vx: (Math.random() - 0.5) * 0.4,
        vy: -0.6 - Math.random() * 0.8,
        radius: 3 + Math.random() * 6,
        alpha: 0.1 + Math.random() * 0.4,
        grow: 0.05
      });
    }
  }

  setupEvents() {
    this.canvas.addEventListener('click', (e) => {
      const rect = this.canvas.getBoundingClientRect();
      const mx = e.clientX - rect.left;
      const my = e.clientY - rect.top;

      let clickedAgent = null;
      for (const a of this.agents) {
        const dx = mx - a.x;
        const dy = my - a.y;
        if (Math.sqrt(dx * dx + dy * dy) <= 28) {
          clickedAgent = a;
          break;
        }
      }

      if (clickedAgent) {
        this.selectAgent(clickedAgent);
      } else {
        // Клик по полу — если выбран агент, отправляем его туда
        if (this.selectedAgent) {
          this.selectedAgent.targetX = mx;
          this.selectedAgent.targetY = my;
          this.selectedAgent.state = 'walking';
          this.selectedAgent.bubble = `Иду на позицию (${Math.round(mx)}, ${Math.round(my)})!`;
          this.selectedAgent.bubbleTimer = 140;
        }
      }
    });
  }

  selectAgent(agent) {
    this.selectedAgent = agent;
    agent.bubble = `Привет! Я ${agent.name}. Чем помочь?`;
    agent.bubbleTimer = 220;

    // Озвучиваем статус Бригадиром
    if (typeof speakText === 'function') {
      speakText(`${agent.name}: ${agent.thoughts}`);
    }

    // Подсвечиваем карточку на внешней странице
    const cardMap = {
      soroka: 'card-craftsman-analyzer',
      bobr: 'card-craftsman-researcher',
      kot: 'card-craftsman-writer',
      lis: 'card-craftsman-writer2',
      barsuk: 'card-craftsman-illustrator',
      filin: 'card-craftsman-reviewer',
      dyatel: 'card-craftsman-scorer'
    };
    const cId = cardMap[agent.id];
    if (cId && document.getElementById(cId)) {
      document.querySelectorAll('.craftsman-card').forEach(el => el.classList.remove('craftsman-highlighted'));
      const card = document.getElementById(cId);
      card.classList.add('craftsman-highlighted');
      card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      setTimeout(() => card.classList.remove('craftsman-highlighted'), 5000);
    }
  }

  setSpeed(s) {
    this.speed = s;
  }

  togglePause() {
    this.paused = !this.paused;
    return this.paused;
  }

  /**
   * Сценарная цепочка сквозного выполнения (Mission Flow):
   * Сорока -> Бобёр -> Кот -> Барсук -> Филин -> Дятел -> WordPress
   */
  startMissionPipeline(topicId = 1) {
    const pipelineSequence = [
      { from: 'soroka', to: 'bobr', item: '📜 LSI-ядро темы #' + topicId, dialogue: 'Бобёр, держи LSI-хвосты и интент новичка!' },
      { from: 'bobr', to: 'kot', item: '🪵 Бриф ГОСТ Р МЭК #' + topicId, dialogue: 'Кот, плотина стандартов готова! Пиши триаду!' },
      { from: 'kot', to: 'barsuk', item: '📟 Черновик статьи #' + topicId, dialogue: 'Барсук, текст готов! Нужен точный термопрофиль ПОС-61.' },
      { from: 'barsuk', to: 'filin', item: '📐 SVG-схема + Текст #' + topicId, dialogue: 'Филин, держи векторный ликвидус 183°C. Проверь на слоп!' },
      { from: 'filin', to: 'dyatel', item: '🔍 Паспорт v1.1 [ОТК OK]', dialogue: 'Дятел, штамп [ОТК] стоит, слопа 0. Запускай скоринг!' },
      { from: 'dyatel', to: 'wp', item: '📦 Релиз 94/100 -> WP', dialogue: 'Score 94 балла! Пакет доставлен в шлюз WordPress!' }
    ];

    let currentStep = 0;

    const runStep = () => {
      if (currentStep >= pipelineSequence.length) {
        if (typeof speakText === 'function') {
          speakText('Сквозной цикл Артели успешно завершен! Статья готова к публикации.');
        }
        return;
      }

      const step = pipelineSequence[currentStep];
      const sender = this.agents.find(a => a.id === step.from);
      const receiverWs = this.workstations[step.to] || { x: 910, y: 380 };

      if (sender) {
        sender.holdingItem = step.item;
        sender.targetX = receiverWs.x + 30;
        sender.targetY = receiverWs.y + 20;
        sender.state = 'walking';
        sender.bubble = step.dialogue;
        sender.bubbleTimer = 220;

        if (typeof speakText === 'function') {
          speakText(`${sender.name}: ${step.dialogue}`);
        }

        // Ждем пока дойдет
        const checkArrival = setInterval(() => {
          const dist = Math.hypot(sender.x - sender.targetX, sender.y - sender.targetY);
          if (dist < 15) {
            clearInterval(checkArrival);
            sender.state = 'working';
            sender.stateTimer = 120;
            // Возвращаем отправителя домой
            setTimeout(() => {
              sender.targetX = sender.homeX;
              sender.targetY = sender.homeY;
              sender.holdingItem = null;
            }, 1200);

            currentStep++;
            setTimeout(runStep, 1800);
          }
        }, 100);
      }
    };

    runStep();
  }

  update() {
    if (this.paused) return;

    // Обновление состояния агентов
    for (const a of this.agents) {
      // Движение к цели
      const dx = a.targetX - a.x;
      const dy = a.targetY - a.y;
      const dist = Math.hypot(dx, dy);

      if (dist > 3) {
        const moveDist = a.speed * this.speed * 1.5;
        a.x += (dx / dist) * Math.min(moveDist, dist);
        a.y += (dy / dist) * Math.min(moveDist, dist);
        a.state = 'walking';
      } else {
        if (a.state === 'walking') {
          a.state = 'idle';
        }
      }

      // Случайные перемещения в режиме свободной жизни
      if (a.state === 'idle') {
        a.stateTimer -= 1 * this.speed;
        if (a.stateTimer <= 0) {
          a.stateTimer = 200 + Math.random() * 300;
          // Иногда гуляют около своего верстака
          const wanderRadius = 45;
          a.targetX = a.homeX + (Math.random() - 0.5) * wanderRadius;
          a.targetY = a.homeY + (Math.random() - 0.5) * wanderRadius;

          // Случайные мысли
          const randomThoughts = [
            'Проверяю калибровку T12...',
            'Интересно, какой ликвидус у Розе?',
            'ГОСТ 21931-76 строго запрещает перегрев флюса.',
            'Детектор слопа чист на 100%.',
            'Припой ПОС-61 смачивает медь за 1.2 секунды.'
          ];
          if (Math.random() < 0.25) {
            a.bubble = randomThoughts[Math.floor(Math.random() * randomThoughts.length)];
            a.bubbleTimer = 180;
          }
        }
      }

      // Таймер речевого облачка
      if (a.bubbleTimer > 0) {
        a.bubbleTimer -= 1 * this.speed;
        if (a.bubbleTimer <= 0) a.bubble = null;
      }
    }

    // Обновление паяльного дыма
    for (const p of this.ambientParticles) {
      p.x += p.vx * this.speed;
      p.y += p.vy * this.speed;
      p.radius += p.grow * this.speed;
      p.alpha -= 0.003 * this.speed;

      if (p.alpha <= 0 || p.y < 220) {
        p.x = 130 + (Math.random() - 0.5) * 15;
        p.y = 295;
        p.radius = 3 + Math.random() * 4;
        p.alpha = 0.35 + Math.random() * 0.3;
      }
    }
  }

  animate() {
    this.update();
    this.ctx.clearRect(0, 0, this.width, this.height);

    this.drawTownEnvironment();
    this.drawWorkstations();
    this.drawSmokeAndSparks();
    this.drawAgents();
    this.drawSpeechBubbles();
    this.drawHUD();

    requestAnimationFrame(() => this.animate());
  }

  drawTownEnvironment() {
    const ctx = this.ctx;

    // 1. Пол мастерской (Теплый индустриальный паркет + цеховая плитка)
    ctx.fillStyle = '#18181D';
    ctx.fillRect(0, 0, this.width, this.height);

    // Доски пола
    ctx.strokeStyle = '#22222A';
    ctx.lineWidth = 1;
    const tileW = 40;
    const tileH = 24;
    for (let y = 0; y < this.height; y += tileH) {
      ctx.beginPath();
      ctx.moveTo(0, y);
      ctx.lineTo(this.width, y);
      ctx.stroke();
    }
    for (let x = 0; x < this.width; x += tileW) {
      ctx.beginPath();
      ctx.moveTo(x, 0);
      ctx.lineTo(x, this.height);
      ctx.stroke();
    }

    // 2. Зоны цехов (ковровые дорожки / разметка)
    // Центральная дорожка
    ctx.fillStyle = 'rgba(255, 199, 0, 0.04)';
    ctx.fillRect(360, 40, 240, 460);
    ctx.strokeStyle = '#3F3F46';
    ctx.setLineDash([4, 4]);
    ctx.strokeRect(360, 40, 240, 460);
    ctx.setLineDash([]);

    // Стены и колонны
    ctx.fillStyle = '#27272A';
    ctx.fillRect(0, 0, this.width, 24);
    ctx.fillStyle = '#FFC700';
    ctx.font = 'bold 10px monospace';
    ctx.fillText('МАСТЕРСКАЯ АРТЕЛИ ЗВЕРЕЙ-МАСТЕРОВ · TOCHKA-PLAVLENIYA.RU · EDGE AI ECOSYSTEM', 20, 16);
  }

  drawWorkstations() {
    const ctx = this.ctx;

    Object.values(this.workstations).forEach(ws => {
      // Тень верстака
      ctx.fillStyle = 'rgba(0,0,0,0.4)';
      ctx.beginPath();
      ctx.ellipse(ws.x, ws.y + 18, 38, 16, 0, 0, Math.PI * 2);
      ctx.fill();

      // Стол / Верстак
      ctx.fillStyle = '#222228';
      ctx.strokeStyle = ws.color;
      ctx.lineWidth = 2;
      ctx.beginPath();
      ctx.roundRect(ws.x - 36, ws.y - 18, 72, 36, 6);
      ctx.fill();
      ctx.stroke();

      // Иконка верстака
      ctx.font = '18px sans-serif';
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillText(ws.icon, ws.x, ws.y - 2);

      // Название верстака
      ctx.font = 'bold 10px sans-serif';
      ctx.fillStyle = '#E4E4E7';
      ctx.fillText(ws.name.split(' (')[0], ws.x, ws.y + 30);
    });
  }

  drawSmokeAndSparks() {
    const ctx = this.ctx;

    // Отрисовка дыма паяльника
    for (const p of this.ambientParticles) {
      ctx.fillStyle = `rgba(220, 220, 230, ${p.alpha})`;
      ctx.beginPath();
      ctx.arc(p.x, p.y, p.radius, 0, Math.PI * 2);
      ctx.fill();
    }

    // Искры пайки у Кота
    if (Math.random() < 0.3) {
      ctx.fillStyle = '#FFC700';
      ctx.beginPath();
      ctx.arc(130 + (Math.random() - 0.5) * 12, 298 + (Math.random() - 0.5) * 8, 2, 0, Math.PI * 2);
      ctx.fill();
    }
  }

  drawAgents() {
    const ctx = this.ctx;
    const now = Date.now() * 0.005;

    this.agents.forEach(a => {
      const isSelected = (this.selectedAgent && this.selectedAgent.id === a.id);
      const bobbing = (a.state === 'walking') ? Math.sin(now * 4) * 3 : Math.sin(now) * 1.5;

      // Тень под ногами
      ctx.fillStyle = 'rgba(0,0,0,0.5)';
      ctx.beginPath();
      ctx.ellipse(a.x, a.y + 14, 16, 7, 0, 0, Math.PI * 2);
      ctx.fill();

      // Аура выбора
      if (isSelected) {
        ctx.save();
        ctx.shadowColor = '#FFC700';
        ctx.shadowBlur = 18;
        ctx.strokeStyle = '#FFC700';
        ctx.lineWidth = 2.5;
        ctx.beginPath();
        ctx.arc(a.x, a.y + bobbing, 22, 0, Math.PI * 2);
        ctx.stroke();
        ctx.restore();
      }

      // Тело персонажа (Круглый аватар)
      ctx.fillStyle = '#27272A';
      ctx.beginPath();
      ctx.arc(a.x, a.y + bobbing, 18, 0, Math.PI * 2);
      ctx.fill();
      ctx.strokeStyle = a.color;
      ctx.lineWidth = 2;
      ctx.stroke();

      // Эмодзи персонажа
      ctx.font = '20px sans-serif';
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillText(a.icon, a.x, a.y + bobbing);

      // Имя
      ctx.font = 'bold 10px sans-serif';
      ctx.fillStyle = isSelected ? '#FFC700' : '#FFFFFF';
      ctx.fillText(a.name.split(' (')[0], a.x, a.y + 28);

      // Предмет в руках (Inventory item)
      if (a.holdingItem) {
        ctx.fillStyle = '#09090B';
        ctx.strokeStyle = '#FFC700';
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.roundRect(a.x + 10, a.y - 15 + bobbing, 60, 16, 4);
        ctx.fill();
        ctx.stroke();
        ctx.font = 'bold 8.5px sans-serif';
        ctx.fillStyle = '#FFC700';
        ctx.fillText(a.holdingItem, a.x + 40, a.y - 7 + bobbing);
      }
    });
  }

  drawSpeechBubbles() {
    const ctx = this.ctx;

    this.agents.forEach(a => {
      if (!a.bubble) return;

      ctx.save();
      ctx.font = '10.5px sans-serif';
      const textWidth = ctx.measureText(a.bubble).width;
      const bW = Math.min(Math.max(textWidth + 18, 90), 220);
      const bH = 26;
      const bx = a.x;
      const by = a.y - 40;

      // Облачко диалога
      ctx.fillStyle = 'rgba(9, 9, 11, 0.94)';
      ctx.strokeStyle = '#FFC700';
      ctx.lineWidth = 1.2;
      ctx.beginPath();
      ctx.roundRect(bx - bW / 2, by - bH / 2, bW, bH, 6);
      ctx.fill();
      ctx.stroke();

      // Хвостик облачка
      ctx.beginPath();
      ctx.moveTo(bx - 4, by + bH / 2);
      ctx.lineTo(bx, by + bH / 2 + 6);
      ctx.lineTo(bx + 4, by + bH / 2);
      ctx.fillStyle = '#09090B';
      ctx.fill();
      ctx.stroke();

      // Текст реплики
      ctx.fillStyle = '#F4F4F5';
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillText(a.bubble, bx, by);
      ctx.restore();
    });
  }

  drawHUD() {
    const ctx = this.ctx;

    // Панель выбранного персонажа в углу
    if (this.selectedAgent) {
      const a = this.selectedAgent;
      ctx.fillStyle = 'rgba(15, 15, 19, 0.95)';
      ctx.strokeStyle = a.color;
      ctx.lineWidth = 1.5;
      ctx.beginPath();
      ctx.roundRect(14, this.height - 76, 260, 62, 6);
      ctx.fill();
      ctx.stroke();

      ctx.font = '24px sans-serif';
      ctx.textAlign = 'left';
      ctx.fillText(a.icon, 24, this.height - 42);

      ctx.font = 'bold 11px sans-serif';
      ctx.fillStyle = '#FFFFFF';
      ctx.fillText(a.name, 58, this.height - 56);

      ctx.font = '9.5px sans-serif';
      ctx.fillStyle = '#A1A1AA';
      ctx.fillText(`Роль: ${a.role} · Предмет: ${a.holdingItem || 'Руки свободны'}`, 58, this.height - 42);

      ctx.font = 'italic 9px sans-serif';
      ctx.fillStyle = '#FFC700';
      ctx.fillText(`«${a.thoughts}»`, 58, this.height - 28);
    }
  }
}

// Глобальная инициализация 2D-движка
document.addEventListener('DOMContentLoaded', () => {
  if (document.getElementById('farmInteractiveCanvas')) {
    window.aiTown2D = new AITownEngine('farmInteractiveCanvas');
    if (!window.aiTown) window.aiTown = window.aiTown2D;
  }
});
