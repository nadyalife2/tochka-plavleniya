/**
 * 🎮 3D ИНЖЕНЕРНАЯ ФЕРМА АРТЕЛИ ЗВЕРЕЙ-МАСТЕРОВ v3.0 (Three.js WebGL Engine)
 * - Управляемый игрок: Медведь-Бригадир (Локальный агент Triad-Omni на 3 смартфонах).
 *     * Управление: WASD / Стрелки / Клик мыши.
 *     * Камера следует за персонажем.
 *     * Подход к любому мастеру -> Диалог и открытие верстака по нажатию ПРОБЕЛ / E / Клик.
 * - Интерактивный Верстак и Нормативная Документация (ГОСТы, правила триады, таблицы seo.db, AST-скоринг).
 * - Драматичная физика Смерти / Офлайна:
 *     * Если узел недоступен -> Зверь падает на бок (X_X), над ним череп 💀, табличка верстака краснеет.
 *     * Если включена автозамена -> Зверь встает и работает через резервный узел [ 🔄 АВТОЗАМЕНА ].
 * - Панель управления YAML-маршрутизацией и автозаменой прямо в интерфейсе.
 * - Визуализация физической передачи предметов из лап в лапы по дуге с подтверждением репликой.
 * Точка Плавления · tochka-plavleniya.ru · Triad-Omni Edge AI Cluster.
 */

