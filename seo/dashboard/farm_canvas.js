/**
 * Интерактивная Живая Ферма Зверей-Мастеров (Interactive PCB Animated Canvas)
 * Вдохновлено проектами aadityasp/visual-workflows и a16z/ai-town.
 * Чистый Vanilla JS + HTML5 Canvas (0 внешних зависимостей, 60 FPS).
 */

class FarmInteractiveCanvas {
  constructor(canvasId) {
    this.canvas = document.getElementById(canvasId);
    if (!this.canvas) return;
    this.ctx = this.canvas.getContext('2d');
    this.nodes = [];
    this.edges = [];
    this.particles = [];
    this.hoveredNode = null;
    this.activeNodeId = null;
    this.animFrame = null;
    this.simulating = false;

    this.initNodes();
    this.initEdges();
    this.setupEvents();
    this.resize();
    window.addEventListener('resize', () => this.resize());
    this.animate();
  }

  resize() {
    if (!this.canvas) return;
    const rect = this.canvas.parentElement.getBoundingClientRect();
    const dpr = window.devicePixelRatio || 1;
    this.width = rect.width || 900;
    this.height = 420;
    this.canvas.width = this.width * dpr;
    this.canvas.height = this.height * dpr;
    this.canvas.style.width = this.width + 'px';
    this.canvas.style.height = this.height + 'px';
    this.ctx.scale(dpr, dpr);
    this.layoutNodes();
  }

  initNodes() {
    this.nodes = [
      { id: 'card-craftsman-analyzer', key: 'soroka', name: 'Сорока-Разведчица', icon: '🪶', role: '00-research', sub: 'Парсинг выдачи & LSI', color: '#38BDF8', state: 'idle', xPct: 0.10, yPct: 0.25 },
      { id: 'card-craftsman-researcher', key: 'bobr', name: 'Бобёр-Конструктор', icon: '🦫', role: '01-researcher', sub: 'Бриф, ГОСТ & FAQ', color: '#A16207', state: 'idle', xPct: 0.28, yPct: 0.25 },
      { id: 'cluster-core', key: 'triad', name: 'Кластер Triad-Omni', icon: '🐺', role: 'NODE 1-3 & OmniRoute', sub: 'SD865 + SD690 + SD625', color: '#10B981', state: 'online', xPct: 0.50, yPct: 0.20 },
      { id: 'card-craftsman-writer', key: 'kot', name: 'Кот-Паяльщик (P1)', icon: '🐱', role: '02-writer', sub: 'Ведущий инженер · Триада', color: '#EA580C', state: 'idle', xPct: 0.40, yPct: 0.60 },
      { id: 'card-craftsman-writer2', key: 'lis', name: 'Лис-Прагматик (P2)', icon: '🦊', role: '02-writer-2', sub: 'Голос заказчика · Сметы', color: '#F59E0B', state: 'idle', xPct: 0.60, yPct: 0.60 },
      { id: 'card-craftsman-illustrator', key: 'barsuk', name: 'Барсук-Иллюстратор', icon: '🦡', role: '05-illustrator', sub: 'SVG-термопрофили & Hero', color: '#EAB308', state: 'idle', xPct: 0.74, yPct: 0.35 },
      { id: 'card-craftsman-reviewer', key: 'filin', name: 'Филин-Ревизор [ОТК]', icon: '🦉', role: '03-reviewer', sub: 'Фактчек + SlopMonster', color: '#8B5CF6', state: 'idle', xPct: 0.88, yPct: 0.35 },
      { id: 'card-craftsman-scorer', key: 'dyatel', name: 'Дятел-Метролог', icon: '🪵', role: '04-scorer', sub: 'Математика 0–100', color: '#EC4899', state: 'idle', xPct: 0.82, yPct: 0.78 },
      { id: 'production-wp', key: 'wp', name: 'WordPress / Читатель', icon: '🚀', role: 'production', sub: 'TechArticle + Schema.org', color: '#06B6D4', state: 'ready', xPct: 0.95, yPct: 0.78 },
    ];
  }

  layoutNodes() {
    this.nodes.forEach(n => {
      n.x = n.xPct * this.width;
      n.y = n.yPct * this.height;
    });
  }

