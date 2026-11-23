/**
 * 🎮 3D ИНЖЕНЕРНАЯ ФЕРМА-ГОРОДОК АРТЕЛИ ЗВЕРЕЙ-МАСТЕРОВ (Three.js WebGL 3D Engine)
 * Полная интерактивная 3D-симуляция фермы: поля, пашня, ручей, деревья, мастерские и анимированные 3D-звери.
 * Вдохновлено a16z-infra/ai-town, Stanford generative_agents и стандартами MuJoCo 3D.
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
      this.timeOfDay = 'day'; // day, sunset, night

      this.agents = [];
      this.agentMeshes = {};
      this.stationMeshes = {};
      this.trees = [];
      this.windmills = [];
      this.smokeParticles = null;
      this.pollenParticles = null;

      this.raycaster = new THREE.Raycaster();
      this.mouse = new THREE.Vector2();

      this.init();
    }

    init() {
      // 1. Сцена и туман
      this.scene = new THREE.Scene();
      this.scene.background = new THREE.Color(0x87CEEB);
      this.scene.fog = new THREE.FogExp2(0x87CEEB, 0.012);

      // 2. Камера
      const width = this.container.clientWidth || 1000;
      const height = this.container.clientHeight || 560;
      this.camera = new THREE.PerspectiveCamera(42, width / height, 0.5, 300);
      this.camera.position.set(38, 32, 48);

      // 3. WebGL Рендерер
      this.renderer = new THREE.WebGLRenderer({ antialias: true, alpha: false, powerPreference: 'high-performance' });
      this.renderer.setSize(width, height);
      this.renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
      this.renderer.shadowMap.enabled = true;
      this.renderer.shadowMap.type = THREE.PCFSoftShadowMap;
      this.renderer.toneMapping = THREE.ACESFilmicToneMapping;
      this.renderer.toneMappingExposure = 1.05;
      this.container.appendChild(this.renderer.domElement);

      // 4. OrbitControls
      if (typeof THREE.OrbitControls !== 'undefined') {
        this.controls = new THREE.OrbitControls(this.camera, this.renderer.domElement);
        this.controls.enableDamping = true;
        this.controls.dampingFactor = 0.06;
        this.controls.maxPolarAngle = Math.PI / 2.08; // Не даем нырять под землю
        this.controls.minDistance = 12;
        this.controls.maxDistance = 120;
        this.controls.target.set(0, 2, 0);
      }

      // 5. Освещение
      this.setupLighting();

      // 6. Мир Фермы (Поле, Пашня, Ручей, Деревья, Заборы)
      this.buildFarmEnvironment();

      // 7. Постройки и Верстаки Мастеров
      this.buildCraftsmenStations();

      // 8. 3D Анимированные Персонажи
      this.build3DAgents();

      // 9. Системы частиц (Дым пайки и пыльца полей)
      this.buildParticleSystems();

      // 10. Обработчики мыши и событий
      this.setupInteraction();

      // 11. Анимационный цикл
      window.addEventListener('resize', () => this.onWindowResize());
      this.animate();
    }

    setupLighting() {
      // Мягкий полусферический свет неба и травы
      this.hemiLight = new THREE.HemisphereLight(0xC6E2FF, 0x3E5E2A, 0.65);
      this.scene.add(this.hemiLight);

      // Теплый солнечный свет
      this.dirLight = new THREE.DirectionalLight(0xFFF4D4, 1.25);
      this.dirLight.position.set(35, 45, 25);
      this.dirLight.castShadow = true;
      this.dirLight.shadow.mapSize.width = 2048;
      this.dirLight.shadow.mapSize.height = 2048;
      this.dirLight.shadow.camera.near = 1;
      this.dirLight.shadow.camera.far = 140;
      const d = 45;
      this.dirLight.shadow.camera.left = -d;
      this.dirLight.shadow.camera.right = d;
      this.dirLight.shadow.camera.top = d;
      this.dirLight.shadow.camera.bottom = -d;
      this.dirLight.shadow.bias = -0.0005;
      this.scene.add(this.dirLight);

      // Фоновый теплый эмбиент
      this.ambientLight = new THREE.AmbientLight(0xFAF3E0, 0.45);
      this.scene.add(this.ambientLight);

      // Точечные огоньки у верстаков
      this.forgePointLight = new THREE.PointLight(0xFF6A00, 2.0, 16);
      this.forgePointLight.position.set(-18, 3.5, 8);
      this.scene.add(this.forgePointLight);

      this.owlLight = new THREE.PointLight(0x8B5CF6, 1.5, 20);
      this.owlLight.position.set(16, 11, -12);
      this.scene.add(this.owlLight);
    }

    buildFarmEnvironment() {
      // --- А. Основной зеленый ландшафт фермы (Луга и Холмы) ---
      const groundGeo = new THREE.PlaneGeometry(110, 110, 64, 64);
      // Придаем рельефу мягкую естественную холмистость
      const pos = groundGeo.attributes.position;
      for (let i = 0; i < pos.count; i++) {
        const vx = pos.getX(i);
        const vy = pos.getY(i);
        // Не поднимаем центр, где стоят здания и дорожки
        const distFromCenter = Math.sqrt(vx * vx + vy * vy);
        if (distFromCenter > 20) {
          const elevation = (Math.sin(vx * 0.12) * Math.cos(vy * 0.12) * 1.6) +
                            (Math.sin(vx * 0.05 + 1.2) * 1.2);
          pos.setZ(i, elevation);
        }
      }
      groundGeo.computeVertexNormals();

      const grassMat = new THREE.MeshStandardMaterial({
        color: 0x4D8C47,
        roughness: 0.85,
        metalness: 0.05,
        flatShading: true
      });
      const ground = new THREE.Mesh(groundGeo, grassMat);
      ground.rotation.x = -Math.PI / 2;
      ground.receiveShadow = true;
      this.scene.add(ground);
      this.groundMesh = ground;

      // --- Б. Пашня / Вспаханное поле (Farmland Soil with Furrows) ---
      // Располагаем поле у лесопилки Бобра
      const fieldW = 28;
      const fieldD = 18;
      const soilGeo = new THREE.BoxGeometry(fieldW, 0.3, fieldD);
      const soilMat = new THREE.MeshStandardMaterial({
        color: 0x4A2E1B, // Богатый чернозём
        roughness: 0.95,
        flatShading: true
      });
      const field = new THREE.Mesh(soilGeo, soilMat);
      field.position.set(-20, 0.15, -16);
      field.receiveShadow = true;
      this.scene.add(field);

      // Борозды пашни (3D Furrows)
      const furrowMat = new THREE.MeshStandardMaterial({ color: 0x382012, roughness: 1.0 });
      const cropMat = new THREE.MeshStandardMaterial({ color: 0x88D44A, roughness: 0.5 });
      for (let z = -fieldD / 2 + 1.5; z < fieldD / 2 - 1; z += 2.4) {
        // Гребень земли
        const ridgeGeo = new THREE.BoxGeometry(fieldW - 2, 0.22, 1.2);
        const ridge = new THREE.Mesh(ridgeGeo, furrowMat);
        ridge.position.set(-20, 0.36, -16 + z);
        ridge.receiveShadow = true;
        this.scene.add(ridge);

        // Зеленые ростки культур вдоль борозды
        for (let x = -fieldW / 2 + 2; x < fieldW / 2 - 2; x += 2.2) {
          const sproutGeo = new THREE.ConeGeometry(0.22, 0.5, 4);
          const sprout = new THREE.Mesh(sproutGeo, cropMat);
          sprout.position.set(-20 + x, 0.55, -16 + z);
          sprout.rotation.y = Math.random() * Math.PI;
          this.scene.add(sprout);
        }
      }

      // Ограждение пашни деревянными колышками
      this.buildFence(-34, -16, 28, 18);

      // --- В. Извилистая речка и пруд с кувшинками ---
      const riverCurve = new THREE.CatmullRomCurve3([
        new THREE.Vector3(-45, 0.05, 30),
        new THREE.Vector3(-25, 0.05, 22),
        new THREE.Vector3(-8, 0.05, 12),
        new THREE.Vector3(6, 0.05, -5),
        new THREE.Vector3(18, 0.05, -28),
        new THREE.Vector3(35, 0.05, -45)
      ]);
      const riverGeo = new THREE.TubeGeometry(riverCurve, 40, 2.4, 8, false);
      const waterMat = new THREE.MeshStandardMaterial({
        color: 0x2299DD,
        roughness: 0.15,
        metalness: 0.3,
        transparent: true,
        opacity: 0.88
      });
      const river = new THREE.Mesh(riverGeo, waterMat);
      river.scale.set(1, 0.12, 1);
      this.scene.add(river);

      // Пруд у лесопилки Бобра
      const pondGeo = new THREE.CylinderGeometry(5.5, 5.5, 0.15, 16);
      const pond = new THREE.Mesh(pondGeo, waterMat);
      pond.position.set(-5, 0.08, 14);
      this.scene.add(pond);

      // Кувшинки на пруду
      const lilyMat = new THREE.MeshStandardMaterial({ color: 0x2E7D32, roughness: 0.6 });
      for (let i = 0; i < 6; i++) {
        const lilyGeo = new THREE.CylinderGeometry(0.45, 0.45, 0.02, 8);
        const lily = new THREE.Mesh(lilyGeo, lilyMat);
        const ang = Math.random() * Math.PI * 2;
        const rad = 1.2 + Math.random() * 3.2;
        lily.position.set(-5 + Math.cos(ang) * rad, 0.16, 14 + Math.sin(ang) * rad);
        this.scene.add(lily);
      }

      // Деревянный мостик через речку
      this.buildBridge(-8, 0.35, 12, 0.4);

      // --- Г. Грунтовые дорожки и мощеные тропы ---
      this.buildPaths();

      // --- Д. Деревья (Березы, Яблони, Дубы, Ели) ---
      this.populateForestAndOrchard();

      // --- Е. Тюки сена вокруг фермы ---
      this.placeHayBales();
    }

    buildFence(cx, cz, w, d) {
      const fenceMat = new THREE.MeshStandardMaterial({ color: 0x8D5B36, roughness: 0.9 });
      const postGeo = new THREE.CylinderGeometry(0.12, 0.14, 1.4, 6);
      const railGeoX = new THREE.BoxGeometry(w, 0.08, 0.08);
      const railGeoZ = new THREE.BoxGeometry(0.08, 0.08, d);

      // Верхние и нижние перекладины
      const topRail1 = new THREE.Mesh(railGeoX, fenceMat);
      topRail1.position.set(cx + w / 2, 0.8, cz - d / 2);
      this.scene.add(topRail1);
      const topRail2 = new THREE.Mesh(railGeoX, fenceMat);
      topRail2.position.set(cx + w / 2, 0.8, cz + d / 2);
      this.scene.add(topRail2);

      const sideRail1 = new THREE.Mesh(railGeoZ, fenceMat);
      sideRail1.position.set(cx, 0.8, cz);
      this.scene.add(sideRail1);

      // Столбики
      for (let x = cx; x <= cx + w; x += 3.5) {
        const post = new THREE.Mesh(postGeo, fenceMat);
        post.position.set(x, 0.7, cz - d / 2);
        post.castShadow = true;
        this.scene.add(post);

        const post2 = new THREE.Mesh(postGeo, fenceMat);
        post2.position.set(x, 0.7, cz + d / 2);
        post2.castShadow = true;
        this.scene.add(post2);
      }
    }

    buildBridge(x, y, z, rotY) {
      const bridgeGroup = new THREE.Group();
      const woodMat = new THREE.MeshStandardMaterial({ color: 0x784D2B, roughness: 0.85 });

      // Настил
      const deckGeo = new THREE.BoxGeometry(3.6, 0.2, 5.2);
      const deck = new THREE.Mesh(deckGeo, woodMat);
      deck.castShadow = true;
      deck.receiveShadow = true;
      bridgeGroup.add(deck);

      // Перила
      const postGeo = new THREE.CylinderGeometry(0.08, 0.08, 1.1, 6);
      const railGeo = new THREE.BoxGeometry(0.1, 0.1, 5.2);

      const r1 = new THREE.Mesh(railGeo, woodMat);
      r1.position.set(-1.7, 0.9, 0);
      bridgeGroup.add(r1);

      const r2 = new THREE.Mesh(railGeo, woodMat);
      r2.position.set(1.7, 0.9, 0);
      bridgeGroup.add(r2);

      [-2, 0, 2].forEach(pz => {
        const pL = new THREE.Mesh(postGeo, woodMat);
        pL.position.set(-1.7, 0.5, pz);
        bridgeGroup.add(pL);

        const pR = new THREE.Mesh(postGeo, woodMat);
        pR.position.set(1.7, 0.5, pz);
        bridgeGroup.add(pR);
      });

      bridgeGroup.position.set(x, y, z);
      bridgeGroup.rotation.y = rotY;
      this.scene.add(bridgeGroup);
    }

    buildPaths() {
      // Песчано-гравийные дорожки, связывающие верстаки
      const pathMat = new THREE.MeshStandardMaterial({
        color: 0xC8B282,
        roughness: 0.95,
        flatShading: true
      });

      const paths = [
        // Центральная площадь
        { geo: new THREE.CylinderGeometry(8.5, 8.5, 0.05, 16), x: 0, y: 0.03, z: 0 },
        // Дорога на запад (Кузница Кота)
        { geo: new THREE.BoxGeometry(18, 0.05, 3.2), x: -10, y: 0.03, z: 6 },
        // Дорога на северо-запад (Лесопилка Бобра)
        { geo: new THREE.BoxGeometry(18, 0.05, 3.2), x: -14, y: 0.03, z: -10, rotY: -0.4 },
        // Дорога на восток (Усадьба Лиса)
        { geo: new THREE.BoxGeometry(20, 0.05, 3.2), x: 12, y: 0.03, z: 4, rotY: 0.2 },
        // Дорога на север (Мельница Сороки)
        { geo: new THREE.BoxGeometry(3.2, 0.05, 18), x: -4, y: 0.03, z: -16 },
        // Дорога на северо-восток (Башня Филина)
        { geo: new THREE.BoxGeometry(16, 0.05, 3.2), x: 10, y: 0.03, z: -10, rotY: -0.6 },
        // Дорога на юг (Кульман Барсука и Элеватор Дятла)
        { geo: new THREE.BoxGeometry(3.2, 0.05, 22), x: 2, y: 0.03, z: 12 }
      ];

      paths.forEach(p => {
        const mesh = new THREE.Mesh(p.geo, pathMat);
        mesh.position.set(p.x, p.y, p.z);
        if (p.rotY) mesh.rotation.y = p.rotY;
        mesh.receiveShadow = true;
        this.scene.add(mesh);
      });
    }

    populateForestAndOrchard() {
      // Генерация реалистичных стилизованных 3D деревьев
      const treeCoords = [
        // Лесная опушка около пашни и лесопилки (Бобёр)
        { x: -32, z: -28, type: 'pine', scale: 1.4 },
        { x: -36, z: -20, type: 'pine', scale: 1.2 },
        { x: -38, z: -8,  type: 'oak',  scale: 1.3 },
        { x: -28, z: -32, type: 'pine', scale: 1.1 },

        // Яблоневый сад у усадьбы Лиса
        { x: 22, z: 14, type: 'apple', scale: 1.1 },
        { x: 28, z: 12, type: 'apple', scale: 1.25 },
        { x: 26, z: 20, type: 'apple', scale: 1.15 },
        { x: 34, z: 16, type: 'apple', scale: 1.0 },

        // Березы около пруда и реки
        { x: -14, z: 18, type: 'birch', scale: 1.2 },
        { x: -2,  z: 22, type: 'birch', scale: 1.35 },
        { x: 10,  z: 18, type: 'birch', scale: 1.15 },

        // Деревья у башни Филина
        { x: 26, z: -18, type: 'oak',  scale: 1.4 },
        { x: 22, z: -26, type: 'pine', scale: 1.3 },

        // Одинокие тенистые дубы у беседки Бригадира
        { x: -8, z: -4, type: 'oak', scale: 1.2 },
        { x: 8,  z: -6, type: 'oak', scale: 1.15 }
      ];

      treeCoords.forEach((t, idx) => {
        const tree = this.createStylizedTree(t.type, t.scale);
        tree.position.set(t.x, 0, t.z);
        this.scene.add(tree);
        this.trees.push({ mesh: tree, origY: tree.position.y, seed: idx * 1.3 });
      });
    }

    createStylizedTree(type, scale = 1.0) {
      const treeGroup = new THREE.Group();

      if (type === 'pine') {
        // Хвойная ель
        const trunkGeo = new THREE.CylinderGeometry(0.35, 0.55, 3.5, 6);
        const trunkMat = new THREE.MeshStandardMaterial({ color: 0x4E3524, roughness: 0.9 });
        const trunk = new THREE.Mesh(trunkGeo, trunkMat);
        trunk.position.y = 1.75;
        trunk.castShadow = true;
        treeGroup.add(trunk);

        const pineMat = new THREE.MeshStandardMaterial({ color: 0x27592D, roughness: 0.8, flatShading: true });
        const tiers = [
          { r: 2.6, h: 2.8, y: 3.2 },
          { r: 2.1, h: 2.5, y: 4.8 },
          { r: 1.5, h: 2.2, y: 6.2 },
          { r: 0.9, h: 1.8, y: 7.3 }
        ];
        tiers.forEach(tr => {
          const cGeo = new THREE.ConeGeometry(tr.r, tr.h, 7);
          const cone = new THREE.Mesh(cGeo, pineMat);
          cone.position.y = tr.y;
          cone.castShadow = true;
          treeGroup.add(cone);
        });
      } else if (type === 'birch') {
        // Береза с белым стволом
        const trunkGeo = new THREE.CylinderGeometry(0.28, 0.42, 5.0, 7);
        const birchMat = new THREE.MeshStandardMaterial({ color: 0xEDEDED, roughness: 0.8 });
        const trunk = new THREE.Mesh(trunkGeo, birchMat);
        trunk.position.y = 2.5;
        trunk.castShadow = true;
        treeGroup.add(trunk);

        // Черные крапинки на стволе березы
        const spotMat = new THREE.MeshBasicMaterial({ color: 0x222222 });
        for (let i = 0; i < 6; i++) {
          const spotGeo = new THREE.BoxGeometry(0.35, 0.12, 0.35);
          const spot = new THREE.Mesh(spotGeo, spotMat);
          spot.position.set(0, 1.2 + i * 0.6, 0);
          spot.rotation.y = i * 1.1;
          treeGroup.add(spot);
        }

        // Воздушная крона
        const foliageMat = new THREE.MeshStandardMaterial({ color: 0x65A644, roughness: 0.7, flatShading: true });
        const spheres = [
          { r: 1.8, y: 5.2, x: 0, z: 0 },
          { r: 1.4, y: 6.3, x: 0.4, z: -0.3 },
          { r: 1.2, y: 5.6, x: -0.6, z: 0.4 }
        ];
        spheres.forEach(s => {
          const fGeo = new THREE.DodecahedronGeometry(s.r, 1);
          const fMesh = new THREE.Mesh(fGeo, foliageMat);
          fMesh.position.set(s.x, s.y, s.z);
          fMesh.castShadow = true;
          treeGroup.add(fMesh);
        });
      } else if (type === 'apple') {
        // Яблоня в цвету / с плодами
        const trunkGeo = new THREE.CylinderGeometry(0.32, 0.5, 2.8, 6);
        const trunkMat = new THREE.MeshStandardMaterial({ color: 0x5C381E, roughness: 0.9 });
        const trunk = new THREE.Mesh(trunkGeo, trunkMat);
        trunk.position.y = 1.4;
        trunk.castShadow = true;
        treeGroup.add(trunk);

        const foliageMat = new THREE.MeshStandardMaterial({ color: 0x559E38, roughness: 0.75, flatShading: true });
        const fGeo = new THREE.DodecahedronGeometry(2.3, 1);
        const fMesh = new THREE.Mesh(fGeo, foliageMat);
        fMesh.position.y = 3.6;
        fMesh.castShadow = true;
        treeGroup.add(fMesh);

        // Спелые красные яблоки
        const appleMat = new THREE.MeshStandardMaterial({ color: 0xD32F2F, roughness: 0.4 });
        for (let i = 0; i < 7; i++) {
          const aGeo = new THREE.SphereGeometry(0.18, 6, 6);
          const apple = new THREE.Mesh(aGeo, appleMat);
          const ang = (i / 7) * Math.PI * 2;
          apple.position.set(Math.cos(ang) * 1.8, 3.4 + (i % 3) * 0.4, Math.sin(ang) * 1.8);
          treeGroup.add(apple);
        }
      } else {
        // Могучий раскидистый Дуб
        const trunkGeo = new THREE.CylinderGeometry(0.6, 0.9, 3.6, 8);
        const trunkMat = new THREE.MeshStandardMaterial({ color: 0x482C17, roughness: 0.9 });
        const trunk = new THREE.Mesh(trunkGeo, trunkMat);
        trunk.position.y = 1.8;
        trunk.castShadow = true;
        treeGroup.add(trunk);

        const foliageMat = new THREE.MeshStandardMaterial({ color: 0x3E7D32, roughness: 0.8, flatShading: true });
        const clusters = [
          { r: 2.6, x: 0, y: 4.2, z: 0 },
          { r: 1.9, x: -1.3, y: 4.0, z: 0.8 },
          { r: 1.8, x: 1.2, y: 4.3, z: -0.7 },
          { r: 1.6, x: 0.4, y: 5.4, z: 0.5 }
        ];
        clusters.forEach(c => {
          const cGeo = new THREE.DodecahedronGeometry(c.r, 1);
          const cMesh = new THREE.Mesh(cGeo, foliageMat);
          cMesh.position.set(c.x, c.y, c.z);
          cMesh.castShadow = true;
          treeGroup.add(cMesh);
        });
      }

      treeGroup.scale.set(scale, scale, scale);
      return treeGroup;
    }

    placeHayBales() {
      const hayMat = new THREE.MeshStandardMaterial({ color: 0xDEB843, roughness: 0.9, flatShading: true });
      const baleGeo = new THREE.CylinderGeometry(1.0, 1.0, 1.6, 12);

      const baleCoords = [
        { x: -14, z: -6, rZ: Math.PI / 2, rY: 0.4 },
        { x: -13, z: -4.5, rZ: Math.PI / 2, rY: 0.3 },
        { x: -13.5, z: -5.2, y: 1.6, rZ: Math.PI / 2, rY: 0.35 },
        { x: 18, z: 8, rZ: Math.PI / 2, rY: -0.5 },
        { x: 19.5, z: 9, rZ: Math.PI / 2, rY: -0.4 }
      ];

      baleCoords.forEach(b => {
        const bale = new THREE.Mesh(baleGeo, hayMat);
        bale.position.set(b.x, b.y || 0.8, b.z);
        if (b.rZ) bale.rotation.z = b.rZ;
        if (b.rY) bale.rotation.y = b.rY;
        bale.castShadow = true;
        bale.receiveShadow = true;
        this.scene.add(bale);
      });
    }

    buildCraftsmenStations() {
      // 1. Ветряная Мельница Сороки (00-research / Бюро Разведки)
      this.stationMeshes['soroka'] = this.buildWindmillStation(-16, 0, -22);

      // 2. Лесопилка Бобра (01-researcher / Конструкторское Бюро)
      this.stationMeshes['bobr'] = this.buildSawmillStation(-22, 0, -6);

      // 3. Паяльная Кузница Кота (02-writer / Practico 1)
      this.stationMeshes['kot'] = this.buildForgeStation(-18, 0, 8);

      // 4. Беседка Бригадира Медведя (Командный Пункт Артели)
      this.stationMeshes['bear'] = this.buildGazeboStation(0, 0, 0);

      // 5. Усадьба Лиса (02-writer-2 / Practico 2)
      this.stationMeshes['lis'] = this.buildFoxManorStation(18, 0, 4);

      // 6. Шатер-Кульман Барсука (05-illustrator / Векторные Схемы)
      this.stationMeshes['barsuk'] = this.buildDraftingTentStation(-4, 0, 16);

      // 7. Дозорная Башня-Маяк Филина (03-reviewer / ОТК & SlopMonster)
      this.stationMeshes['filin'] = this.buildWatchtowerStation(16, 0, -14);

      // 8. Метрологический Элеватор Дятла (04-scorer / Метрология 100 баллов)
      this.stationMeshes['dyatel'] = this.buildElevatorStation(12, 0, 18);

      // 9. Релизный Шлюз WordPress (Портал tochka-plavleniya.ru)
      this.stationMeshes['wp'] = this.buildReleasePortalStation(28, 0, -4);
    }

    buildWindmillStation(x, y, z) {
      const g = new THREE.Group();
      g.userData = { id: 'ws_soroka', name: 'Бюро Разведки (Сорока)', icon: '🪶' };

      // Каменное основание мельницы
      const baseGeo = new THREE.CylinderGeometry(2.4, 3.2, 7.5, 8);
      const baseMat = new THREE.MeshStandardMaterial({ color: 0x9E9E9E, roughness: 0.9, flatShading: true });
      const base = new THREE.Mesh(baseGeo, baseMat);
      base.position.y = 3.75;
      base.castShadow = true;
      base.receiveShadow = true;
      g.add(base);

      // Деревянная крыша конусом
      const roofGeo = new THREE.ConeGeometry(2.8, 3.0, 8);
      const roofMat = new THREE.MeshStandardMaterial({ color: 0x8D5B36, roughness: 0.8 });
      const roof = new THREE.Mesh(roofGeo, roofMat);
      roof.position.y = 9.0;
      roof.castShadow = true;
      g.add(roof);

      // Вращающиеся лопасти мельницы
      const bladeCenter = new THREE.Group();
      bladeCenter.position.set(0, 7.8, 2.2);

      const hubGeo = new THREE.CylinderGeometry(0.4, 0.4, 0.6, 8);
      hubGeo.rotateX(Math.PI / 2);
      const hubMat = new THREE.MeshStandardMaterial({ color: 0x333333 });
      const hub = new THREE.Mesh(hubGeo, hubMat);
      bladeCenter.add(hub);

      const bladeWood = new THREE.MeshStandardMaterial({ color: 0xE8D7B8, roughness: 0.7 });
      for (let i = 0; i < 4; i++) {
        const bladeGeo = new THREE.BoxGeometry(0.7, 5.2, 0.08);
        const blade = new THREE.Mesh(bladeGeo, bladeWood);
        blade.position.y = 2.8;
        blade.castShadow = true;

        const bladeArm = new THREE.Group();
        bladeArm.rotation.z = (i * Math.PI) / 2;
        bladeArm.add(blade);
        bladeCenter.add(bladeArm);
      }
      g.add(bladeCenter);
      this.windmills.push(bladeCenter);

      // Вывеска
      this.addStationSign(g, '🪶 Бюро Разведки', 0x38BDF8, 0, 11.2, 0);

      g.position.set(x, y, z);
      this.scene.add(g);
      return g;
    }

    buildSawmillStation(x, y, z) {
      const g = new THREE.Group();
      g.userData = { id: 'ws_bobr', name: 'Конструкторское Бюро (Бобёр)', icon: '🦫' };

      // Бревенчатый навес
      const roofGeo = new THREE.BoxGeometry(6.5, 0.3, 5.5);
      const roofMat = new THREE.MeshStandardMaterial({ color: 0x6E4A2A, roughness: 0.85 });
      const roof = new THREE.Mesh(roofGeo, roofMat);
      roof.position.set(0, 3.6, 0);
      roof.rotation.z = -0.08;
      roof.castShadow = true;
      g.add(roof);

      // Опорные бревна
      const postGeo = new THREE.CylinderGeometry(0.18, 0.22, 3.6, 6);
      const logMat = new THREE.MeshStandardMaterial({ color: 0x5C3B1E, roughness: 0.9 });
      [[-2.8, -2.2], [2.8, -2.2], [-2.8, 2.2], [2.8, 2.2]].forEach(([px, pz]) => {
        const post = new THREE.Mesh(postGeo, logMat);
        post.position.set(px, 1.8, pz);
        post.castShadow = true;
        g.add(post);
      });

      // Штабель бревен у лесопилки
      for (let row = 0; row < 3; row++) {
        for (let col = 0; col < 4 - row; col++) {
          const timberGeo = new THREE.CylinderGeometry(0.32, 0.32, 4.2, 8);
          timberGeo.rotateZ(Math.PI / 2);
          const timber = new THREE.Mesh(timberGeo, logMat);
          timber.position.set(-2.5 + col * 0.65 + row * 0.32, 0.35 + row * 0.55, 3.2);
          timber.castShadow = true;
          g.add(timber);
        }
      }

      // Верстак Бобра с чертежами ГОСТ
      const benchGeo = new THREE.BoxGeometry(2.6, 1.1, 1.4);
      const benchMat = new THREE.MeshStandardMaterial({ color: 0x8C5E35 });
      const bench = new THREE.Mesh(benchGeo, benchMat);
      bench.position.set(0, 0.55, 0);
      bench.castShadow = true;
      g.add(bench);

      this.addStationSign(g, '🦫 ГОСТ-Бюро', 0xB45309, 0, 4.6, 0);

      g.position.set(x, y, z);
      this.scene.add(g);
      return g;
    }

    buildForgeStation(x, y, z) {
      const g = new THREE.Group();
      g.userData = { id: 'ws_kot', name: 'Паяльный Цех (Кот-Паяльщик)', icon: '🐱' };

      // Каменная мастерская
      const wallMat = new THREE.MeshStandardMaterial({ color: 0x616161, roughness: 0.95, flatShading: true });
      const wallsGeo = new THREE.BoxGeometry(5.8, 3.2, 5.0);
      const walls = new THREE.Mesh(wallsGeo, wallMat);
      walls.position.set(0, 1.6, 0);
      walls.castShadow = true;
      walls.receiveShadow = true;
      g.add(walls);

      // Двускатная черепичная крыша
      const roofGeo = new THREE.ConeGeometry(4.4, 1.8, 4);
      roofGeo.rotateY(Math.PI / 4);
      const roofMat = new THREE.MeshStandardMaterial({ color: 0x9E3D22, roughness: 0.8 });
      const roof = new THREE.Mesh(roofGeo, roofMat);
      roof.position.set(0, 4.1, 0);
      roof.scale.set(1.1, 1, 0.9);
      roof.castShadow = true;
      g.add(roof);

      // Кирпичная труба
      const chimGeo = new THREE.BoxGeometry(0.8, 3.4, 0.8);
      const chimMat = new THREE.MeshStandardMaterial({ color: 0x7E311B, roughness: 0.9 });
      const chim = new THREE.Mesh(chimGeo, chimMat);
      chim.position.set(1.8, 4.2, 1.2);
      chim.castShadow = true;
      g.add(chim);
      this.forgeChimneyPos = new THREE.Vector3(x + 1.8, 5.9, z + 1.2);

      // Светящаяся ванна припоя / тигель с раскаленным сплавом (ПОС-61)
      const potGeo = new THREE.CylinderGeometry(0.7, 0.5, 0.7, 12);
      const potMat = new THREE.MeshStandardMaterial({ color: 0x222222 });
      const pot = new THREE.Mesh(potGeo, potMat);
      pot.position.set(-1.4, 0.65, 2.7);
      g.add(pot);

      const moltenGeo = new THREE.CylinderGeometry(0.62, 0.62, 0.1, 12);
      const moltenMat = new THREE.MeshBasicMaterial({ color: 0xFF9900 });
      const molten = new THREE.Mesh(moltenGeo, moltenMat);
      molten.position.set(-1.4, 0.98, 2.7);
      g.add(molten);

      // Наковальня
      const anvilGeo = new THREE.BoxGeometry(0.9, 0.8, 1.4);
      const anvilMat = new THREE.MeshStandardMaterial({ color: 0x1F2937, metalness: 0.8, roughness: 0.3 });
      const anvil = new THREE.Mesh(anvilGeo, anvilMat);
      anvil.position.set(1.4, 0.4, 2.7);
      anvil.castShadow = true;
      g.add(anvil);

      this.addStationSign(g, '🐱 Паяльный Цех', 0xEA580C, 0, 5.4, 0);

      g.position.set(x, y, z);
      this.scene.add(g);
      return g;
    }

    buildGazeboStation(x, y, z) {
      const g = new THREE.Group();
      g.userData = { id: 'ws_bear', name: 'Круглый Стол Артели (Медведь-Бригадир)', icon: '🐻' };

      // Деревянный круглый помост
      const deckGeo = new THREE.CylinderGeometry(4.8, 5.0, 0.35, 12);
      const deckMat = new THREE.MeshStandardMaterial({ color: 0x7A502E, roughness: 0.9 });
      const deck = new THREE.Mesh(deckGeo, deckMat);
      deck.position.y = 0.18;
      deck.receiveShadow = true;
      g.add(deck);

      // Столбики беседки
      const postMat = new THREE.MeshStandardMaterial({ color: 0x5C381E, roughness: 0.85 });
      for (let i = 0; i < 6; i++) {
        const ang = (i / 6) * Math.PI * 2;
        const postGeo = new THREE.CylinderGeometry(0.14, 0.16, 3.2, 6);
        const post = new THREE.Mesh(postGeo, postMat);
        post.position.set(Math.cos(ang) * 4.2, 1.6, Math.sin(ang) * 4.2);
        post.castShadow = true;
        g.add(post);
      }

      // Коническая крыша
      const roofGeo = new THREE.ConeGeometry(5.4, 2.2, 8);
      const roofMat = new THREE.MeshStandardMaterial({ color: 0x3E5E2A, roughness: 0.8 });
      const roof = new THREE.Mesh(roofGeo, roofMat);
      roof.position.y = 4.2;
      roof.castShadow = true;
      g.add(roof);

      // Большой круглый дубовый стол
      const tableGeo = new THREE.CylinderGeometry(2.0, 2.0, 0.25, 12);
      const table = new THREE.Mesh(tableGeo, deckMat);
      table.position.y = 1.0;
      table.castShadow = true;
      g.add(table);

      // Самовар на столе
      const samovarGeo = new THREE.CylinderGeometry(0.3, 0.25, 0.8, 8);
      const samovarMat = new THREE.MeshStandardMaterial({ color: 0xEAB308, metalness: 0.9, roughness: 0.2 });
      const samovar = new THREE.Mesh(samovarGeo, samovarMat);
      samovar.position.set(0, 1.5, 0);
      samovar.castShadow = true;
      g.add(samovar);

      this.addStationSign(g, '🐻 Совет Артели', 0xEAB308, 0, 5.8, 0);

      g.position.set(x, y, z);
      this.scene.add(g);
      return g;
    }

    buildFoxManorStation(x, y, z) {
      const g = new THREE.Group();
      g.userData = { id: 'ws_lis', name: 'Сметное Бюро (Лис-Прагматик)', icon: '🦊' };

      // Уютный деревянный дом
      const houseGeo = new THREE.BoxGeometry(5.2, 3.2, 4.6);
      const woodMat = new THREE.MeshStandardMaterial({ color: 0x8C5831, roughness: 0.85 });
      const house = new THREE.Mesh(houseGeo, woodMat);
      house.position.set(0, 1.6, 0);
      house.castShadow = true;
      house.receiveShadow = true;
      g.add(house);

      // Крыша
      const roofGeo = new THREE.ConeGeometry(4.2, 1.8, 4);
      roofGeo.rotateY(Math.PI / 4);
      const roofMat = new THREE.MeshStandardMaterial({ color: 0x4A6B32, roughness: 0.8 });
      const roof = new THREE.Mesh(roofGeo, roofMat);
      roof.position.set(0, 4.0, 0);
      roof.scale.set(1.05, 1, 0.9);
      roof.castShadow = true;
      g.add(roof);

      // Ящики со сметами и монетами на крыльце
      const boxMat = new THREE.MeshStandardMaterial({ color: 0x5C3B1E });
      for (let i = 0; i < 3; i++) {
        const boxGeo = new THREE.BoxGeometry(0.8, 0.6, 0.8);
        const box = new THREE.Mesh(boxGeo, boxMat);
        box.position.set(2.0 + i * 0.5, 0.3, 2.6);
        box.castShadow = true;
        g.add(box);
      }

      this.addStationSign(g, '🦊 Сметное Бюро', 0xF59E0B, 0, 5.2, 0);

      g.position.set(x, y, z);
      this.scene.add(g);
      return g;
    }

    buildDraftingTentStation(x, y, z) {
      const g = new THREE.Group();
      g.userData = { id: 'ws_barsuk', name: 'Кульман Иллюстраций (Барсук)', icon: '🦡' };

      // Полосатый навес-шатер
      const tentGeo = new THREE.ConeGeometry(3.6, 2.0, 4);
      tentGeo.rotateY(Math.PI / 4);
      const tentMat = new THREE.MeshStandardMaterial({ color: 0xFDE047, roughness: 0.7 });
      const tent = new THREE.Mesh(tentGeo, tentMat);
      tent.position.y = 2.8;
      tent.castShadow = true;
      g.add(tent);

      // Колышки навеса
      const legMat = new THREE.MeshStandardMaterial({ color: 0x555555 });
      [[-2.2, -2.2], [2.2, -2.2], [-2.2, 2.2], [2.2, 2.2]].forEach(([px, pz]) => {
        const leg = new THREE.Mesh(new THREE.CylinderGeometry(0.06, 0.06, 2.8, 6), legMat);
        leg.position.set(px, 1.4, pz);
        g.add(leg);
      });

      // Наклонный чертежный стол (Кульман)
      const boardGeo = new THREE.BoxGeometry(2.0, 0.1, 1.4);
      const boardMat = new THREE.MeshStandardMaterial({ color: 0xFFFFFF, roughness: 0.4 });
      const board = new THREE.Mesh(boardGeo, boardMat);
      board.position.set(0, 1.4, 0);
      board.rotation.x = -0.35;
      board.castShadow = true;
      g.add(board);

      this.addStationSign(g, '🦡 Кульман SVG', 0xFBBF24, 0, 4.2, 0);

      g.position.set(x, y, z);
      this.scene.add(g);
      return g;
    }

    buildWatchtowerStation(x, y, z) {
      const g = new THREE.Group();
      g.userData = { id: 'ws_filin', name: 'Стенд ОТК & Ревизор (Филин)', icon: '🦉' };

      // Высокая бревенчатая сторожевая вышка (12 метров)
      const postMat = new THREE.MeshStandardMaterial({ color: 0x4A2E1B, roughness: 0.9 });
      [[-1.8, -1.8], [1.8, -1.8], [-1.8, 1.8], [1.8, 1.8]].forEach(([px, pz]) => {
        const post = new THREE.Mesh(new THREE.CylinderGeometry(0.18, 0.28, 9.5, 6), postMat);
        post.position.set(px * 0.8, 4.75, pz * 0.8);
        post.rotation.z = -px * 0.03;
        post.rotation.x = pz * 0.03;
        post.castShadow = true;
        g.add(post);
      });

      // Смотровая площадка на высоте 9.5 метров
      const platformGeo = new THREE.BoxGeometry(4.2, 0.3, 4.2);
      const platMat = new THREE.MeshStandardMaterial({ color: 0x5C3B1E });
      const platform = new THREE.Mesh(platformGeo, platMat);
      platform.position.y = 9.5;
      platform.castShadow = true;
      g.add(platform);

      // Перила площадки
      const railMat = new THREE.MeshStandardMaterial({ color: 0x332211 });
      const railGeo = new THREE.BoxGeometry(4.2, 0.8, 0.1);
      const r1 = new THREE.Mesh(railGeo, railMat);
      r1.position.set(0, 10.0, 2.0);
      g.add(r1);

      // Крыша вышки
      const roofGeo = new THREE.ConeGeometry(3.2, 1.8, 4);
      roofGeo.rotateY(Math.PI / 4);
      const roofMat = new THREE.MeshStandardMaterial({ color: 0x4C1D95, roughness: 0.7 });
      const roof = new THREE.Mesh(roofGeo, roofMat);
      roof.position.y = 11.5;
      roof.castShadow = true;
      g.add(roof);

      // Прожектор ОТК
      const spotGeo = new THREE.CylinderGeometry(0.3, 0.4, 0.6, 8);
      spotGeo.rotateX(Math.PI / 2);
      const spotMat = new THREE.MeshStandardMaterial({ color: 0xA78BFA, emissive: 0x8B5CF6, emissiveIntensity: 0.8 });
      const spotlight = new THREE.Mesh(spotGeo, spotMat);
      spotlight.position.set(0, 10.2, 1.8);
      g.add(spotlight);

      this.addStationSign(g, '🦉 Пост ОТК', 0x8B5CF6, 0, 12.8, 0);

      g.position.set(x, y, z);
      this.scene.add(g);
      return g;
    }

    buildElevatorStation(x, y, z) {
      const g = new THREE.Group();
      g.userData = { id: 'ws_dyatel', name: 'Метрологический Стенд (Дятел)', icon: '🪵' };

      // Зерновой амбар / башня метрологии
      const towerGeo = new THREE.BoxGeometry(3.6, 6.8, 3.6);
      const towerMat = new THREE.MeshStandardMaterial({ color: 0x9333EA, roughness: 0.85, flatShading: true });
      const tower = new THREE.Mesh(towerGeo, towerMat);
      tower.position.y = 3.4;
      tower.castShadow = true;
      tower.receiveShadow = true;
      g.add(tower);

      // Крыша
      const roofGeo = new THREE.ConeGeometry(2.8, 2.0, 4);
      roofGeo.rotateY(Math.PI / 4);
      const roofMat = new THREE.MeshStandardMaterial({ color: 0x3B0764 });
      const roof = new THREE.Mesh(roofGeo, roofMat);
      roof.position.y = 7.7;
      roof.castShadow = true;
      g.add(roof);

      // Мерная шкала 100 баллов на фасаде
      const scaleGeo = new THREE.BoxGeometry(0.4, 4.8, 0.08);
      const scaleMat = new THREE.MeshBasicMaterial({ color: 0x10B981 });
      const scaleBar = new THREE.Mesh(scaleGeo, scaleMat);
      scaleBar.position.set(0, 3.4, 1.84);
      g.add(scaleBar);

      this.addStationSign(g, '🪵 Метрология 100', 0xEC4899, 0, 9.2, 0);

      g.position.set(x, y, z);
      this.scene.add(g);
      return g;
    }

    buildReleasePortalStation(x, y, z) {
      const g = new THREE.Group();
      g.userData = { id: 'ws_wp', name: 'Шлюз Релиза (WordPress)', icon: '🚀' };

      // Деревянная триумфальная арка ворот фермы
      const archMat = new THREE.MeshStandardMaterial({ color: 0x1E3A8A, roughness: 0.7 });
      const p1 = new THREE.Mesh(new THREE.BoxGeometry(0.8, 4.5, 0.8), archMat);
      p1.position.set(-1.8, 2.25, 0);
      p1.castShadow = true;
      g.add(p1);

      const p2 = new THREE.Mesh(new THREE.BoxGeometry(0.8, 4.5, 0.8), archMat);
      p2.position.set(1.8, 2.25, 0);
      p2.castShadow = true;
      g.add(p2);

      const beam = new THREE.Mesh(new THREE.BoxGeometry(4.8, 0.8, 1.0), archMat);
      beam.position.set(0, 4.5, 0);
      beam.castShadow = true;
      g.add(beam);

      // Светящийся портал релиза
      const portalGeo = new THREE.PlaneGeometry(2.6, 3.8);
      const portalMat = new THREE.MeshBasicMaterial({
        color: 0x06B6D4,
        transparent: true,
        opacity: 0.65,
        side: THREE.DoubleSide
      });
      const portal = new THREE.Mesh(portalGeo, portalMat);
      portal.position.set(0, 2.0, 0);
      g.add(portal);

      this.addStationSign(g, '🚀 В РЕЛИЗ: tochka-plavleniya.ru', 0x06B6D4, 0, 5.5, 0);

      g.position.set(x, y, z);
      this.scene.add(g);
      return g;
    }

    addStationSign(group, text, colorHex, x, y, z) {
      // 3D Canvas Billboard с названием станции
      const canvas = document.createElement('canvas');
      canvas.width = 256;
      canvas.height = 64;
      const ctx = canvas.getContext('2d');
      ctx.fillStyle = 'rgba(15, 23, 42, 0.85)';
      ctx.roundRect(4, 4, 248, 56, 12);
      ctx.fill();
      ctx.strokeStyle = '#' + colorHex.toString(16).padStart(6, '0');
      ctx.lineWidth = 3;
      ctx.stroke();

      ctx.font = 'bold 22px sans-serif';
      ctx.fillStyle = '#FFFFFF';
      ctx.textAlign = 'center';
      ctx.textBaseline = 'middle';
      ctx.fillText(text, 128, 32);

      const tex = new THREE.CanvasTexture(canvas);
      tex.minFilter = THREE.LinearFilter;
      const spriteMat = new THREE.SpriteMaterial({ map: tex, transparent: true });
      const sprite = new THREE.Sprite(spriteMat);
      sprite.position.set(x, y, z);
      sprite.scale.set(4.0, 1.0, 1.0);
      group.add(sprite);
    }

    build3DAgents() {
      // Конфигурация персонажей Артели
      const agentConfigs = [
        {
          id: 'bear', name: 'Медведь-Бригадир', role: 'Староста Артели', icon: '🐻',
          color: 0x8D5B36, x: 0, z: -1.2, speed: 1.4, thoughts: 'Слежу за полевыми работами и балансом тепла кластера',
          dialogue: 'Добро пожаловать на 3D Ферму Артели! Здесь рождается чистый инженерный контент.',
          earType: 'round', snout: 0.45, tail: 'short'
        },
        {
          id: 'soroka', name: 'Сорока-Разведчица', role: '00-research', icon: '🪶',
          color: 0x1E293B, accentColor: 0x38BDF8, x: -16, z: -18, speed: 2.1, thoughts: 'Парсю поисковую выдачу и калькуляторы конкурентов',
          dialogue: 'Собрала 15 LSI-хвостов и нашла 3 формулы расчета припоя!',
          earType: 'beak', snout: 0.6, tail: 'feather'
        },
        {
          id: 'bobr', name: 'Бобёр-Конструктор', role: '01-researcher', icon: '🦫',
          color: 0x78350F, accentColor: 0xB45309, x: -22, z: -2, speed: 1.4, thoughts: 'Проверяю плотину ГОСТ Р МЭК 61191 и FAQ',
          dialogue: 'Бриф утвержден: 4 обязательных вопроса новичка встроены в ТЗ.',
          earType: 'round', snout: 0.5, tail: 'paddle'
        },
        {
          id: 'kot', name: 'Кот-Паяльщик (Practico 1)', role: '02-writer', icon: '🐱',
          color: 0xEA580C, accentColor: 0xFFEDD5, x: -18, z: 5, speed: 1.6, thoughts: 'Триада: факт -> физика шва -> последствие перегрева',
          dialogue: 'Жало T12 прогрето до 285°C. Физика смачивания меди 1.2 сек.',
          earType: 'pointy', snout: 0.35, tail: 'long'
        },
        {
          id: 'lis', name: 'Лис-Прагматик (Practico 2)', role: '02-writer-2', icon: '🦊',
          color: 0xF97316, accentColor: 0xFFFFFF, x: 18, z: 2, speed: 1.7, thoughts: 'Считаю смету домашней мастерской: экономия 14 000₽',
          dialogue: 'Флюс за 300₽ окупается при первой же спасенной плате!',
          earType: 'pointy', snout: 0.6, tail: 'bushy'
        },
        {
          id: 'barsuk', name: 'Барсук-Иллюстратор', role: '05-illustrator', icon: '🦡',
          color: 0x4B5563, accentColor: 0xFBBF24, x: -4, z: 13, speed: 1.5, thoughts: 'Черчу векторный SVG-термопрофиль ПОС-61 (183–190°C)',
          dialogue: 'Векторная графика встроена в паспорт статьи. 0 токенов расхода!',
          earType: 'stripe', snout: 0.45, tail: 'short'
        },
        {
          id: 'filin', name: 'Филин-Ревизор [ОТК]', role: '03-reviewer', icon: '🦉',
          color: 0x6D28D9, accentColor: 0xEDE9FE, x: 16, z: -10, speed: 1.3, thoughts: 'Детектор нейроштампов SlopMonster включен на 100%',
          dialogue: 'Штамп [ОТК-2026] одобрен: водность текста 0%, температура ликвидуса точна.',
          earType: 'tuft', snout: 0.4, tail: 'wings'
        },
        {
          id: 'dyatel', name: 'Дятел-Метролог', role: '04-scorer', icon: '🪵',
          color: 0xBE185D, accentColor: 0xFCE7F3, x: 12, z: 14, speed: 1.6, thoughts: 'AST-анализ: 6 строгих блоков скоринга на 100 баллов',
          dialogue: 'Итоговый балл статьи: 94/100. Зеленый свет в WordPress!',
          earType: 'crest', snout: 0.7, tail: 'stiff'
        }
      ];

      agentConfigs.forEach(cfg => {
        const meshGroup = this.create3DAnimalMesh(cfg);
        meshGroup.position.set(cfg.x, 0, cfg.z);
        this.scene.add(meshGroup);

        const agentObj = {
          ...cfg,
          group: meshGroup,
          targetPos: new THREE.Vector3(cfg.x, 0, cfg.z),
          homePos: new THREE.Vector3(cfg.x, 0, cfg.z),
          state: 'idle', // idle, walking, working
          walkCycle: 0,
          bubbleSprite: null,
          bubbleTimer: 240,
          holdingItem: null
        };

        // Создаем речевое 3D-облачко над головой
        this.updateAgentSpeechBubble(agentObj, cfg.dialogue);

        this.agents.push(agentObj);
        this.agentMeshes[cfg.id] = meshGroup;
      });
    }

    create3DAnimalMesh(cfg) {
      const g = new THREE.Group();
      g.userData = { isAgent: true, agentId: cfg.id, name: cfg.name };

      const bodyMat = new THREE.MeshStandardMaterial({ color: cfg.color, roughness: 0.7, flatShading: true });
      const accentMat = new THREE.MeshStandardMaterial({ color: cfg.accentColor || 0xFFFFFF, roughness: 0.6 });
      const eyeMat = new THREE.MeshBasicMaterial({ color: 0x111111 });

      // 1. Тело (Округло-кубический торс)
      const bodyGeo = new THREE.BoxGeometry(1.2, 1.1, 1.5);
      const body = new THREE.Mesh(bodyGeo, bodyMat);
      body.position.y = 1.1;
      body.castShadow = true;
      body.receiveShadow = true;
      g.add(body);

      // Грудка / Брюшко акцентного цвета
      const bellyGeo = new THREE.BoxGeometry(0.9, 0.8, 0.1);
      const belly = new THREE.Mesh(bellyGeo, accentMat);
      belly.position.set(0, 1.0, 0.76);
      g.add(belly);

      // 2. Голова
      const headGroup = new THREE.Group();
      headGroup.position.set(0, 1.8, 0.75);

      const headGeo = new THREE.BoxGeometry(0.9, 0.85, 0.9);
      const head = new THREE.Mesh(headGeo, bodyMat);
      head.castShadow = true;
      headGroup.add(head);

      // Мордочка / Клюв
      if (cfg.earType === 'beak' || cfg.earType === 'crest') {
        // Клюв птицы
        const beakGeo = new THREE.ConeGeometry(0.22, cfg.snout, 4);
        beakGeo.rotateX(Math.PI / 2);
        const beakMat = new THREE.MeshStandardMaterial({ color: 0xF59E0B, roughness: 0.5 });
        const beak = new THREE.Mesh(beakGeo, beakMat);
        beak.position.set(0, -0.05, 0.65);
        headGroup.add(beak);
      } else {
        // Звериная мордочка
        const snoutGeo = new THREE.BoxGeometry(0.5, 0.38, cfg.snout);
        const snout = new THREE.Mesh(snoutGeo, accentMat);
        snout.position.set(0, -0.15, 0.55);
        headGroup.add(snout);

        // Черный носик
        const noseGeo = new THREE.BoxGeometry(0.18, 0.14, 0.1);
        const nose = new THREE.Mesh(noseGeo, eyeMat);
        nose.position.set(0, -0.05, 0.55 + cfg.snout / 2);
        headGroup.add(nose);
      }

      // Глазки
      [-0.26, 0.26].forEach(ex => {
        const eyeGeo = new THREE.SphereGeometry(0.08, 6, 6);
        const eye = new THREE.Mesh(eyeGeo, eyeMat);
        eye.position.set(ex, 0.15, 0.46);
        headGroup.add(eye);
      });

      // Ушки / Гребень / Хохолок
      if (cfg.earType === 'pointy') {
        // Острые треугольные уши (Кот, Лис)
        [-0.32, 0.32].forEach(ux => {
          const earGeo = new THREE.ConeGeometry(0.2, 0.45, 4);
          const ear = new THREE.Mesh(earGeo, bodyMat);
          ear.position.set(ux, 0.58, 0);
          headGroup.add(ear);
        });
      } else if (cfg.earType === 'round') {
        // Круглые уши (Медведь, Бобёр)
        [-0.35, 0.35].forEach(ux => {
          const earGeo = new THREE.CylinderGeometry(0.2, 0.2, 0.1, 8);
          earGeo.rotateZ(Math.PI / 2);
          const ear = new THREE.Mesh(earGeo, bodyMat);
          ear.position.set(ux, 0.52, 0);
          headGroup.add(ear);
        });
      } else if (cfg.earType === 'tuft') {
        // Кисточки Филина
        [-0.3, 0.3].forEach(ux => {
          const tuftGeo = new THREE.ConeGeometry(0.12, 0.4, 4);
          const tuft = new THREE.Mesh(tuftGeo, bodyMat);
          tuft.position.set(ux, 0.55, 0.1);
          headGroup.add(tuft);
        });
      }

      g.add(headGroup);
      g.headGroup = headGroup;

      // 3. Лапки с шарнирами (Для реалистичной анимации ходьбы!)
      const legGeo = new THREE.BoxGeometry(0.32, 0.65, 0.32);
      const legMat = new THREE.MeshStandardMaterial({ color: cfg.color, roughness: 0.8 });

      const legs = {};
      const legPositions = [
        { key: 'legFL', x: -0.42, z: 0.45 },
        { key: 'legFR', x: 0.42,  z: 0.45 },
        { key: 'legBL', x: -0.42, z: -0.45 },
        { key: 'legBR', x: 0.42,  z: -0.45 }
      ];

      legPositions.forEach(lp => {
        const legPivot = new THREE.Group();
        legPivot.position.set(lp.x, 0.6, lp.z);

        const legMesh = new THREE.Mesh(legGeo, legMat);
        legMesh.position.y = -0.3;
        legMesh.castShadow = true;
        legPivot.add(legMesh);

        g.add(legPivot);
        legs[lp.key] = legPivot;
      });
      g.legs = legs;

      // 4. Хвостик
      const tailGroup = new THREE.Group();
      tailGroup.position.set(0, 1.0, -0.75);

      if (cfg.tail === 'bushy') {
        // Пушистый лисий хвост
        const tailGeo = new THREE.ConeGeometry(0.35, 1.2, 6);
        tailGeo.rotateX(-Math.PI / 2.8);
        const tail = new THREE.Mesh(tailGeo, bodyMat);
        tail.position.set(0, 0.2, -0.5);
        tailGroup.add(tail);
      } else if (cfg.tail === 'paddle') {
        // Широкий хвост бобра
        const tailGeo = new THREE.BoxGeometry(0.65, 0.12, 1.1);
        const tailMat = new THREE.MeshStandardMaterial({ color: 0x3E2310 });
        const tail = new THREE.Mesh(tailGeo, tailMat);
        tail.position.set(0, -0.2, -0.5);
        tailGroup.add(tail);
      } else {
        // Обычный хвостик
        const tailGeo = new THREE.CylinderGeometry(0.1, 0.08, 0.8, 6);
        tailGeo.rotateX(-Math.PI / 3);
        const tail = new THREE.Mesh(tailGeo, bodyMat);
        tail.position.set(0, 0.2, -0.35);
        tailGroup.add(tail);
      }
      g.add(tailGroup);
      g.tailGroup = tailGroup;

      return g;
    }

    updateAgentSpeechBubble(agent, text) {
      if (agent.bubbleSprite) {
        agent.group.remove(agent.bubbleSprite);
        if (agent.bubbleSprite.material.map) agent.bubbleSprite.material.map.dispose();
        agent.bubbleSprite.material.dispose();
      }

      if (!text) return;

      const canvas = document.createElement('canvas');
      canvas.width = 384;
      canvas.height = 128;
      const ctx = canvas.getContext('2d');

      // Плашка диалога с хвостиком
      ctx.fillStyle = 'rgba(15, 23, 42, 0.92)';
      ctx.roundRect(6, 6, 372, 96, 16);
      ctx.fill();

      ctx.strokeStyle = '#' + (agent.accentColor || 0xFFC700).toString(16).padStart(6, '0');
      ctx.lineWidth = 4;
      ctx.stroke();

      // Треугольный хвостик вниз
      ctx.fillStyle = 'rgba(15, 23, 42, 0.92)';
      ctx.beginPath();
      ctx.moveTo(180, 102);
      ctx.lineTo(204, 102);
      ctx.lineTo(192, 120);
      ctx.closePath();
      ctx.fill();

      // Текст реплики
      ctx.font = 'bold 20px sans-serif';
      ctx.fillStyle = '#FFC700';
      ctx.fillText(`${agent.icon} ${agent.name}:`, 20, 36);

      ctx.font = '16px sans-serif';
      ctx.fillStyle = '#FFFFFF';
      this.wrapText(ctx, text, 20, 64, 340, 22);

      const tex = new THREE.CanvasTexture(canvas);
      tex.minFilter = THREE.LinearFilter;
      const mat = new THREE.SpriteMaterial({ map: tex, transparent: true, depthTest: false });
      const sprite = new THREE.Sprite(mat);
      sprite.position.set(0, 3.4, 0);
      sprite.scale.set(5.5, 1.8, 1.0);
      agent.group.add(sprite);
      agent.bubbleSprite = sprite;
      agent.bubbleTimer = 300;
    }

    wrapText(ctx, text, x, y, maxWidth, lineHeight) {
      const words = text.split(' ');
      let line = '';
      for (let n = 0; n < words.length; n++) {
        const testLine = line + words[n] + ' ';
        const metrics = ctx.measureText(testLine);
        if (metrics.width > maxWidth && n > 0) {
          ctx.fillText(line, x, y);
          line = words[n] + ' ';
          y += lineHeight;
        } else {
          line = testLine;
        }
      }
      ctx.fillText(line, x, y);
    }

    buildParticleSystems() {
      // 1. Дым канифоли и кузницы Кота
      const smokeCount = 35;
      const smokeGeo = new THREE.BufferGeometry();
      const smokePositions = new Float32Array(smokeCount * 3);
      this.smokeData = [];

      const startX = -18 + 1.8;
      const startY = 5.9;
      const startZ = 8 + 1.2;

      for (let i = 0; i < smokeCount; i++) {
        smokePositions[i * 3] = startX + (Math.random() - 0.5) * 0.4;
        smokePositions[i * 3 + 1] = startY + Math.random() * 4.0;
        smokePositions[i * 3 + 2] = startZ + (Math.random() - 0.5) * 0.4;

        this.smokeData.push({
          vx: (Math.random() - 0.5) * 0.02,
          vy: 0.04 + Math.random() * 0.03,
          vz: (Math.random() - 0.5) * 0.02,
          origY: startY
        });
      }
      smokeGeo.setAttribute('position', new THREE.BufferAttribute(smokePositions, 3));

      // Круглая текстура частицы
      const pCanvas = document.createElement('canvas');
      pCanvas.width = 32;
      pCanvas.height = 32;
      const pCtx = pCanvas.getContext('2d');
      const grad = pCtx.createRadialGradient(16, 16, 0, 16, 16, 16);
      grad.addColorStop(0, 'rgba(235, 235, 245, 0.8)');
      grad.addColorStop(1, 'rgba(235, 235, 245, 0.0)');
      pCtx.fillStyle = grad;
      pCtx.fillRect(0, 0, 32, 32);

      const smokeTex = new THREE.CanvasTexture(pCanvas);
      const smokeMat = new THREE.PointsMaterial({
        size: 1.6,
        map: smokeTex,
        transparent: true,
        opacity: 0.45,
        depthWrite: false
      });
      this.smokeParticles = new THREE.Points(smokeGeo, smokeMat);
      this.scene.add(this.smokeParticles);

      // 2. Цветочная пыльца над полями фермы
      const pollenCount = 120;
      const pollenGeo = new THREE.BufferGeometry();
      const pollenPositions = new Float32Array(pollenCount * 3);
      for (let i = 0; i < pollenCount; i++) {
        pollenPositions[i * 3] = (Math.random() - 0.5) * 70;
        pollenPositions[i * 3 + 1] = 0.5 + Math.random() * 4.5;
        pollenPositions[i * 3 + 2] = (Math.random() - 0.5) * 70;
      }
      pollenGeo.setAttribute('position', new THREE.BufferAttribute(pollenPositions, 3));
      const pollenMat = new THREE.PointsMaterial({
        size: 0.35,
        color: 0xFEF08A,
        transparent: true,
        opacity: 0.75
      });
      this.pollenParticles = new THREE.Points(pollenGeo, pollenMat);
      this.scene.add(this.pollenParticles);
    }

    setupInteraction() {
      const dom = this.renderer.domElement;

      dom.addEventListener('pointerdown', (e) => {
        const rect = dom.getBoundingClientRect();
        this.mouse.x = ((e.clientX - rect.left) / rect.width) * 2 - 1;
        this.mouse.y = -((e.clientY - rect.top) / rect.height) * 2 + 1;

        this.raycaster.setFromCamera(this.mouse, this.camera);
        const intersects = this.raycaster.intersectObjects(this.scene.children, true);

        if (intersects.length > 0) {
          // Ищем среди родителей агента
          let hitAgent = null;
          let hitStation = null;
          let groundHit = null;

          for (const hit of intersects) {
            let cur = hit.object;
            while (cur && cur !== this.scene) {
              if (cur.userData && cur.userData.isAgent) {
                hitAgent = this.agents.find(a => a.id === cur.userData.agentId);
                break;
              }
              if (cur.userData && cur.userData.id && cur.userData.id.startsWith('ws_')) {
                hitStation = cur.userData;
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
          } else if (groundHit && this.selectedAgent) {
            // Направляем выбранного зверя на точку клика
            this.selectedAgent.targetPos.copy(groundHit);
            this.selectedAgent.targetPos.y = 0;
            this.selectedAgent.state = 'walking';
            this.updateAgentSpeechBubble(this.selectedAgent, `Бегу по полю на позицию (${Math.round(groundHit.x)}, ${Math.round(groundHit.z)})!`);
          }
        }
      });
    }

    selectAgent(agent) {
      this.selectedAgent = agent;
      this.updateAgentSpeechBubble(agent, `Привет! Я ${agent.name}. Моя задача: ${agent.thoughts}`);

      // Плавное приближение камеры к персонажу
      if (this.controls) {
        const targetPos = agent.group.position.clone();
        const camOffset = new THREE.Vector3(12, 9, 14);
        const newCamPos = targetPos.clone().add(camOffset);

        const startTime = performance.now();
        const startPos = this.camera.position.clone();
        const startTarget = this.controls.target.clone();

        const panAnim = () => {
          const now = performance.now();
          const p = Math.min((now - startTime) / 600, 1.0);
          const ease = p * (2 - p);

          this.camera.position.lerpVectors(startPos, newCamPos, ease);
          this.controls.target.lerpVectors(startTarget, targetPos, ease);
          this.controls.update();

          if (p < 1.0) requestAnimationFrame(panAnim);
        };
        panAnim();
      }

      // Голосовая озвучка реплики
      if (typeof speakText === 'function') {
        speakText(`${agent.name}: ${agent.dialogue}`);
      }

      // Подсветка карточки на веб-странице
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

    update() {
      if (this.paused) return;
      const delta = this.clock.getDelta();
      const time = this.clock.getElapsedTime();

      // 1. Вращение лопастей мельницы
      this.windmills.forEach(w => {
        w.rotation.z += 0.018 * this.speed;
      });

      // 2. Колебание деревьев на ветру
      this.trees.forEach(t => {
        t.mesh.rotation.z = Math.sin(time * 1.6 + t.seed) * 0.03;
        t.mesh.rotation.x = Math.cos(time * 1.2 + t.seed) * 0.02;
      });

      // 3. Анимация дыма кузницы
      if (this.smokeParticles) {
        const pos = this.smokeParticles.geometry.attributes.position;
        for (let i = 0; i < this.smokeData.length; i++) {
          const d = this.smokeData[i];
          pos.setX(i, pos.getX(i) + d.vx * this.speed);
          pos.setY(i, pos.getY(i) + d.vy * this.speed);
          pos.setZ(i, pos.getZ(i) + d.vz * this.speed);

          if (pos.getY(i) > d.origY + 5.5) {
            pos.setX(i, this.forgeChimneyPos.x + (Math.random() - 0.5) * 0.3);
            pos.setY(i, this.forgeChimneyPos.y);
            pos.setZ(i, this.forgeChimneyPos.z + (Math.random() - 0.5) * 0.3);
          }
        }
        pos.needsUpdate = true;
      }

      // 4. Мерцание огня кузницы
      if (this.forgePointLight) {
        this.forgePointLight.intensity = 1.8 + Math.sin(time * 8.0) * 0.5;
      }

      // 5. Логика и анимация ходьбы персонажей Артели
      this.agents.forEach(a => {
        const dist = a.group.position.distanceTo(a.targetPos);

        if (dist > 0.4 && a.state === 'walking') {
          // Поворачиваем зверя лицом к направлению движения
          const dir = a.targetPos.clone().sub(a.group.position).normalize();
          const targetAngle = Math.atan2(dir.x, dir.z);
          a.group.rotation.y = THREE.MathUtils.lerp(a.group.rotation.y, targetAngle, 0.15);

          // Движение вперед
          const step = Math.min(a.speed * delta * 2.8 * this.speed, dist);
          a.group.position.addScaledVector(dir, step);

          // Анимация шага лапок
          a.walkCycle += delta * 12.0 * this.speed;
          const legSwing = Math.sin(a.walkCycle) * 0.5;
          if (a.group.legs) {
            a.group.legs.legFL.rotation.x = legSwing;
            a.group.legs.legBR.rotation.x = legSwing;
            a.group.legs.legFR.rotation.x = -legSwing;
            a.group.legs.legBL.rotation.x = -legSwing;
          }
          // Покачивание тела
          a.group.position.y = Math.abs(Math.sin(a.walkCycle)) * 0.18;
          if (a.group.tailGroup) {
            a.group.tailGroup.rotation.y = Math.sin(a.walkCycle * 0.8) * 0.3;
          }
        } else {
          if (a.state === 'walking') {
            a.state = 'working';
            // Возвращаем лапки в исходное положение
            if (a.group.legs) {
              a.group.legs.legFL.rotation.x = 0;
              a.group.legs.legBR.rotation.x = 0;
              a.group.legs.legFR.rotation.x = 0;
              a.group.legs.legBL.rotation.x = 0;
            }
          }
          // Дыхание на месте
          a.group.position.y = Math.sin(time * 2.5 + a.homePos.x) * 0.05;
        }

        // Таймер речевого облачка
        if (a.bubbleTimer > 0) {
          a.bubbleTimer -= 1 * this.speed;
          if (a.bubbleTimer <= 0 && a.bubbleSprite) {
            a.group.remove(a.bubbleSprite);
            a.bubbleSprite = null;
          }
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
      this.camera.position.set(38, 32, 48);
      this.controls.target.set(0, 2, 0);
      this.controls.update();
    }

    setTimeOfDay(timeMode) {
      this.timeOfDay = timeMode;
      if (timeMode === 'sunset') {
        this.scene.background = new THREE.Color(0xF97316);
        this.scene.fog.color = new THREE.Color(0xFB923C);
        this.dirLight.color = new THREE.Color(0xFF8844);
        this.dirLight.intensity = 1.4;
        this.hemiLight.color = new THREE.Color(0xFDBA74);
      } else if (timeMode === 'night') {
        this.scene.background = new THREE.Color(0x0F172A);
        this.scene.fog.color = new THREE.Color(0x0F172A);
        this.dirLight.color = new THREE.Color(0x38BDF8);
        this.dirLight.intensity = 0.35;
        this.hemiLight.color = new THREE.Color(0x1E293B);
      } else {
        this.scene.background = new THREE.Color(0x87CEEB);
        this.scene.fog.color = new THREE.Color(0x87CEEB);
        this.dirLight.color = new THREE.Color(0xFFF4D4);
        this.dirLight.intensity = 1.25;
        this.hemiLight.color = new THREE.Color(0xC6E2FF);
      }
    }

    /**
     * Сквозная сценарная миссия Артели (Mission Pipeline)
     */
    startMissionPipeline(topicId = 1) {
      const pipelineSequence = [
        { from: 'soroka', to: 'bobr', item: '📜 LSI-ядро темы #' + topicId, dialogue: 'Бобёр, держи LSI-хвосты и интент новичка!' },
        { from: 'bobr', to: 'kot', item: '🪵 Бриф ГОСТ Р МЭК #' + topicId, dialogue: 'Кот, плотина стандартов готова! Пиши триаду!' },
        { from: 'kot', to: 'barsuk', item: '📝 Черновик статьи #' + topicId, dialogue: 'Барсук, текст готов! Нужен точный термопрофиль ПОС-61.' },
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
        const receiverStation = this.stationMeshes[step.to];

        if (sender && receiverStation) {
          sender.holdingItem = step.item;
          sender.targetPos.copy(receiverStation.position).add(new THREE.Vector3(2, 0, 2));
          sender.state = 'walking';
          this.updateAgentSpeechBubble(sender, step.dialogue);

          if (typeof speakText === 'function') {
            speakText(`${sender.name}: ${step.dialogue}`);
          }

          const checkArrival = setInterval(() => {
            const dist = sender.group.position.distanceTo(sender.targetPos);
            if (dist < 1.2) {
              clearInterval(checkArrival);
              sender.state = 'working';
              setTimeout(() => {
                sender.targetPos.copy(sender.homePos);
                sender.state = 'walking';
                currentStep++;
                setTimeout(runStep, 1500);
              }, 2000);
            }
          }, 300);
        }
      };

      runStep();
    }
  }

  // Экспортируем глобально
  window.Farm3DEngine = Farm3DEngine;

  // Инициализация при загрузке документа
  document.addEventListener('DOMContentLoaded', () => {
    const container = document.getElementById('farm3DContainer');
    if (container && typeof THREE !== 'undefined') {
      window.farm3D = new Farm3DEngine('farm3DContainer');
      // Сохраняем совместимость с window.aiTown
      window.aiTown = window.farm3D;
    }
  });
})();