(function() {
  'use strict';

  class Farm3DEngine {
    constructor(containerId) {
      this.container = document.getElementById(containerId);
      if (!this.container) return;

      this.scene = null;
      this.camera = null;
      this.renderer = null;
      this.controls = null;
      this.clock = new THREE.Clock();

      this.speed = 1.0;
      this.paused = false;
      this.selectedAgent = null;
      this.timeOfDay = 'day';

      this.agents = [];
      this.agentMeshes = {};
      this.stationConfigs = {};
      this.stationMeshes = {};
      this.windmills = [];
      this.flowLines = [];

      // Управление игроком (Медведь-Бригадир)
      this.playerAgent = null;
      this.keysPressed = {};
      this.nearestCraftsman = null;
      this.cameraFollow = true;

      // Системы эффектов
      this.sparksGroup = null;

      this.raycaster = new THREE.Raycaster();
      this.mouse = new THREE.Vector2();

      this.init();
    }

    init() {
      // 1. Сцена и чистое небо
      this.scene = new THREE.Scene();
      this.scene.background = new THREE.Color(0x93C5FD);
      this.scene.fog = new THREE.FogExp2(0x93C5FD, 0.009);

      // 2. Камера
      const width = this.container.clientWidth || 1000;
      const height = this.container.clientHeight || 560;
      this.camera = new THREE.PerspectiveCamera(40, width / height, 0.5, 300);
      this.camera.position.set(28, 24, 36);

      // 3. WebGL Рендерер
      this.renderer = new THREE.WebGLRenderer({ antialias: true, powerPreference: 'high-performance' });
      this.renderer.setSize(width, height);
      this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
      this.renderer.shadowMap.enabled = true;
      this.renderer.shadowMap.type = THREE.PCFSoftShadowMap;
      this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
      this.renderer.toneMappingExposure = 1.08;
      this.container.appendChild(this.renderer.domElement);

      // 4. OrbitControls
      if (typeof THREE.OrbitControls !== 'undefined') {
        this.controls = new THREE.OrbitControls(this.camera, this.renderer.domElement);
        this.controls.enableDamping = true;
        this.controls.dampingFactor = 0.06;
        this.controls.maxPolarAngle = Math.PI / 2.08;
        this.controls.minDistance = 8;
        this.controls.maxDistance = 110;
        this.controls.target.set(0, 1.2, 0);
      }

      // 5. Освещение
      this.setupLighting();

      // 6. Чистый ландшафт фермы (без загромождающего антуража)
      this.buildCleanFarmEnvironment();

      // 7. Постройки и информационные 3D-таблички верстаков
      this.buildStationsWithPlacards();

      // 8. 3D Анимированные Персонажи с рабочими инструментами
      this.build3DAgentsWithTools();

      // 9. Назначение игрового аватара (Медведь-Бригадир — локальный агент Triad-Omni)
      this.setupPlayerAvatar();

      // 10. Рабочие эффекты (искры пайки)
      this.setupWorkEffects();

      // 11. Обработчики ввода (WASD + мышь)
      this.setupInteraction();

      // 12. Первичный запрос статуса здоровья и автозамены
      this.fetchInitialHealth();

      // 13. Анимационный цикл
      window.addEventListener('resize', () => this.onWindowResize());
      this.animate();
    }

    setupLighting() {
      this.hemiLight = new THREE.HemisphereLight(0xDCEEFF, 0x3E5E2A, 0.7);
      this.scene.add(this.hemiLight);

      this.dirLight = new THREE.DirectionalLight(0xFFF7E6, 1.3);
      this.dirLight.position.set(30, 40, 25);
      this.dirLight.castShadow = true;
      this.dirLight.shadow.mapSize.width = 2048;
      this.dirLight.shadow.mapSize.height = 2048;
      this.dirLight.shadow.camera.near = 1;
      this.dirLight.shadow.camera.far = 130;
      const d = 40;
      this.dirLight.shadow.camera.left = -d;
      this.dirLight.shadow.camera.right = d;
      this.dirLight.shadow.camera.top = d;
      this.dirLight.shadow.camera.bottom = -d;
      this.dirLight.shadow.bias = -0.0005;
      this.scene.add(this.dirLight);

      this.ambientLight = new THREE.AmbientLight(0xFFFFFF, 0.35);
      this.scene.add(this.ambientLight);

      this.forgeLight = new THREE.PointLight(0xFF6600, 1.8, 14);
      this.forgeLight.position.set(-16, 2.5, 7);
      this.scene.add(this.forgeLight);
    }

    buildCleanFarmEnvironment() {
      // Зеленая рабочая площадка
      const groundGeo = new THREE.PlaneGeometry(95, 95);
      const groundMat = new THREE.MeshStandardMaterial({ color: 0x4F8A4B, roughness: 0.9, flatShading: true });
      const ground = new THREE.Mesh(groundGeo, groundMat);
      ground.rotation.x = -Math.PI / 2;
      ground.receiveShadow = true;
      this.scene.add(ground);
      this.groundMesh = ground;

      // Пашня у Бобра
      const fieldW = 22;
      const fieldD = 14;
      const soilGeo = new THREE.BoxGeometry(fieldW, 0.15, fieldD);
      const soilMat = new THREE.MeshStandardMaterial({ color: 0x452B19, roughness: 0.95 });
      const field = new THREE.Mesh(soilGeo, soilMat);
      field.position.set(-18, 0.08, -16);
      field.receiveShadow = true;
      this.scene.add(field);

      // Борозды и ростки
      const ridgeMat = new THREE.MeshStandardMaterial({ color: 0x341F11, roughness: 1.0 });
      const sproutMat = new THREE.MeshStandardMaterial({ color: 0x86EFAC });
      for (let z = -fieldD / 2 + 1.4; z < fieldD / 2 - 1; z += 2.2) {
        const ridge = new THREE.Mesh(new THREE.BoxGeometry(fieldW - 1.5, 0.18, 1.0), ridgeMat);
        ridge.position.set(-18, 0.22, -16 + z);
        this.scene.add(ridge);

        for (let x = -fieldW / 2 + 2; x < fieldW / 2 - 1.5; x += 2.4) {
          const sprout = new THREE.Mesh(new THREE.ConeGeometry(0.18, 0.45, 4), sproutMat);
          sprout.position.set(-18 + x, 0.42, -16 + z);
          this.scene.add(sprout);
        }
      }

      // Изгородь пашни
      this.buildFence(-29, -16, 22, 14);

      // Ручей с деревянным мостиком
      const streamGeo = new THREE.PlaneGeometry(8, 65);
      const waterMat = new THREE.MeshStandardMaterial({ color: 0x38BDF8, roughness: 0.1, metalness: 0.2, transparent: true, opacity: 0.85 });
      const stream = new THREE.Mesh(streamGeo, waterMat);
      stream.position.set(-7, 0.04, -2);
      stream.rotation.x = -Math.PI / 2;
      stream.rotation.z = 0.22;
      this.scene.add(stream);

      this.buildBridge(-7, 0.2, 0, 0.22);

      // Песчаные дорожки между верстаками
      this.buildCobblePaths();

      // Деревья строго по дальнему периметру
      this.placePerimeterTrees();
    }

    buildFence(cx, cz, w, d) {
      const fenceMat = new THREE.MeshStandardMaterial({ color: 0x8C5A36, roughness: 0.9 });
      const postGeo = new THREE.CylinderGeometry(0.08, 0.1, 1.2, 6);
      const railGeoX = new THREE.BoxGeometry(w, 0.06, 0.06);

      const r1 = new THREE.Mesh(railGeoX, fenceMat);
      r1.position.set(cx + w / 2, 0.6, cz - d / 2);
      this.scene.add(r1);

      const r2 = new THREE.Mesh(railGeoX, fenceMat);
      r2.position.set(cx + w / 2, 0.6, cz + d / 2);
      this.scene.add(r2);

      for (let x = cx; x <= cx + w; x += 3.2) {
        const p1 = new THREE.Mesh(postGeo, fenceMat);
        p1.position.set(x, 0.6, cz - d / 2);
        this.scene.add(p1);

        const p2 = new THREE.Mesh(postGeo, fenceMat);
        p2.position.set(x, 0.6, cz + d / 2);
        this.scene.add(p2);
      }
    }

    buildBridge(x, y, z, rotY) {
      const bridge = new THREE.Group();
      const woodMat = new THREE.MeshStandardMaterial({ color: 0x784D2B, roughness: 0.85 });

      const deck = new THREE.Mesh(new THREE.BoxGeometry(3.6, 0.15, 6.0), woodMat);
      deck.receiveShadow = true;
      bridge.add(deck);

      const railGeo = new THREE.BoxGeometry(0.08, 0.08, 6.0);
      const rL = new THREE.Mesh(railGeo, woodMat);
      rL.position.set(-1.7, 0.7, 0);
      bridge.add(rL);

      const rR = new THREE.Mesh(railGeo, woodMat);
      rR.position.set(1.7, 0.7, 0);
      bridge.add(rR);

      bridge.position.set(x, y, z);
      bridge.rotation.y = rotY;
      this.scene.add(bridge);
    }

    buildCobblePaths() {
      const pathMat = new THREE.MeshStandardMaterial({ color: 0xD4C39C, roughness: 0.95 });
      const paths = [
        { geo: new THREE.CylinderGeometry(7.5, 7.5, 0.03, 16), x: 0, y: 0.02, z: 0 },
        { geo: new THREE.BoxGeometry(16, 0.03, 2.8), x: -8, y: 0.02, z: 5, rotY: 0.1 },
        { geo: new THREE.BoxGeometry(2.8, 0.03, 16), x: -14, y: 0.02, z: -14 },
        { geo: new THREE.BoxGeometry(16, 0.03, 2.8), x: 10, y: 0.02, z: 3, rotY: 0.15 },
        { geo: new THREE.BoxGeometry(14, 0.03, 2.8), x: 9, y: 0.02, z: -8, rotY: -0.5 },
        { geo: new THREE.BoxGeometry(2.8, 0.03, 18), x: 0, y: 0.02, z: 10 }
      ];

      paths.forEach(p => {
        const mesh = new THREE.Mesh(p.geo, pathMat);
        mesh.position.set(p.x, p.y, p.z);
        if (p.rotY) mesh.rotation.y = p.rotY;
        mesh.receiveShadow = true;
        this.scene.add(mesh);
      });
    }

    placePerimeterTrees() {
      const coords = [
        { x: -36, z: -30, type: 'pine' },
        { x: 34, z: -28, type: 'pine' },
        { x: 38, z: 22, type: 'oak' },
        { x: -38, z: 24, type: 'birch' },
        { x: 0, z: -36, type: 'birch' },
        { x: 26, z: 28, type: 'oak' }
      ];
      coords.forEach(c => {
        const tree = this.createTree(c.type);
        tree.position.set(c.x, 0, c.z);
        this.scene.add(tree);
      });
    }

    createTree(type) {
      const g = new THREE.Group();
      if (type === 'pine') {
        const trunk = new THREE.Mesh(new THREE.CylinderGeometry(0.3, 0.45, 3.2, 6), new THREE.MeshStandardMaterial({ color: 0x4E3524 }));
        trunk.position.y = 1.6;
        g.add(trunk);
        const foliage = new THREE.Mesh(new THREE.ConeGeometry(2.2, 5.0, 6), new THREE.MeshStandardMaterial({ color: 0x2A623D }));
        foliage.position.y = 4.8;
        g.add(foliage);
      } else {
        const trunk = new THREE.Mesh(new THREE.CylinderGeometry(0.35, 0.5, 3.0, 6), new THREE.MeshStandardMaterial({ color: 0x5C381E }));
        trunk.position.y = 1.5;
        g.add(trunk);
        const crown = new THREE.Mesh(new THREE.DodecahedronGeometry(2.2, 1), new THREE.MeshStandardMaterial({ color: 0x4A853D, flatShading: true }));
        crown.position.y = 4.2;
        g.add(crown);
      }
      return g;
    }

    // ==========================================
    // СТАНЦИИ И ИНФОРМАЦИОННЫЕ ТАБЛИЧКИ
    // ==========================================
    buildStationsWithPlacards() {
      this.stationConfigs = {
        soroka: {
          id: 'ws_soroka', name: 'БЮРО РАЗВЕДКИ (СОРОКА)', icon: '🪶',
          pos: new THREE.Vector3(-14, 0, -20), colorHex: 0x38BDF8,
          taskTitle: 'СДЕЛАТЬ: Сбор LSI-ядра',
          taskSubtitle: 'Парсинг выдачи Яндекса и калькуляторов',
          status: 'ОЖИДАЕТ'
        },
        bobr: {
          id: 'ws_bobr', name: 'КОНСТРУКТОРСКОЕ БЮРО (БОБЁР)', icon: '🦫',
          pos: new THREE.Vector3(-20, 0, -6), colorHex: 0xB45309,
          taskTitle: 'УТВЕРДИТЬ: Бриф ГОСТ Р МЭК 61191',
          taskSubtitle: '4 обязательных вопроса FAQ новичка',
          status: 'ОЖИДАЕТ'
        },
        kot: {
          id: 'ws_kot', name: 'ПАЯЛЬНЫЙ ЦЕХ (КОТ-ПАЯЛЬЩИК)', icon: '🐱',
          pos: new THREE.Vector3(-16, 0, 7), colorHex: 0xEA580C,
          taskTitle: 'НАПИСАТЬ: Триада фактов и физика шва',
          taskSubtitle: 'Смачивание меди и ликвидус ПОС-61 (183–190°C)',
          status: 'ОЖИДАЕТ'
        },
        bear: {
          id: 'ws_bear', name: 'СОВЕТ АРТЕЛИ (МЕДВЕДЬ-БРИГАДИР)', icon: '🐻',
          pos: new THREE.Vector3(0, 0, 0), colorHex: 0xEAB308,
          taskTitle: 'КООРДИНАЦИЯ: Диспетчер конвейера',
          taskSubtitle: 'Балансировка ресурсов и энергопотребления',
          status: 'АКТИВЕН'
        },
        lis: {
          id: 'ws_lis', name: 'СМЕТНОЕ БЮРО (ЛИС-ПРАГМАТИК)', icon: '🦊',
          pos: new THREE.Vector3(16, 0, 3), colorHex: 0xF59E0B,
          taskTitle: 'ПОСЧИТАТЬ: Смета и окупаемость',
          taskSubtitle: 'Расчет стоимости флюса vs спасенной платы',
          status: 'ОЖИДАЕТ'
        },
        barsuk: {
          id: 'ws_barsuk', name: 'КУЛЬМАН СХЕМ (БАРСУК-ИЛЛЮСТРАТОР)', icon: '🦡',
          pos: new THREE.Vector3(-4, 0, 14), colorHex: 0xFBBF24,
          taskTitle: 'НАЧЕРТИТЬ: Векторный SVG-термопрофиль',
          taskSubtitle: 'Каноническая кривая ликвидуса по реестру',
          status: 'ОЖИДАЕТ'
        },
        filin: {
          id: 'ws_filin', name: 'СТЕНД ОТК & РЕВИЗОР (ФИЛИН)', icon: '🦉',
          pos: new THREE.Vector3(14, 0, -12), colorHex: 0x8B5CF6,
          taskTitle: 'ПЕРЕПРОВЕРИТЬ: ОТК & SlopMonster',
          taskSubtitle: 'Детекция нейроштампов и лингво-приемка',
          status: 'ОЖИДАЕТ'
        },
        dyatel: {
          id: 'ws_dyatel', name: 'МЕТРОЛОГИЯ 100 (ДЯТЕЛ-СКОРЕР)', icon: '🪵',
          pos: new THREE.Vector3(10, 0, 16), colorHex: 0xEC4899,
          taskTitle: 'СКОРИНГ: Метрология 100 баллов',
          taskSubtitle: 'AST-анализ 6 объективных блоков',
          status: 'ОЖИДАЕТ'
        },
        wp: {
          id: 'ws_wp', name: 'ШЛЮЗ РЕЛИЗА (WORDPRESS)', icon: '🚀',
          pos: new THREE.Vector3(25, 0, -4), colorHex: 0x06B6D4,
          taskTitle: 'ОПУБЛИКОВАТЬ: Экспорт в tochka-plavleniya',
          taskSubtitle: 'Поставка zip-пакета и Schema.org разметка',
          status: 'ГОТОВ'
        }
      };

      Object.entries(this.stationConfigs).forEach(([key, cfg]) => {
        const stationGroup = this.buildStationStructure(key, cfg);
        stationGroup.position.copy(cfg.pos);
        this.scene.add(stationGroup);
        this.stationMeshes[key] = stationGroup;

        this.createStationPlacard(key, cfg, stationGroup);
      });
    }

    buildStationStructure(key, cfg) {
      const g = new THREE.Group();
      g.userData = { id: cfg.id, name: cfg.name, icon: cfg.icon, key };

      if (key === 'soroka') {
        const tower = new THREE.Mesh(new THREE.CylinderGeometry(2.0, 2.6, 6.0, 8), new THREE.MeshStandardMaterial({ color: 0x828282 }));
        tower.position.y = 3.0;
        g.add(tower);

        const roof = new THREE.Mesh(new THREE.ConeGeometry(2.4, 2.2, 8), new THREE.MeshStandardMaterial({ color: 0x6E4423 }));
        roof.position.y = 7.1;
        g.add(roof);

        const bladeGroup = new THREE.Group();
        bladeGroup.position.set(0, 6.2, 1.8);
        const hub = new THREE.Mesh(new THREE.CylinderGeometry(0.35, 0.35, 0.4, 8), new THREE.MeshStandardMaterial({ color: 0x222222 }));
        hub.rotateX(Math.PI / 2);
        bladeGroup.add(hub);

        for (let i = 0; i < 4; i++) {
          const blade = new THREE.Mesh(new THREE.BoxGeometry(0.5, 4.4, 0.06), new THREE.MeshStandardMaterial({ color: 0xE8D7B8 }));
          blade.position.y = 2.4;
          const arm = new THREE.Group();
          arm.rotation.z = (i * Math.PI) / 2;
          arm.add(blade);
          bladeGroup.add(arm);
        }
        g.add(bladeGroup);
        this.windmills.push(bladeGroup);

        const table = new THREE.Mesh(new THREE.BoxGeometry(1.8, 0.8, 1.2), new THREE.MeshStandardMaterial({ color: 0x8C5A36 }));
        table.position.set(0, 0.4, 2.6);
        g.add(table);

      } else if (key === 'bobr') {
        const bench = new THREE.Mesh(new THREE.BoxGeometry(3.2, 0.9, 1.6), new THREE.MeshStandardMaterial({ color: 0x784A28 }));
        bench.position.set(0, 0.45, 0);
        g.add(bench);

        const log = new THREE.Mesh(new THREE.CylinderGeometry(0.3, 0.3, 2.8, 8), new THREE.MeshStandardMaterial({ color: 0x5C381E }));
        log.rotateZ(Math.PI / 2);
        log.position.set(0, 1.15, 0);
        g.add(log);

      } else if (key === 'kot') {
        const walls = new THREE.Mesh(new THREE.BoxGeometry(4.8, 2.8, 4.0), new THREE.MeshStandardMaterial({ color: 0x555555 }));
        walls.position.set(0, 1.4, 0);
        g.add(walls);

        const roof = new THREE.Mesh(new THREE.ConeGeometry(3.6, 1.6, 4), new THREE.MeshStandardMaterial({ color: 0x8B321D }));
        roof.rotateY(Math.PI / 4);
        roof.position.set(0, 3.6, 0);
        g.add(roof);

        const bench = new THREE.Mesh(new THREE.BoxGeometry(2.4, 0.85, 1.4), new THREE.MeshStandardMaterial({ color: 0x2A2A2A }));
        bench.position.set(0, 0.42, 2.8);
        g.add(bench);

        const pcb = new THREE.Mesh(new THREE.BoxGeometry(0.9, 0.05, 0.7), new THREE.MeshStandardMaterial({ color: 0x166534 }));
        pcb.position.set(0, 0.88, 2.8);
        g.add(pcb);

      } else if (key === 'bear') {
        const deck = new THREE.Mesh(new THREE.CylinderGeometry(4.2, 4.4, 0.25, 16), new THREE.MeshStandardMaterial({ color: 0x734826 }));
        deck.position.y = 0.12;
        g.add(deck);

        const table = new THREE.Mesh(new THREE.CylinderGeometry(2.0, 2.0, 0.8, 16), new THREE.MeshStandardMaterial({ color: 0x5C381E }));
        table.position.y = 0.52;
        g.add(table);

        const samovar = new THREE.Mesh(new THREE.CylinderGeometry(0.28, 0.25, 0.8, 8), new THREE.MeshStandardMaterial({ color: 0xEAB308, metalness: 0.9 }));
        samovar.position.set(0, 1.3, 0);
        g.add(samovar);

      } else if (key === 'lis') {
        const desk = new THREE.Mesh(new THREE.BoxGeometry(2.6, 1.0, 1.6), new THREE.MeshStandardMaterial({ color: 0x8C5831 }));
        desk.position.set(0, 0.5, 0);
        g.add(desk);

      } else if (key === 'barsuk') {
        const stand = new THREE.Mesh(new THREE.BoxGeometry(0.12, 1.6, 1.2), new THREE.MeshStandardMaterial({ color: 0x444444 }));
        stand.position.set(0, 0.8, 0);
        g.add(stand);

        const board = new THREE.Mesh(new THREE.BoxGeometry(1.8, 0.06, 1.3), new THREE.MeshStandardMaterial({ color: 0xFAF9F6 }));
        board.position.set(0, 1.3, 0.2);
        board.rotation.x = -0.45;
        g.add(board);

      } else if (key === 'filin') {
        const plat = new THREE.Mesh(new THREE.BoxGeometry(3.6, 0.25, 3.6), new THREE.MeshStandardMaterial({ color: 0x5C3B1E }));
        plat.position.y = 5.0;
        g.add(plat);

        [[-1.5, -1.5], [1.5, -1.5], [-1.5, 1.5], [1.5, 1.5]].forEach(([px, pz]) => {
          const leg = new THREE.Mesh(new THREE.CylinderGeometry(0.14, 0.18, 5.0, 6), new THREE.MeshStandardMaterial({ color: 0x3E2310 }));
          leg.position.set(px, 2.5, pz);
          g.add(leg);
        });

      } else if (key === 'dyatel') {
        const tower = new THREE.Mesh(new THREE.BoxGeometry(2.4, 5.5, 2.4), new THREE.MeshStandardMaterial({ color: 0x831843 }));
        tower.position.y = 2.75;
        g.add(tower);

        const ruler = new THREE.Mesh(new THREE.BoxGeometry(0.35, 4.2, 0.06), new THREE.MeshBasicMaterial({ color: 0x10B981 }));
        ruler.position.set(0, 2.8, 1.24);
        g.add(ruler);

      } else if (key === 'wp') {
        const archMat = new THREE.MeshStandardMaterial({ color: 0x0E7490 });
        const postL = new THREE.Mesh(new THREE.BoxGeometry(0.6, 4.0, 0.6), archMat);
        postL.position.set(-1.6, 2.0, 0);
        g.add(postL);

        const postR = new THREE.Mesh(new THREE.BoxGeometry(0.6, 4.0, 0.6), archMat);
        postR.position.set(1.6, 2.0, 0);
        g.add(postR);

        const top = new THREE.Mesh(new THREE.BoxGeometry(3.8, 0.6, 0.8), archMat);
        top.position.set(0, 4.0, 0);
        g.add(top);
      }

      return g;
    }

    createStationPlacard(key, cfg, stationGroup) {
      const canvas = document.createElement('canvas');
      canvas.width = 512;
      canvas.height = 192;
      const ctx = canvas.getContext('2d');

      const tex = new THREE.CanvasTexture(canvas);
      tex.minFilter = THREE.LinearFilter;
      const spriteMat = new THREE.SpriteMaterial({ map: tex, transparent: true, depthTest: false });
      const sprite = new THREE.Sprite(spriteMat);

      const placardY = (key === 'filin') ? 8.2 : (key === 'soroka' ? 8.8 : 4.8);
      sprite.position.set(0, placardY, 0);
      sprite.scale.set(6.8, 2.5, 1.0);
      stationGroup.add(sprite);

      stationGroup.placardCanvas = canvas;
      stationGroup.placardCtx = ctx;
      stationGroup.placardTexture = tex;

      this.redrawPlacard(stationGroup, cfg.name, cfg.taskTitle, cfg.taskSubtitle, cfg.status, cfg.colorHex);
    }

    redrawPlacard(stationGroup, title, taskTitle, taskSubtitle, statusText, colorHex) {
      const ctx = stationGroup.placardCtx;
      const canvas = stationGroup.placardCanvas;
      if (!ctx || !canvas) return;

      ctx.clearRect(0, 0, canvas.width, canvas.height);

      ctx.fillStyle = 'rgba(15, 23, 42, 0.94)';
      ctx.roundRect(8, 8, canvas.width - 16, canvas.height - 16, 20);
      ctx.fill();

      ctx.strokeStyle = '#' + colorHex.toString(16).padStart(6, '0');
      ctx.lineWidth = 5;
      ctx.stroke();

      ctx.font = 'bold 24px sans-serif';
      ctx.fillStyle = '#FFFFFF';
      ctx.textAlign = 'left';
      ctx.fillText(title, 26, 46);

      ctx.font = 'bold 22px sans-serif';
      ctx.fillStyle = '#FFC700';
      ctx.fillText(taskTitle, 26, 88);

      ctx.font = '18px sans-serif';
      ctx.fillStyle = '#E2E8F0';
      ctx.fillText(taskSubtitle, 26, 122);

      let badgeBg = '#475569';
      let badgeColor = '#FFFFFF';
      if (statusText.includes('РАБОТЕ')) {
        badgeBg = '#EA580C';
      } else if (statusText.includes('ГОТОВ') || statusText.includes('УТВЕРЖДЕНО')) {
        badgeBg = '#16A34A';
      } else if (statusText.includes('АКТИВЕН')) {
        badgeBg = '#2563EB';
      } else if (statusText.includes('ОФФЛАЙН')) {
        badgeBg = '#DC2626';
      } else if (statusText.includes('АВТОЗАМЕНА')) {
        badgeBg = '#D97706';
      }

      ctx.fillStyle = badgeBg;
      ctx.roundRect(26, 142, 240, 36, 8);
      ctx.fill();

      ctx.font = 'bold 16px sans-serif';
      ctx.fillStyle = badgeColor;
      ctx.textAlign = 'center';
      ctx.fillText(`СТАТУС: ${statusText}`, 146, 166);

      stationGroup.placardTexture.needsUpdate = true;
    }

    updateStationStatus(key, statusText, customSub) {
      const st = this.stationMeshes[key];
      const cfg = this.stationConfigs[key];
      if (!st || !cfg) return;
      cfg.status = statusText;
      if (customSub) cfg.taskSubtitle = customSub;
      this.redrawPlacard(st, cfg.name, cfg.taskTitle, cfg.taskSubtitle, cfg.status, cfg.colorHex);
    }

    // ==========================================
    // 3D АНИМИРОВАННЫЕ ПЕРСОНАЖИ С ИНСТРУМЕНТАМИ
    // ==========================================
    build3DAgentsWithTools() {
      const agentConfigs = [
        {
          id: 'bear', name: 'Медведь-Бригадир (Игрок)', role: 'Староста Артели', icon: '🐻',
          color: 0x8D5B36, x: 0, z: -1.2, speed: 1.5,
          dialogue: 'Совет Артели на связи! Управляйте мной клавишами WASD.',
          earType: 'round', toolType: 'tea_cup'
        },
        {
          id: 'soroka', name: 'Сорока-Разведчица', role: '00-research', icon: '🪶',
          color: 0x1E293B, accentColor: 0x38BDF8, x: -14, z: -17, speed: 2.1,
          dialogue: 'Парсю выдачу Яндекса и калькуляторы припоя конкурентов!',
          earType: 'beak', toolType: 'spyglass'
        },
        {
          id: 'bobr', name: 'Бобёр-Конструктор', role: '01-researcher', icon: '🦫',
          color: 0x78350F, accentColor: 0xB45309, x: -20, z: -4, speed: 1.4,
          dialogue: 'Сверяю 4 обязательных вопроса новичка по ГОСТ Р МЭК 61191!',
          earType: 'round', toolType: 'saw'
        },
        {
          id: 'kot', name: 'Кот-Паяльщик (Practico 1)', role: '02-writer', icon: '🐱',
          color: 0xEA580C, accentColor: 0xFFEDD5, x: -16, z: 5, speed: 1.6,
          dialogue: 'Паяю соединение жалом T12 при 285°C. Физика смачивания меди!',
          earType: 'pointy', toolType: 'soldering_iron'
        },
        {
          id: 'lis', name: 'Лис-Прагматик (Practico 2)', role: '02-writer-2', icon: '🦊',
          color: 0xF97316, accentColor: 0xFFFFFF, x: 16, z: 1.2, speed: 1.7,
          dialogue: 'Считаю смету: флюс за 300₽ спасет материнскую плату за 15 000₽!',
          earType: 'pointy', toolType: 'quill'
        },
        {
          id: 'barsuk', name: 'Барсук-Иллюстратор', role: '05-illustrator', icon: '🦡',
          color: 0x4B5563, accentColor: 0xFBBF24, x: -4, z: 12, speed: 1.5,
          dialogue: 'Черчу векторный SVG-термопрофиль ПОС-61 (183–190°C) по ГОСТ!',
          earType: 'stripe', toolType: 'pencil_ruler'
        },
        {
          id: 'filin', name: 'Филин-Ревизор [ОТК]', role: '03-reviewer', icon: '🦉',
          color: 0x6D28D9, accentColor: 0xEDE9FE, x: 14, z: -10, speed: 1.3,
          dialogue: 'SlopMonster заряжен: нейроштампы удалены, штамп [ОТК] готов!',
          earType: 'tuft', toolType: 'otk_stamp'
        },
        {
          id: 'dyatel', name: 'Дятел-Метролог', role: '04-scorer', icon: '🪵',
          color: 0xBE185D, accentColor: 0xFCE7F3, x: 10, z: 14, speed: 1.6,
          dialogue: 'AST-анализ: 94/100 баллов! Зеленый свет в шлюз релиза!',
          earType: 'crest', toolType: 'calibrator'
        }
      ];

      agentConfigs.forEach(cfg => {
        const meshGroup = this.create3DAnimalWithTool(cfg);
        meshGroup.position.set(cfg.x, 0, cfg.z);
        this.scene.add(meshGroup);

        const agentObj = {
          ...cfg,
          group: meshGroup,
          targetPos: new THREE.Vector3(cfg.x, 0, cfg.z),
          homePos: new THREE.Vector3(cfg.x, 0, cfg.z),
          state: 'idle', // idle, walking, working, dead
          healthStatus: 'alive',
          walkCycle: 0,
          workTimer: 0,
          heldItemMesh: null,
          statusSprite: null
        };

        this.agents.push(agentObj);
        this.agentMeshes[cfg.id] = meshGroup;
      });
    }

    setupPlayerAvatar() {
      // Игрок управляет Медведем-Бригадиром
      this.playerAgent = this.agents.find(a => a.id === 'bear');
      if (this.playerAgent) {
        // Создаем золотую корону игрока
        const crownCanvas = document.createElement('canvas');
        crownCanvas.width = 256;
        crownCanvas.height = 64;
        const cCtx = crownCanvas.getContext('2d');
        cCtx.fillStyle = 'rgba(15, 23, 42, 0.9)';
        cCtx.roundRect(4, 4, 248, 56, 12);
        cCtx.fill();
        cCtx.strokeStyle = '#FFC700';
        cCtx.lineWidth = 3;
        cCtx.stroke();
        cCtx.font = 'bold 20px sans-serif';
        cCtx.fillStyle = '#FFC700';
        cCtx.textAlign = 'center';
        cCtx.textBaseline = 'middle';
        cCtx.fillText('👑 ВЫ: Triad-Omni', 128, 32);

        const tex = new THREE.CanvasTexture(crownCanvas);
        const spriteMat = new THREE.SpriteMaterial({ map: tex, transparent: true, depthTest: false });
        const crownSprite = new THREE.Sprite(spriteMat);
        crownSprite.position.set(0, 3.2, 0);
        crownSprite.scale.set(4.0, 1.0, 1.0);
        this.playerAgent.group.add(crownSprite);
      }
    }

    create3DAnimalWithTool(cfg) {
      const g = new THREE.Group();
      g.userData = { isAgent: true, agentId: cfg.id, name: cfg.name };

      const bodyMat = new THREE.MeshStandardMaterial({ color: cfg.color, roughness: 0.75 });
      const accentMat = new THREE.MeshStandardMaterial({ color: cfg.accentColor || 0xFFFFFF });
      const eyeMat = new THREE.MeshBasicMaterial({ color: 0x111111 });

      const body = new THREE.Mesh(new THREE.BoxGeometry(1.2, 1.1, 1.4), bodyMat);
      body.position.y = 1.05;
      body.castShadow = true;
      g.add(body);

      const belly = new THREE.Mesh(new THREE.BoxGeometry(0.85, 0.8, 0.08), accentMat);
      belly.position.set(0, 0.95, 0.72);
      g.add(belly);

      const headGroup = new THREE.Group();
      headGroup.position.set(0, 1.75, 0.7);

      const head = new THREE.Mesh(new THREE.BoxGeometry(0.85, 0.8, 0.85), bodyMat);
      head.castShadow = true;
      headGroup.add(head);

      // Глазки
      [-0.24, 0.24].forEach(ex => {
        const eye = new THREE.Mesh(new THREE.SphereGeometry(0.07, 6, 6), eyeMat);
        eye.position.set(ex, 0.12, 0.44);
        headGroup.add(eye);
      });

      if (cfg.earType === 'beak' || cfg.earType === 'crest') {
        const beak = new THREE.Mesh(new THREE.ConeGeometry(0.18, 0.55, 4), new THREE.MeshStandardMaterial({ color: 0xF59E0B }));
        beak.rotateX(Math.PI / 2);
        beak.position.set(0, -0.05, 0.6);
        headGroup.add(beak);
      } else {
        const snout = new THREE.Mesh(new THREE.BoxGeometry(0.45, 0.32, 0.4), accentMat);
        snout.position.set(0, -0.12, 0.52);
        headGroup.add(snout);

        const nose = new THREE.Mesh(new THREE.BoxGeometry(0.16, 0.12, 0.08), eyeMat);
        nose.position.set(0, -0.04, 0.73);
        headGroup.add(nose);
      }

      if (cfg.earType === 'pointy') {
        [-0.3, 0.3].forEach(ux => {
          const ear = new THREE.Mesh(new THREE.ConeGeometry(0.18, 0.4, 4), bodyMat);
          ear.position.set(ux, 0.52, 0);
          headGroup.add(ear);
        });
      } else if (cfg.earType === 'round') {
        [-0.32, 0.32].forEach(ux => {
          const ear = new THREE.Mesh(new THREE.CylinderGeometry(0.18, 0.18, 0.08, 8), bodyMat);
          ear.rotateZ(Math.PI / 2);
          ear.position.set(ux, 0.48, 0);
          headGroup.add(ear);
        });
      }

      g.add(headGroup);
      g.headGroup = headGroup;

      const legMat = new THREE.MeshStandardMaterial({ color: cfg.color, roughness: 0.8 });
      const legs = {};
      [
        { key: 'legFL', x: -0.38, z: 0.4 },
        { key: 'legFR', x: 0.38,  z: 0.4 },
        { key: 'legBL', x: -0.38, z: -0.4 },
        { key: 'legBR', x: 0.38,  z: -0.4 }
      ].forEach(lp => {
        const pivot = new THREE.Group();
        pivot.position.set(lp.x, 0.55, lp.z);
        const leg = new THREE.Mesh(new THREE.BoxGeometry(0.28, 0.58, 0.28), legMat);
        leg.position.y = -0.28;
        pivot.add(leg);
        g.add(pivot);
        legs[lp.key] = pivot;
      });
      g.legs = legs;

      const toolArm = new THREE.Group();
      toolArm.position.set(0.48, 1.0, 0.4);
      this.attachWorkingTool(toolArm, cfg.toolType);
      g.add(toolArm);
      g.toolArm = toolArm;

      return g;
    }

    attachWorkingTool(armGroup, toolType) {
      if (toolType === 'soldering_iron') {
        const handle = new THREE.Mesh(new THREE.CylinderGeometry(0.06, 0.06, 0.5, 6), new THREE.MeshStandardMaterial({ color: 0x1E293B }));
        handle.rotateX(Math.PI / 3);
        armGroup.add(handle);
        const tip = new THREE.Mesh(new THREE.ConeGeometry(0.04, 0.25, 6), new THREE.MeshBasicMaterial({ color: 0xF97316 }));
        tip.rotateX(Math.PI / 3);
        tip.position.set(0, -0.3, 0.25);
        armGroup.add(tip);
      } else if (toolType === 'saw') {
        const sawBlade = new THREE.Mesh(new THREE.BoxGeometry(0.05, 0.3, 0.8), new THREE.MeshStandardMaterial({ color: 0xCBD5E1, metalness: 0.9 }));
        sawBlade.position.set(0, -0.15, 0.3);
        armGroup.add(sawBlade);
      } else if (toolType === 'pencil_ruler') {
        const pencil = new THREE.Mesh(new THREE.CylinderGeometry(0.04, 0.04, 0.5, 6), new THREE.MeshStandardMaterial({ color: 0xFBBF24 }));
        pencil.rotateX(Math.PI / 4);
        armGroup.add(pencil);
      } else if (toolType === 'otk_stamp') {
        const stamp = new THREE.Mesh(new THREE.CylinderGeometry(0.12, 0.16, 0.45, 8), new THREE.MeshStandardMaterial({ color: 0x8B5CF6 }));
        stamp.position.set(0, -0.2, 0.15);
        armGroup.add(stamp);
      } else if (toolType === 'tea_cup') {
        const cup = new THREE.Mesh(new THREE.CylinderGeometry(0.12, 0.1, 0.25, 8), new THREE.MeshStandardMaterial({ color: 0xEAB308 }));
        cup.position.set(0, -0.1, 0.2);
        armGroup.add(cup);
      }
    }

    setupWorkEffects() {
      const sparkCount = 20;
      const sparkGeo = new THREE.BufferGeometry();
      const sparkPos = new Float32Array(sparkCount * 3);
      for (let i = 0; i < sparkCount; i++) {
        sparkPos[i * 3] = -16;
        sparkPos[i * 3 + 1] = 0.9;
        sparkPos[i * 3 + 2] = 7.8;
      }
      sparkGeo.setAttribute('position', new THREE.BufferAttribute(sparkPos, 3));
      const sparkMat = new THREE.PointsMaterial({ color: 0xFFC700, size: 0.25, transparent: true, opacity: 0 });
      this.sparksGroup = new THREE.Points(sparkGeo, sparkMat);
      this.scene.add(this.sparksGroup);
    }

    setupInteraction() {
      const dom = this.renderer.domElement;

      // 1. Мышь: клик по зверю -> открытие верстака и документации
      dom.addEventListener('pointerdown', (e) => {
        const rect = dom.getBoundingClientRect();
        this.mouse.x = ((e.clientX - rect.left) / rect.width) * 2 - 1;
        this.mouse.y = -((e.clientY - rect.top) / rect.height) * 2 + 1;

        this.raycaster.setFromCamera(this.mouse, this.camera);
        const intersects = this.raycaster.intersectObjects(this.scene.children, true);

        if (intersects.length > 0) {
          let hitAgent = null;
          let groundHit = null;

          for (const hit of intersects) {
            let cur = hit.object;
            while (cur && cur !== this.scene) {
              if (cur.userData && cur.userData.isAgent) {
                hitAgent = this.agents.find(a => a.id === cur.userData.agentId);
                break;
              }
              cur = cur.parent;
            }
            if (hitAgent) break;
            if (hit.object === this.groundMesh && !groundHit) {
              groundHit = hit.point;
            }
          }

          if (hitAgent) {
            this.selectAgent(hitAgent);
          } else if (groundHit && this.playerAgent) {
            this.playerAgent.targetPos.copy(groundHit);
            this.playerAgent.targetPos.y = 0;
            this.playerAgent.state = 'walking';
          }
        }
      });

      // 2. Клавиатурное управление игроком (WASD / Стрелки / Пробел)
      window.addEventListener('keydown', (e) => {
        this.keysPressed[e.code] = true;

        if (e.code === 'Space' || e.code === 'KeyE') {
          if (this.nearestCraftsman) {
            e.preventDefault();
            if (typeof openWorkstationModal === 'function') {
              openWorkstationModal(this.nearestCraftsman.id);
            }
          }
        }
      });

      window.addEventListener('keyup', (e) => {
        this.keysPressed[e.code] = false;
      });
    }

    selectAgent(agent) {
      this.selectedAgent = agent;
      if (typeof speakText === 'function') {
        speakText(`${agent.name}: ${agent.dialogue}`);
      }

      // Открываем модальное окно верстака и документации мастера
      if (typeof openWorkstationModal === 'function') {
        openWorkstationModal(agent.id);
      }

      // Плавное приближение камеры
      if (this.controls) {
        const targetPos = agent.group.position.clone();
        const newCamPos = targetPos.clone().add(new THREE.Vector3(8, 6, 10));

        const startTime = performance.now();
        const startPos = this.camera.position.clone();
        const startTarget = this.controls.target.clone();

        const panAnim = () => {
          const now = performance.now();
          const p = Math.min((now - startTime) / 450, 1.0);
          const ease = p * (2 - p);

          this.camera.position.lerpVectors(startPos, newCamPos, ease);
          this.controls.target.lerpVectors(startTarget, targetPos, ease);
          this.controls.update();

          if (p < 1.0) requestAnimationFrame(panAnim);
        };
        panAnim();
      }
    }

    fetchInitialHealth() {
      fetch('/api/agents/routing')
        .then(r => r.json())
        .then(data => {
          if (data.status === 'ok') {
            window.clusterHealth = data.health;
            this.applyHealthStatus(data.health);
          }
        })
        .catch(() => {});
    }

    // ==========================================
    // ФИЗИКА СМЕРТИ / ОФФЛАЙНА И АВТОЗАМЕНЫ
    // ==========================================
    applyHealthStatus(healthData) {
      if (!healthData || !healthData.craftsmen) return;

      Object.entries(healthData.craftsmen).forEach(([agentId, info]) => {
        const agent = this.agents.find(a => a.id === agentId);
        const stGroup = this.stationMeshes[agentId];
        if (!agent) return;

        agent.healthStatus = info.status;

        if (info.status === 'dead') {
          // ДРАМАТИЧНАЯ СМЕРТЬ: зверь падает на бок!
          agent.state = 'dead';
          agent.group.rotation.z = Math.PI / 2.2;
          agent.group.position.y = 0.35;
          if (agent.group.toolArm) agent.group.toolArm.rotation.set(0.6, 0.4, 0.4);

          this.createOrUpdateStatusSprite(agent, '💀 ОФФЛАЙН (Сбой узла)', '#EF4444');

          if (stGroup) {
            this.redrawPlacard(stGroup, stGroup.userData.name, '💀 СБОЙ УЗЛА: ' + info.primary, 'Требуется автозамена в YAML!', 'ОФФЛАЙН 💀', 0xEF4444);
          }

        } else if (info.status === 'fallback') {
          // В СТРОЮ БЛАГОДАРЯ АВТОЗАМЕНЕ
          if (agent.state === 'dead') agent.state = 'idle';
          agent.group.rotation.z = 0;
          agent.group.position.y = 0;

          this.createOrUpdateStatusSprite(agent, `🔄 РЕЗЕРВ: ${info.active_route}`, '#F59E0B');

          if (stGroup) {
            this.redrawPlacard(stGroup, stGroup.userData.name, stGroup.userData.name, 'Резервный узел: ' + info.active_route, 'АВТОЗАМЕНА 🔄', 0xF59E0B);
          }

        } else {
          // В ШТАТНОМ ОНЛАЙН РЕЖИМЕ
          if (agent.state === 'dead') agent.state = 'idle';
          agent.group.rotation.z = 0;
          agent.group.position.y = 0;
          this.removeStatusSprite(agent);

          if (stGroup) {
            const cfg = this.stationConfigs[agentId];
            if (cfg) {
              this.redrawPlacard(stGroup, cfg.name, cfg.taskTitle, cfg.taskSubtitle, cfg.status || 'ГОТОВ', cfg.colorHex);
            }
          }
        }
      });
    }

    createOrUpdateStatusSprite(agent, text, colorHex) {
      if (agent.statusSprite) {
        agent.group.remove(agent.statusSprite);
        agent.statusSprite = null;
      }

      const canvas = document.createElement('canvas');
      canvas.width = 300;
      canvas.height = 70;
      const ctx = canvas.getContext('2d');

      ctx.fillStyle = 'rgba(15, 23, 42, 0.92)';
      ctx.roundRect(4, 4, 292, 62, 12);
      ctx.fill();

      ctx.strokeStyle = colorHex;
      ctx.lineWidth = 3;
      ctx.stroke();

      ctx.font = 'bold 20px sans-serif';
      ctx.fillStyle = colorHex;
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillText(text, 150, 35);

      const tex = new THREE.CanvasTexture(canvas);
      const mat = new THREE.SpriteMaterial({ map: tex, transparent: true, depthTest: false });
      const sprite = new THREE.Sprite(mat);
      sprite.position.set(0, 3.4, 0);
      sprite.scale.set(4.5, 1.1, 1.0);
      agent.group.add(sprite);
      agent.statusSprite = sprite;
    }

    removeStatusSprite(agent) {
      if (agent.statusSprite) {
        agent.group.remove(agent.statusSprite);
        agent.statusSprite = null;
      }
    }

    // ==========================================
    // ВИЗУАЛИЗАЦИЯ ПЕРЕДАЧИ ИЗ ЛАП В ЛАПЫ (HANDOVER)
    // ==========================================
    createHandoverItem(itemType) {
      const g = new THREE.Group();
      if (itemType.includes('LSI')) {
        const scroll = new THREE.Mesh(new THREE.CylinderGeometry(0.12, 0.12, 0.7, 8), new THREE.MeshStandardMaterial({ color: 0xFDE68A }));
        scroll.rotateZ(Math.PI / 2);
        g.add(scroll);
      } else if (itemType.includes('ГОСТ')) {
        const folder = new THREE.Mesh(new THREE.BoxGeometry(0.6, 0.08, 0.45), new THREE.MeshStandardMaterial({ color: 0x92400E }));
        g.add(folder);
      } else if (itemType.includes('Черновик')) {
        const draft = new THREE.Mesh(new THREE.BoxGeometry(0.55, 0.1, 0.42), new THREE.MeshStandardMaterial({ color: 0xEA580C }));
        g.add(draft);
      } else if (itemType.includes('SVG')) {
        const tablet = new THREE.Mesh(new THREE.BoxGeometry(0.65, 0.04, 0.5), new THREE.MeshStandardMaterial({ color: 0x1E293B }));
        g.add(tablet);
      } else if (itemType.includes('Паспорт')) {
        const pass = new THREE.Mesh(new THREE.BoxGeometry(0.5, 0.08, 0.4), new THREE.MeshStandardMaterial({ color: 0x8B5CF6 }));
        g.add(pass);
      } else {
        const box = new THREE.Mesh(new THREE.BoxGeometry(0.5, 0.5, 0.5), new THREE.MeshStandardMaterial({ color: 0x06B6D4 }));
        g.add(box);
      }

      const glow = new THREE.Mesh(new THREE.SphereGeometry(0.45, 8, 8), new THREE.MeshBasicMaterial({ color: 0xFFC700, transparent: true, opacity: 0.35 }));
      g.add(glow);
      return g;
    }

    drawConveyorFlowLine(pA, pB) {
      const lineGeo = new THREE.BufferGeometry().setFromPoints([
        new THREE.Vector3(pA.x, 0.08, pA.z),
        new THREE.Vector3(pB.x, 0.08, pB.z)
      ]);
      const lineMat = new THREE.LineDashedMaterial({ color: 0xFFC700, dashSize: 1.2, gapSize: 0.6, linewidth: 3 });
      const line = new THREE.Line(lineGeo, lineMat);
      line.computeLineDistances();
      this.scene.add(line);
      this.flowLines.push(line);
    }

    clearFlowLines() {
      this.flowLines.forEach(l => this.scene.remove(l));
      this.flowLines = [];
    }

    startMissionPipeline(topicId = 1) {
      const pipelineSteps = [
        { from: 'soroka', to: 'bobr', itemName: '📜 LSI-ядро #' + topicId, sendSpeech: 'Бобёр, держи LSI-ядро темы #' + topicId, receiveSpeech: 'Принял LSI! Утверждаю бриф ГОСТ Р МЭК 61191.' },
        { from: 'bobr', to: 'kot', itemName: '🪵 Бриф ГОСТ #' + topicId, sendSpeech: 'Кот, плотина стандартов готова! Пиши триаду!', receiveSpeech: 'Принял бриф! Прогреваю жало T12, паяю физику смачивания.' },
        { from: 'kot', to: 'barsuk', itemName: '📝 Черновик #' + topicId, sendSpeech: 'Барсук, текст готов! Нужен точный SVG-термопрофиль ПОС-61.', receiveSpeech: 'Принял черновик! Черчу кривую ликвидуса 183–190°C.' },
        { from: 'barsuk', to: 'filin', itemName: '📐 SVG-схема #' + topicId, sendSpeech: 'Филин, векторная схема готова! Проверь на слоп.', receiveSpeech: 'Принял схему! SlopMonster чист, ставлю штамп [ОТК-2026]!' },
        { from: 'filin', to: 'dyatel', itemName: '🔍 Паспорт v1.1 #' + topicId, sendSpeech: 'Дятел, штамп ОТК стоит. Запускай метрологический скоринг!', receiveSpeech: 'Принял паспорт! AST-скоринг 94/100 баллов! Передаю в релиз.' },
        { from: 'dyatel', to: 'wp', itemName: '📦 Релиз 94/100', sendSpeech: 'Шлюз WordPress, пакет статьи доставлен для tochka-plavleniya.ru!', receiveSpeech: 'Релиз успешно развернут на сайте!' }
      ];

      let currentStepIdx = 0;
      this.clearFlowLines();

      const executeStep = () => {
        if (currentStepIdx >= pipelineSteps.length) {
          if (typeof speakText === 'function') {
            speakText('Сквозной цикл Артели успешно завершен! Все верстаки сдали работу.');
          }
          this.updateStationStatus('wp', 'ОПУБЛИКОВАНО ✅', 'Статья активна на tochka-plavleniya.ru');
          return;
        }

        const step = pipelineSteps[currentStepIdx];
        const sender = this.agents.find(a => a.id === step.from);
        const receiver = this.agents.find(a => a.id === step.to);
        const senderSt = this.stationConfigs[step.from];
        const receiverSt = this.stationConfigs[step.to];

        if (!sender || !receiverSt) return;

        this.updateStationStatus(step.from, 'В РАБОТЕ ⚙️', 'Формирование: ' + step.itemName);
        sender.state = 'working';
        sender.workTimer = 180;

        this.drawConveyorFlowLine(senderSt.pos, receiverSt.pos);

        setTimeout(() => {
          sender.state = 'walking';
          const itemMesh = this.createHandoverItem(step.itemName);
          sender.group.add(itemMesh);
          itemMesh.position.set(0, 1.2, 0.6);
          sender.heldItemMesh = itemMesh;

          const meetPoint = new THREE.Vector3().lerpVectors(senderSt.pos, receiverSt.pos, 0.65);
          sender.targetPos.copy(meetPoint);

          if (receiver) {
            receiver.targetPos.copy(meetPoint.clone().add(new THREE.Vector3(1.2, 0, 1.2)));
            receiver.state = 'walking';
          }

          if (typeof speakText === 'function') {
            speakText(`${sender.name}: ${step.sendSpeech}`);
          }

          const arrivalCheck = setInterval(() => {
            const dist = sender.group.position.distanceTo(meetPoint);
            if (dist < 1.0) {
              clearInterval(arrivalCheck);

              this.animateItemTransfer(sender, receiver, itemMesh, () => {
                this.updateStationStatus(step.from, 'УТВЕРЖДЕНО ✅', 'Передано: ' + step.itemName);
                sender.state = 'walking';
                sender.targetPos.copy(sender.homePos);

                if (receiver) {
                  this.updateStationStatus(step.to, 'В РАБОТЕ ⚙️', 'Обработка: ' + step.itemName);
                  receiver.state = 'working';
                  receiver.workTimer = 220;

                  if (typeof speakText === 'function') {
                    speakText(`${receiver.name}: ${step.receiveSpeech}`);
                  }
                }

                currentStepIdx++;
                setTimeout(executeStep, 2600);
              });
            }
          }, 200);

        }, 1500);
      };

      executeStep();
    }

    animateItemTransfer(sender, receiver, itemMesh, onComplete) {
      if (!receiver) {
        if (sender.heldItemMesh) sender.group.remove(sender.heldItemMesh);
        if (onComplete) onComplete();
        return;
      }

      sender.group.remove(itemMesh);
      this.scene.add(itemMesh);

      const startWorld = sender.group.position.clone().add(new THREE.Vector3(0, 1.2, 0.5));
      const endWorld = receiver.group.position.clone().add(new THREE.Vector3(0, 1.2, 0.5));
      itemMesh.position.copy(startWorld);

      const duration = 800;
      const startTime = performance.now();

      const transferLoop = () => {
        const now = performance.now();
        const p = Math.min((now - startTime) / duration, 1.0);

        itemMesh.position.lerpVectors(startWorld, endWorld, p);
        itemMesh.position.y += Math.sin(p * Math.PI) * 1.5;
        itemMesh.rotation.y += 0.08;

        if (p < 1.0) {
          requestAnimationFrame(transferLoop);
        } else {
          this.scene.remove(itemMesh);
          receiver.group.add(itemMesh);
          itemMesh.position.set(0, 1.2, 0.6);
          receiver.heldItemMesh = itemMesh;

          receiver.targetPos.copy(receiver.homePos);
          receiver.state = 'working';

          if (receiver.group.headGroup) {
            receiver.group.headGroup.rotation.x = 0.4;
            setTimeout(() => {
              if (receiver.group.headGroup) receiver.group.headGroup.rotation.x = 0;
            }, 400);
          }

          setTimeout(() => {
            if (receiver.heldItemMesh) {
              receiver.group.remove(receiver.heldItemMesh);
              receiver.heldItemMesh = null;
            }
          }, 3000);

          if (onComplete) onComplete();
        }
      };

      transferLoop();
    }

    // ==========================================
    // ГЛАВНЫЙ ЦИКЛ ОБНОВЛЕНИЯ И АНИМАЦИИ
    // ==========================================
    update() {
      if (this.paused) return;
      const delta = this.clock.getDelta();
      const time = this.clock.getElapsedTime();

      // 1. Мельница вращается
      this.windmills.forEach(w => {
        w.rotation.z += 0.015 * this.speed;
      });

      // 2. Управление Игроком (Медведем-Бригадиром) с клавиатуры (WASD)
      if (this.playerAgent && !this.paused && this.playerAgent.state !== 'dead') {
        const moveVec = new THREE.Vector3(0, 0, 0);
        if (this.keysPressed['KeyW'] || this.keysPressed['ArrowUp']) moveVec.z -= 1;
        if (this.keysPressed['KeyS'] || this.keysPressed['ArrowDown']) moveVec.z += 1;
        if (this.keysPressed['KeyA'] || this.keysPressed['ArrowLeft']) moveVec.x -= 1;
        if (this.keysPressed['KeyD'] || this.keysPressed['ArrowRight']) moveVec.x += 1;

        if (moveVec.lengthSq() > 0) {
          moveVec.normalize();
          // Поворот относительно камеры
          const camAngle = Math.atan2(this.camera.position.x - this.controls.target.x, this.camera.position.z - this.controls.target.z);
          moveVec.applyAxisAngle(new THREE.Vector3(0, 1, 0), camAngle);

          const stepSpeed = this.playerAgent.speed * delta * 5.2 * this.speed;
          this.playerAgent.group.position.addScaledVector(moveVec, stepSpeed);

          const targetAngle = Math.atan2(moveVec.x, moveVec.z);
          this.playerAgent.group.rotation.y = THREE.MathUtils.lerp(this.playerAgent.group.rotation.y, targetAngle, 0.22);

          this.playerAgent.walkCycle += delta * 14.0 * this.speed;
          const legSwing = Math.sin(this.playerAgent.walkCycle) * 0.55;
          if (this.playerAgent.group.legs) {
            this.playerAgent.group.legs.legFL.rotation.x = legSwing;
            this.playerAgent.group.legs.legBR.rotation.x = legSwing;
            this.playerAgent.group.legs.legFR.rotation.x = -legSwing;
            this.playerAgent.group.legs.legBL.rotation.x = -legSwing;
          }
          this.playerAgent.group.position.y = Math.abs(Math.sin(this.playerAgent.walkCycle)) * 0.14;

          // Камера плавно следует за персонажем игрока
          if (this.cameraFollow && this.controls) {
            const camTarget = this.playerAgent.group.position.clone().add(new THREE.Vector3(0, 1.2, 0));
            this.controls.target.lerp(camTarget, 0.08);
          }
        } else if (this.playerAgent.state === 'idle') {
          if (this.playerAgent.group.legs) {
            this.playerAgent.group.legs.legFL.rotation.x = 0;
            this.playerAgent.group.legs.legBR.rotation.x = 0;
            this.playerAgent.group.legs.legFR.rotation.x = 0;
            this.playerAgent.group.legs.legBL.rotation.x = 0;
          }
          this.playerAgent.group.position.y = 0;
        }

        // Проверка близости игрока к другим зверям
        let nearest = null;
        let minDist = 3.8;
        this.agents.forEach(a => {
          if (a.id !== 'bear') {
            const d = this.playerAgent.group.position.distanceTo(a.group.position);
            if (d < minDist) {
              minDist = d;
              nearest = a;
            }
          }
        });
        this.nearestCraftsman = nearest;
        const promptEl = document.getElementById('playerInteractionPrompt');
        if (promptEl) {
          if (nearest) {
            promptEl.style.display = 'block';
            promptEl.innerText = `💬 ВЫ РЯДОМ С ${nearest.name.toUpperCase()}! НАЖМИТЕ ПРОБЕЛ ИЛИ КЛИКНИТЕ: ВЕРСТАК И ДОКУМЕНТАЦИЯ`;
          } else {
            promptEl.style.display = 'none';
          }
        }
      }

      // 3. Анимация остальных персонажей
      this.agents.forEach(a => {
        if (a.state === 'dead') return; // Мертвые звери лежат неподвижно

        // Игрок уже обновлен выше, для остальных — стандартная логика
        if (a.id === 'bear' && (this.keysPressed['KeyW'] || this.keysPressed['KeyA'] || this.keysPressed['KeyS'] || this.keysPressed['KeyD'])) return;

        const dist = a.group.position.distanceTo(a.targetPos);

        if (dist > 0.35 && a.state === 'walking') {
          const dir = a.targetPos.clone().sub(a.group.position).normalize();
          const targetAngle = Math.atan2(dir.x, dir.z);
          a.group.rotation.y = THREE.MathUtils.lerp(a.group.rotation.y, targetAngle, 0.2);

          const step = Math.min(a.speed * delta * 2.8 * this.speed, dist);
          a.group.position.addScaledVector(dir, step);

          a.walkCycle += delta * 12.0 * this.speed;
          const legSwing = Math.sin(a.walkCycle) * 0.55;
          if (a.group.legs) {
            a.group.legs.legFL.rotation.x = legSwing;
            a.group.legs.legBR.rotation.x = legSwing;
            a.group.legs.legFR.rotation.x = -legSwing;
            a.group.legs.legBL.rotation.x = -legSwing;
          }
          a.group.position.y = Math.abs(Math.sin(a.walkCycle)) * 0.14;

        } else if (a.state === 'working') {
          a.group.position.y = 0;
          if (a.group.legs) {
            a.group.legs.legFL.rotation.x = 0;
            a.group.legs.legBR.rotation.x = 0;
            a.group.legs.legFR.rotation.x = 0;
            a.group.legs.legBL.rotation.x = 0;
          }

          if (a.id === 'kot') {
            if (a.group.toolArm) {
              a.group.toolArm.rotation.x = Math.sin(time * 8.0) * 0.35;
              a.group.toolArm.rotation.z = Math.cos(time * 6.0) * 0.2;
            }
            if (a.group.headGroup) a.group.headGroup.rotation.x = 0.25;
            if (this.sparksGroup) this.sparksGroup.material.opacity = (Math.sin(time * 16.0) > 0.2) ? 0.9 : 0;
            if (this.forgeLight) this.forgeLight.intensity = 2.0 + Math.sin(time * 12.0) * 0.8;

          } else if (a.id === 'bobr') {
            if (a.group.toolArm) {
              a.group.toolArm.position.z = 0.4 + Math.sin(time * 7.0) * 0.3;
              a.group.toolArm.rotation.x = 0.2;
            }
            if (a.group.headGroup) a.group.headGroup.rotation.x = 0.15;

          } else if (a.id === 'soroka') {
            if (a.group.headGroup) a.group.headGroup.rotation.x = Math.abs(Math.sin(time * 8.0)) * 0.4;

          } else if (a.id === 'lis') {
            if (a.group.toolArm) a.group.toolArm.rotation.z = Math.sin(time * 9.0) * 0.3;
            if (a.group.headGroup) a.group.headGroup.rotation.y = Math.sin(time * 4.0) * 0.2;

          } else if (a.id === 'barsuk') {
            if (a.group.toolArm) a.group.toolArm.position.y = 1.0 + Math.sin(time * 5.0) * 0.25;

          } else if (a.id === 'filin') {
            if (a.group.toolArm) a.group.toolArm.position.y = 1.0 - Math.abs(Math.sin(time * 3.5)) * 0.4;

          } else if (a.id === 'dyatel') {
            if (a.group.headGroup) a.group.headGroup.rotation.x = Math.abs(Math.sin(time * 12.0)) * 0.4;

          } else if (a.id === 'bear') {
            if (a.group.toolArm) a.group.toolArm.position.y = 1.0 + Math.sin(time * 2.0) * 0.2;
          }

        } else {
          a.state = 'idle';
          a.group.position.y = 0;
          if (a.group.legs) {
            a.group.legs.legFL.rotation.x = 0;
            a.group.legs.legBR.rotation.x = 0;
            a.group.legs.legFR.rotation.x = 0;
            a.group.legs.legBL.rotation.x = 0;
          }
          if (a.group.toolArm) {
            a.group.toolArm.rotation.set(0, 0, 0);
            a.group.toolArm.position.set(0.48, 1.0, 0.4);
          }
          if (a.group.headGroup) a.group.headGroup.rotation.set(0, 0, 0);
          if (a.id === 'kot' && this.sparksGroup) this.sparksGroup.material.opacity = 0;
        }
      });
    }

    animate() {
      requestAnimationFrame(() => this.animate());
      this.update();
      if (this.controls) this.controls.update();
      this.renderer.render(this.scene, this.camera);
    }

    onWindowResize() {
      if (!this.container || !this.renderer || !this.camera) return;
      const width = this.container.clientWidth || 1000;
      const height = this.container.clientHeight || 560;
      this.camera.aspect = width / height;
      this.camera.updateProjectionMatrix();
      this.renderer.setSize(width, height);
    }

    resize() {
      this.onWindowResize();
    }

    togglePause() {
      this.paused = !this.paused;
      return this.paused;
    }

    setSpeed(s) {
      this.speed = s;
    }

    resetCamera() {
      if (!this.controls || !this.camera) return;
      this.camera.position.set(28, 24, 36);
      this.controls.target.set(0, 1.2, 0);
      this.controls.update();
    }

    setTimeOfDay(timeMode) {
      this.timeOfDay = timeMode;
      if (timeMode === 'sunset') {
        this.scene.background = new THREE.Color(0xFB923C);
        this.scene.fog.color = new THREE.Color(0xFDBA74);
        this.dirLight.color = new THREE.Color(0xF97316);
        this.dirLight.intensity = 1.35;
      } else if (timeMode === 'night') {
        this.scene.background = new THREE.Color(0x0F172A);
        this.scene.fog.color = new THREE.Color(0x0F172A);
        this.dirLight.color = new THREE.Color(0x38BDF8);
        this.dirLight.intensity = 0.35;
      } else {
        this.scene.background = new THREE.Color(0x93C5FD);
        this.scene.fog.color = new THREE.Color(0x93C5FD);
        this.dirLight.color = new THREE.Color(0xFFF7E6);
        this.dirLight.intensity = 1.3;
      }
    }
  }

  window.Farm3DEngine = Farm3DEngine;

  document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('farm3DContainer');
    if (container && typeof THREE !== 'undefined') {
      window.farm3D = new Farm3DEngine('farm3DContainer');
      window.aiTown = window.farm3D;
    }
  });
})();