  initEdges() {
    // Связи конвейера Артели на печатной плате
    this.edges = [
      { from: 'soroka', to: 'bobr', label: 'LSI & сниппеты', speed: 1.2 },
      { from: 'bobr', to: 'triad', label: 'Контекст в RAG', speed: 1.5 },
      { from: 'triad', to: 'kot', label: 'Промпт Practico 1', speed: 1.8 },
      { from: 'triad', to: 'lis', label: 'Промпт Practico 2', speed: 1.4 },
      { from: 'kot', to: 'barsuk', label: 'Текст & сплав', speed: 1.6 },
      { from: 'lis', to: 'barsuk', label: 'Смета', speed: 1.2 },
      { from: 'barsuk', to: 'filin', label: 'Черновик + SVG', speed: 1.7 },
      { from: 'filin', to: 'dyatel', label: 'Паспорт v1.1', speed: 2.0 },
      { from: 'dyatel', to: 'wp', label: 'Score >= 85 (Релиз)', speed: 2.2 },
    ];

    // Инициализация фоновых частиц
    for (let i = 0; i < 35; i++) {
      const e = this.edges[Math.floor(Math.random() * this.edges.length)];
      this.particles.push({
        edge: e,
        progress: Math.random(),
        speed: (0.003 + Math.random() * 0.004) * e.speed,
        size: 2.5 + Math.random() * 2,
        color: Math.random() > 0.4 ? '#FFC700' : '#38BDF8'
      });
    }
  }

  setupEvents() {
    this.canvas.addEventListener('mousemove', (e) => {
      const rect = this.canvas.getBoundingClientRect();
      const mx = e.clientX - rect.left;
      const my = e.clientY - rect.top;

      let found = null;
      for (const n of this.nodes) {
        const dx = mx - n.x;
        const dy = my - n.y;
        if (Math.sqrt(dx * dx + dy * dy) <= 32) {
          found = n;
          break;
        }
      }

      this.hoveredNode = found;
      this.canvas.style.cursor = found ? 'pointer' : 'default';
    });

    this.canvas.addEventListener('click', () => {
      if (this.hoveredNode) {
        this.triggerNodeAction(this.hoveredNode);
      }
    });
  }

  triggerNodeAction(node) {
    this.activeNodeId = node.key;
    // Подсвечиваем соответствующую карточку внизу
    if (node.id && document.getElementById(node.id)) {
      document.querySelectorAll('.craftsman-card').forEach(el => el.classList.remove('craftsman-highlighted'));
      const card = document.getElementById(node.id);
      card.classList.add('craftsman-highlighted');
      card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
      setTimeout(() => card.classList.remove('craftsman-highlighted'), 5000);
    }
    // Озвучиваем статус мастера Бригадиром
    if (typeof askAssistant === 'function') {
      askAssistant(`Какой статус у мастера ${node.name}?`);
    }
  }

  simulateFullFlow() {
    this.simulating = true;
    let step = 0;
    const flowOrder = ['soroka', 'bobr', 'kot', 'barsuk', 'filin', 'dyatel', 'wp'];

    const interval = setInterval(() => {
      if (step >= flowOrder.length) {
        clearInterval(interval);
        this.simulating = false;
        this.activeNodeId = null;
        return;
      }
      this.activeNodeId = flowOrder[step];
      const node = this.nodes.find(n => n.key === this.activeNodeId);
      if (node && typeof speakText === 'function') {
        speakText(`Верстак активен: ${node.name}`);
      }
      // Создаем взрыв искр
      this.spawnSparks(node.x, node.y);
      step++;
    }, 1800);
  }

  spawnSparks(x, y) {
    for (let i = 0; i < 20; i++) {
      const angle = Math.random() * Math.PI * 2;
      const speed = 1 + Math.random() * 3;
      this.particles.push({
        isSpark: true,
        x: x,
        y: y,
        vx: Math.cos(angle) * speed,
        vy: Math.sin(angle) * speed,
        life: 1.0,
        decay: 0.03 + Math.random() * 0.04,
        size: 2 + Math.random() * 3,
        color: '#FFC700'
      });
    }
  }

