/**
 * Термопрофиль пайки BGA / SMD — Чистый редакционный стиль (TochkiCamp)
 */

const thermalProfileData = {
  sac305: {
    name: "SAC305 (Бессвинцовый)",
    liquidus: "217°C",
    peak: "235°C – 245°C",
    zones: [
      {
        num: "01",
        name: "Преднагрев",
        temp: "25°C → 150°C",
        ramp: "1.0 – 2.5 °C/сек",
        time: "60 – 90 сек",
        note: "Плавное испарение растворителей из флюса и равномерный прогрев всех слоев текстолита без термического удара.",
        caution: "Скорость нагрева выше 3°C/сек вызывает разбрызгивание флюса и микросмещение чипа."
      },
      {
        num: "02",
        name: "Активация",
        temp: "150°C → 190°C",
        ramp: "0.5 – 1.0 °C/сек",
        time: "60 – 120 сек",
        note: "Химическое растворение окислов меди и олова активаторами флюса. Выравнивание температуры платы и кристалла BGA.",
        caution: "Затягивание фазы дольше 150 сек приводит к преждевременному выгоранию флюса до точки расплавления."
      },
      {
        num: "03",
        name: "Оплавление",
        temp: "217°C → 240°C",
        ramp: "TAL: 45 – 75 сек",
        time: "Пик: 10 – 20 сек",
        note: "Полный переход шариков в жидкую фазу, смачивание контактных площадок и диффузионный рост интерметаллического слоя.",
        caution: "Превышение 248–250°C на кристалле ведет к деламинации подложки и необратимому перегреву кремния."
      },
      {
        num: "04",
        name: "Охлаждение",
        temp: "240°C → 100°C",
        ramp: "2.0 – 4.0 °C/сек",
        time: "30 – 60 сек",
        note: "Контролируемая кристаллизация припоя. Обеспечивает мелкозернистую структуру галтелей и высокую усталостную прочность.",
        caution: "Резкий обдув холодным воздухом создает внутренние механические напряжения и микротрещины в шарах."
      }
    ]
  },
  pos61: {
    name: "ПОС-61 (Свинцовый эвтектик)",
    liquidus: "183°C",
    peak: "210°C – 220°C",
    zones: [
      {
        num: "01",
        name: "Преднагрев",
        temp: "25°C → 130°C",
        ramp: "1.0 – 2.0 °C/сек",
        time: "45 – 75 сек",
        note: "Постепенный прогрев узла и удаление летучих фракций флюса.",
        caution: "Слишком быстрый нагрев деформирует тонкие платы."
      },
      {
        num: "02",
        name: "Активация",
        temp: "130°C → 160°C",
        ramp: "0.5 – 1.0 °C/сек",
        time: "45 – 90 сек",
        note: "Очистка контактных площадок от окислов, подготовка к смачиванию.",
        caution: "Перегрев на стадии активации сушит флюс."
      },
      {
        num: "03",
        name: "Оплавление",
        temp: "183°C → 215°C",
        ramp: "TAL: 30 – 60 сек",
        time: "Пик: 10 – 15 сек",
        note: "Мгновенное растекание эвтектического сплава с формированием зеркальной блестящей галтели.",
        caution: "Нагрев свыше 225°C для свинцовых сплавов избыточен."
      },
      {
        num: "04",
        name: "Охлаждение",
        temp: "215°C → 80°C",
        ramp: "2.0 – 4.0 °C/сек",
        time: "30 – 45 сек",
        note: "Формирование пластичного паяного соединения с низкой вероятностью трещин.",
        caution: "Любые вибрации платы до полного застывания приводят к браку («холодная пайка»)."
      }
    ]
  }
};

let currentAlloy = "sac305";
let currentZoneIndex = 2; // Default to Reflow

function renderThermalWidget() {
  const container = document.getElementById("thermal-simulator-widget");
  if (!container) return;

  const data = thermalProfileData[currentAlloy];
  const activeZone = data.zones[currentZoneIndex];

  container.innerHTML = `
    <div class="thermal-profile-sheet">
      
      <!-- Верхняя панель: выбор сплава -->
      <div class="sheet-header">
        <span class="sheet-title">Сплав:</span>
        <div class="sheet-alloy-toggle">
          <button id="btn-alloy-sac" class="alloy-toggle-btn ${currentAlloy === 'sac305' ? 'active' : ''}">
            SAC305 (217°C)
          </button>
          <button id="btn-alloy-pos" class="alloy-toggle-btn ${currentAlloy === 'pos61' ? 'active' : ''}">
            ПОС-61 (183°C)
          </button>
        </div>
      </div>

      <!-- 4 шага фаз -->
      <div class="sheet-phases-grid">
        ${data.zones.map((zone, idx) => `
          <button data-zone-idx="${idx}" class="sheet-phase-btn ${idx === currentZoneIndex ? 'active' : ''}">
            <div class="phase-num">${zone.num}</div>
            <div class="phase-name">${zone.name}</div>
            <div class="phase-temp">${zone.temp}</div>
          </button>
        `).join('')}
      </div>

      <!-- Карточка выбранной фазы -->
      <div class="sheet-details">
        <div class="details-top">
          <div class="details-heading">
            <span class="details-badge">Фаза ${activeZone.num}</span>
            <h4 class="details-name">${activeZone.name} · ${activeZone.temp}</h4>
          </div>
        </div>

        <div class="details-params-row">
          <div class="param-item">
            <span class="param-label">Скорость / TAL:</span>
            <span class="param-val">${activeZone.ramp}</span>
          </div>
          <div class="param-item">
            <span class="param-label">Длительность:</span>
            <span class="param-val">${activeZone.time}</span>
          </div>
        </div>

        <p class="details-desc">
          <strong>Процесс:</strong> ${activeZone.note}
        </p>

        <p class="details-caution">
          <strong>Ограничение:</strong> ${activeZone.caution}
        </p>
      </div>

    </div>
  `;

  // Attach event listeners
  const btnSac = document.getElementById("btn-alloy-sac");
  const btnPos = document.getElementById("btn-alloy-pos");

  if (btnSac) {
    btnSac.addEventListener("click", () => {
      currentAlloy = "sac305";
      renderThermalWidget();
    });
  }

  if (btnPos) {
    btnPos.addEventListener("click", () => {
      currentAlloy = "pos61";
      renderThermalWidget();
    });
  }

  const phaseBtns = container.querySelectorAll(".sheet-phase-btn");
  phaseBtns.forEach(btn => {
    btn.addEventListener("click", () => {
      currentZoneIndex = parseInt(btn.getAttribute("data-zone-idx"), 10);
      renderThermalWidget();
    });
  });
}

document.addEventListener("DOMContentLoaded", renderThermalWidget);
