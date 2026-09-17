/**
 * thermal-slider.js — Учебная модель профиля оплавления конвекционной печи (SMD / BGA)
 * Привязано к TDS промышленных паяльных паст:
 *   - Kester NP505-HR / Indium8.9HF (SAC305)
 *   - Kester 256 / Alpha OM-5100 (Sn63Pb37)
 * 
 * Стандарты:
 *   - IPC-7530A: Руководство по температурному профилированию процессов оплавления
 *   - IPC/JEDEC J-STD-020E: Классификационный профиль влагочувствительности компонентов (MSL).
 *     Важно: 260 °C — разрушающий стресс-тест корпуса ИМС на «попкорнинг», 
 *     а НЕ рабочая уставка печи!
 */

const thermalProfileData = {
  sac305: {
    name: "SAC305 (Sn96.5Ag3Cu0.5, TDS Kester NP505-HR / Indium8.9HF)",
    liquidus: "217°C",
    peak: "235°C – 245°C",
    notes: "Бессвинцовый профиль оплавления по IPC-7530A",
    zones: [
      {
        num: "01",
        name: "Преднагрев (Ramp-to-Soak)",
        temp: "25°C → 150°C",
        ramp: "1.0 – 2.0 °C/сек",
        time: "60 – 90 сек",
        note: "Постепенный прогрев слоев стеклотекстолита и компонентов. Плавное испарение низкокипящих растворителей из флюса без микровзрывов.",
        caution: "Скорость нагрева свыше 2.5–3.0 °C/с вызывает тепловой шок многослойных керамических конденсаторов (MLCC) и разбрызгивание пасты (solder balls)."
      },
      {
        num: "02",
        name: "Активация (Soak / Выдержка)",
        temp: "150°C → 190–200°C",
        ramp: "0.5 – 1.0 °C/сек",
        time: "60 – 120 сек",
        note: "Химическое восстановление окислов активаторами флюса. Выравнивание температуры между легкими резисторами 0402 и массивными BGA/дросселями перед плавлением.",
        caution: "Слишком длинная фаза (>120 с) полностью истощает защитные свойства флюса до расплавления, приводя к окислению шариков и дефекту «голова на подушке» (Head-in-Pillow)."
      },
      {
        num: "03",
        name: "Оплавление (Reflow / TAL)",
        temp: "217°C → 245°C",
        ramp: "TAL: 45 – 75 сек (Indium8.9HF: 30–90 с)",
        time: "Пик: 10 – 20 сек при 237–245 °C",
        note: "Переход порошка припоя в жидкость, смачивание контактных площадок и образование интерметаллического слоя Cu6Sn5. Пик выбирается минимально достаточным для прогрева всех BGA.",
        caution: "Не путайте профиль пайки (IPC-7530A: пик 235–245 °C) с квалификацией по IPC/JEDEC J-STD-020E (предел 260 °C для испытания стойкости к попкорнингу)! Превышение 250 °C на кристалле повреждает кремний."
      },
      {
        num: "04",
        name: "Охлаждение (Cooling)",
        temp: "245°C → 100°C",
        ramp: "2.0 – 4.0 °C/сек",
        time: "30 – 60 сек",
        note: "Контролируемая кристаллизация сплава. Быстрое охлаждение формирует мелкозернистую микроструктуру интерметаллидов с максимальной усталостной прочностью галтели.",
        caution: "Охлаждение быстрее 4 °C/с создает опасные механические напряжения из-за разницы температурного расширения (CTE) текстолита и кремниевых чипов."
      }
    ]
  },
  pos61: {
    name: "Sn63Pb37 / ПОС-61 (Свинцовые сплавы, TDS Kester 256)",
    liquidus: "183°C (Sn63) / 190°C (ПОС-61)",
    peak: "205°C – 215°C",
    notes: "Для Sn63Pb37 эвтектика 183°C; для ПОС-61 интервал солидус 183°C / ликвидус 190°C по ГОСТ 21930-76",
    zones: [
      {
        num: "01",
        name: "Преднагрев (Ramp)",
        temp: "25°C → 130°C",
        ramp: "1.0 – 2.0 °C/сек",
        time: "45 – 75 сек",
        note: "Равномерный прогрев печатной платы и испарение летучих растворителей из канифольного связующего.",
        caution: "Слишком крутой подъем температуры деформирует тонкие печатные платы (warpage)."
      },
      {
        num: "02",
        name: "Активация (Soak)",
        temp: "130°C → 160°C",
        ramp: "0.5 – 1.0 °C/сек",
        time: "45 – 90 сек",
        note: "Удаление оксидов с меди и выводов компонентов. Термодинамическая стабилизация всего узла.",
        caution: "Превышение 165 °C на этапе выдержки приводит к пересыханию флюса и образованию бусинок припоя (solder beading)."
      },
      {
        num: "03",
        name: "Оплавление (Reflow)",
        temp: "183°C → 215°C",
        ramp: "TAL (>183°C): 30 – 60 сек",
        time: "Пик: 10 – 15 сек при 205–215 °C",
        note: "Мгновенное смачивание жидким оловянно-свинцовым сплавом с формированием гладких зеркальных галтелей. Отличная стойкость к термоциклированию.",
        caution: "Уставка печи свыше 225 °C для свинцовых паст избыточна: она лишь ускоряет рост хрупких интерметаллических фаз Cu3Sn."
      },
      {
        num: "04",
        name: "Охлаждение (Cooling)",
        temp: "215°C → 80°C",
        ramp: "2.0 – 4.0 °C/сек",
        time: "30 – 45 сек",
        note: "Кристаллизация эвтектической оловянно-свинцовой матрицы без образования дендритных пустот.",
        caution: "Любые механические толчки или вибрация платы на конвейере до застывания припоя (<183 °C) вызывают дефект «нарушенной/холодной пайки» (disturbed joint)."
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
        <div class="flex flex-col sm:flex-row sm:items-center justify-between w-full gap-2">
          <div>
            <span class="sheet-title">Сплав и TDS пасты:</span>
            <div class="text-[11px] font-mono text-ink-muted">${data.notes}</div>
          </div>
          <div class="sheet-alloy-toggle">
            <button id="btn-alloy-sac" class="alloy-toggle-btn ${currentAlloy === 'sac305' ? 'active' : ''}">
              SAC305 (217–220°C)
            </button>
            <button id="btn-alloy-pos" class="alloy-toggle-btn ${currentAlloy === 'pos61' ? 'active' : ''}">
              Sn63Pb37 / ПОС-61 (183–190°C)
            </button>
          </div>
        </div>
      </div>

      <!-- 4 шага фаз -->
      <div class="sheet-phases-grid">
        ${data.zones.map((zone, idx) => `
          <button type="button" data-zone-idx="${idx}" class="sheet-phase-btn ${idx === currentZoneIndex ? 'active' : ''}">
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
          <strong>Физико-химический процесс:</strong> ${activeZone.note}
        </p>

        <p class="details-caution">
          <strong>Технологическое ограничение:</strong> ${activeZone.caution}
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