  getNode(key) {
    return this.nodes.find(n => n.key === key);
  }

  animate() {
    this.ctx.clearRect(0, 0, this.width, this.height);

    this.drawPCBBackground();
    this.drawTraces();
    this.drawParticles();
    this.drawNodes();
    this.drawOverlayTooltip();

    this.animFrame = requestAnimationFrame(() => this.animate());
  }

  drawPCBBackground() {
    const ctx = this.ctx;
    // Темная подложка печатной платы
    ctx.fillStyle = '#0F0F13';
    ctx.fillRect(0, 0, this.width, this.height);

    // Сетка контактных дорожек
    ctx.strokeStyle = '#181820';
    ctx.lineWidth = 1;
    const grid = 28;
    for (let x = 0; x < this.width; x += grid) {
      ctx.beginPath();
      ctx.moveTo(x, 0);
      ctx.lineTo(x, this.height);
      ctx.stroke();
    }
    for (let y = 0; y < this.height; y += grid) {
      ctx.beginPath();
      ctx.moveTo(0, y);
      ctx.lineTo(this.width, y);
      ctx.stroke();
    }

    // Монтажные металлические винты по углам
    const screws = [
      { x: 20, y: 20 },
      { x: this.width - 20, y: 20 },
      { x: 20, y: this.height - 20 },
      { x: this.width - 20, y: this.height - 20 }
    ];
    screws.forEach(s => {
      ctx.fillStyle = '#27272A';
      ctx.beginPath();
      ctx.arc(s.x, s.y, 8, 0, Math.PI * 2);
      ctx.fill();
      ctx.strokeStyle = '#3F3F46';
      ctx.lineWidth = 1.5;
      ctx.stroke();
      ctx.beginPath();
      ctx.moveTo(s.x - 4, s.y - 4);
      ctx.lineTo(s.x + 4, s.y + 4);
      ctx.stroke();
    });
  }

  drawTraces() {
    const ctx = this.ctx;

    this.edges.forEach(e => {
      const fromNode = this.getNode(e.from);
      const toNode = this.getNode(e.to);
      if (!fromNode || !toNode) return;

      const isHighlight = (this.activeNodeId === e.from || this.activeNodeId === e.to ||
                           (this.hoveredNode && (this.hoveredNode.key === e.from || this.hoveredNode.key === e.to)));

      // Медная дорожка
      ctx.beginPath();
      ctx.moveTo(fromNode.x, fromNode.y);
      const midX = (fromNode.x + toNode.x) / 2;
      const midY = (fromNode.y + toNode.y) / 2;
      ctx.quadraticCurveTo(midX, fromNode.y, toNode.x, toNode.y);

      ctx.strokeStyle = isHighlight ? '#FFC700' : '#27272A';
      ctx.lineWidth = isHighlight ? 3.5 : 2;
      ctx.stroke();

      // Переходные золотые отверстия (Vias)
      ctx.fillStyle = isHighlight ? '#FFC700' : '#52525B';
      ctx.beginPath();
      ctx.arc(midX, (fromNode.y + toNode.y) / 2, isHighlight ? 4 : 2.5, 0, Math.PI * 2);
      ctx.fill();
    });
  }

  drawParticles() {
    const ctx = this.ctx;

    for (let i = this.particles.length - 1; i >= 0; i--) {
      const p = this.particles[i];

      // Отрисовка искр
      if (p.isSpark) {
        p.x += p.vx;
        p.y += p.vy;
        p.life -= p.decay;
        if (p.life <= 0) {
          this.particles.splice(i, 1);
          continue;
        }
        ctx.fillStyle = p.color;
        ctx.globalAlpha = p.life;
        ctx.beginPath();
        ctx.arc(p.x, p.y, p.size * p.life, 0, Math.PI * 2);
        ctx.fill();
        ctx.globalAlpha = 1.0;
        continue;
      }

      // Перемещение вдоль дорожки
      p.progress += p.speed;
      if (p.progress >= 1.0) p.progress = 0;

      const fromNode = this.getNode(p.edge.from);
      const toNode = this.getNode(p.edge.to);
      if (!fromNode || !toNode) continue;

      const t = p.progress;
      const midX = (fromNode.x + toNode.x) / 2;
      // Квадратичная кривая Безье
      const px = (1 - t) * (1 - t) * fromNode.x + 2 * (1 - t) * t * midX + t * t * toNode.x;
      const py = (1 - t) * (1 - t) * fromNode.y + 2 * (1 - t) * t * fromNode.y + t * t * toNode.y;

      const isBoosted = (this.activeNodeId === p.edge.from || this.activeNodeId === p.edge.to);

      ctx.save();
      ctx.shadowColor = isBoosted ? '#FFC700' : p.color;
      ctx.shadowBlur = isBoosted ? 12 : 6;
      ctx.fillStyle = isBoosted ? '#FFFFFF' : p.color;
      ctx.beginPath();
      ctx.arc(px, py, isBoosted ? p.size * 1.5 : p.size, 0, Math.PI * 2);
      ctx.fill();
      ctx.restore();
    }
  }

  drawNodes() {
    const ctx = this.ctx;
    const now = Date.now() * 0.003;

    this.nodes.forEach(n => {
      const isHovered = (this.hoveredNode && this.hoveredNode.key === n.key);
      const isActive = (this.activeNodeId === n.key);

      // Внешнее свечение при активности
      if (isActive || isHovered) {
        ctx.save();
        ctx.shadowColor = n.color;
        ctx.shadowBlur = isActive ? 24 : 14;
        ctx.strokeStyle = n.color;
        ctx.lineWidth = 3;
        ctx.beginPath();
        ctx.arc(n.x, n.y, 34 + Math.sin(now * 3) * 2, 0, Math.PI * 2);
        ctx.stroke();
        ctx.restore();
      }

      // Подложка контактной площадки (SMD Pad)
      ctx.fillStyle = '#18181B';
      ctx.beginPath();
      ctx.arc(n.x, n.y, 28, 0, Math.PI * 2);
      ctx.fill();
      ctx.strokeStyle = isActive ? '#FFC700' : (isHovered ? n.color : '#3F3F46');
      ctx.lineWidth = isActive ? 2.5 : 1.5;
      ctx.stroke();

      // Отрисовка эмодзи-аватара мастера
      ctx.font = '22px sans-serif';
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillText(n.icon, n.x, n.y);

      // Подпись мастера
      ctx.font = 'bold 11px sans-serif';
      ctx.fillStyle = isActive ? '#FFC700' : (isHovered ? '#FFFFFF' : '#E4E4E7');
      ctx.fillText(n.name, n.x, n.y + 40);

      ctx.font = '9px monospace';
      ctx.fillStyle = '#A1A1AA';
      ctx.fillText(n.role, n.x, n.y + 52);

      // Индикатор статуса
      const pulseColor = isActive ? '#FFC700' : (n.state === 'online' ? '#10B981' : '#71717A');
      ctx.fillStyle = pulseColor;
      ctx.beginPath();
      ctx.arc(n.x + 20, n.y - 18, 4, 0, Math.PI * 2);
      ctx.fill();
    });
  }

  drawOverlayTooltip() {
    if (!this.hoveredNode) return;
    const n = this.hoveredNode;
    const ctx = this.ctx;

    const tx = Math.min(Math.max(n.x, 110), this.width - 110);
    const ty = n.y - 50;

    ctx.save();
    ctx.fillStyle = 'rgba(9, 9, 11, 0.95)';
    ctx.strokeStyle = '#FFC700';
    ctx.lineWidth = 1;
    ctx.beginPath();
    ctx.roundRect(tx - 90, ty - 24, 180, 42, 6);
    ctx.fill();
    ctx.stroke();

    ctx.fillStyle = '#FFC700';
    ctx.font = 'bold 11px sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText(n.name, tx, ty - 8);

    ctx.fillStyle = '#D4D4D8';
    ctx.font = '10px sans-serif';
    ctx.fillText(n.sub, tx, ty + 8);
    ctx.restore();
  }
}

// Автоинициализация при загрузке
document.addEventListener('DOMContentLoaded', () => {
  if (document.getElementById('farmInteractiveCanvas')) {
    window.farmCanvas = new FarmInteractiveCanvas('farmInteractiveCanvas');
  }
});
