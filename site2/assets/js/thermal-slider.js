/**
 * Интерактивный симулятор термопрофиля пайки BGA / SMD (Чистый стиль)
 */

const thermalProfileData = {
  sac305: {
    name: "Бессвинцовый (SAC305)",
    liquidus: "217°C",
    peak: "235°C – 245°C",
    zones: [
      {
        id: "preheat",
        title: "1. Преднагрев",
        tempRange: "25°C → 150°C",
        rampRate: "1 – 2.5°C / сек",
        duration: "60 – 90 сек",
        purpose: "Плавное испарение летучих растворителей из флюса, прогрев многослойного текстолита, защита от термоудара.",
        risk: "При превышении скорости >3°C/сек возможен выброс флюса и появление шариков припоя."
      },
      {
        id: "soak",
        title: "2. Замачивание",
        tempRange: "150°C → 180°C",
        rampRate: "0.5 – 1.0°C / сек",
        duration: "60 – 120 сек",
        purpose: "Химическая активация флюса, удаление окислов, выравнивание градиента температур между платой и BGA-чипом.",
        risk: "Слишком долгое замачивание (>150с) полностью выжигает флюс до момента плавления."
      },
      {
        id: "reflow",
        title: "3. Оплавление (Пик)",
        tempRange: "217°C → 240°C",
        rampRate: "Время над ликвидусом (TAL): 45 – 75 сек",
        duration: "Пик 10 – 20 сек",
        purpose: "Полное расплавление припоя, смачивание контактных площадок и формирование интерметаллического слоя (IMC).",
        risk: "Превышение 250°C ведет к деламинации текстолита и разрушению кремниевого кристалла."
      },
      {
        id: "cooling",
        title: "4. Охлаждение",
        tempRange: "240°C → 100°C",
        rampRate: "2 – 4°C / сек",
        duration: "30 – 60 сек",
        purpose: "Контролируемое затвердевание для получения мелкозернистой прочной кристаллической структуры.",
        risk: "Резкий обдув холодным воздухом вызывает внутренние микротрещины в BGA-шарах."
      }
    ]
  },
  pos61: {
    name: "Свинцовый (ПОС-61)",
    liquidus: "183°C",
    peak: "210°C – 220°C",
    zones: [
      {
        id: "preheat",
        title: "1. Преднагрев",
        tempRange: "25°C → 130°C",
        rampRate: "1 – 3°C / сек",
        duration: "45 – 75 сек",
        purpose: "Мягкий прогрев платы, постепенное испарение растворителей.",
        risk: "Резкий нагрев ведет к деформации тонкого текстолита."
      },
      {
        id: "soak",
        title: "2. Замачивание",
        tempRange: "130°C → 160°C",
        rampRate: "0.5 – 1.0°C / сек",
        duration: "45 – 90 сек",
        purpose: "Активация канифольного или синтетического флюса, удаление окислов.",
        risk: "Выгорание активных компонентов флюса."
      },
      {
        id: "reflow",
        title: "3. Оплавление (Пик)",
        tempRange: "183°C → 215°C",
        rampRate: "Время над ликвидусом: 30 – 60 сек",
        duration: "Пик 10 – 15 сек",
        purpose: "Идеальное растекание эвтектического сплава с зеркальным блеском галтели.",
        risk: "Перегрев свыше 230°C для свинца избыточен."
      },
      {
        id: "cooling",
        title: "4. Охлаждение",
        tempRange: "215°C → 80°C",
        rampRate: "2 – 4°C / сек",
        duration: "30 – 45 сек",
        purpose: "Формирование пластичного паяного шва.",
        risk: "Механические вибрации до застывания вызывают холодную пайку."
      }
    ]
  }
};

let currentAlloy = "sac305";
let currentZoneIndex = 2;

function renderThermalWidget() {
  const container = document.getElementById("thermal-simulator-widget");
  if (!container) return;

  const data = thermalProfileData[currentAlloy];
  const activeZone = data.zones[currentZoneIndex];

  container.innerHTML = `
    <div class="border border-gray-200 rounded-lg p-5 bg-gray-50/70 my-6">
      <div class="flex flex-wrap items-center justify-between gap-3 pb-3 border-b border-gray-200">
        <div>
          <span class="text-[11px] font-mono font-semibold uppercase text-orange-600 tracking-wider">Интерактивный верстак</span>
          <h4 class="text-base font-bold text-gray-900 mt-0.5">Симулятор термопрофиля пайки BGA</h4>
        </div>
        <div class="flex items-center gap-1.5 bg-white p-1 rounded-md border border-gray-200">
          <button id="btn-alloy-sac" class="px-2.5 py-1 text-xs font-medium rounded transition-colors ${currentAlloy === 'sac305' ? 'bg-orange-600 text-white shadow-sm' : 'text-gray-600 hover:text-gray-900'}">
            SAC305 (Бессвинец)
          </button>
          <button id="btn-alloy-pos" class="px-2.5 py-1 text-xs font-medium rounded transition-colors ${currentAlloy === 'pos61' ? 'bg-orange-600 text-white shadow-sm' : 'text-gray-600 hover:text-gray-900'}">
            ПОС-61 (Свинец)
          </button>
        </div>
      </div>

      <!-- Зоны кнопками -->
      <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 my-4">
        ${data.zones.map((zone, idx) => `
          <button data-zone-idx="${idx}" class="zone-tab-btn text-left p-2.5 rounded-md border transition-all ${idx === currentZoneIndex ? 'bg-white border-orange-500 shadow-sm' : 'bg-transparent border-gray-200 hover:bg-white text-gray-600'}">
            <div class="text-[10px] font-mono text-gray-400 uppercase">Фаза ${idx + 1}</div>
            <div class="text-xs font-semibold text-gray-900 truncate">${zone.title.replace(/^\d+\.\s*/, '')}</div>
            <div class="text-[11px] font-mono font-medium text-orange-600 mt-0.5">${zone.tempRange}</div>
          </button>
        `).join('')}
      </div>

      <!-- Детали активной зоны -->
      <div class="bg-white border border-gray-200 p-4 rounded-md">
        <div class="flex flex-wrap items-baseline justify-between gap-2 mb-2">
          <h5 class="text-sm font-bold text-gray-900">${activeZone.title}</h5>
          <span class="font-mono text-xs font-semibold px-2 py-0.5 bg-orange-50 text-orange-700 border border-orange-200 rounded">
            ${activeZone.tempRange}
          </span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 my-2 text-xs">
          <div class="p-2 bg-gray-50 rounded border border-gray-100">
            <span class="text-gray-400 block font-mono text-[10px] uppercase">Скорость нагрева</span>
            <span class="font-medium text-gray-800">${activeZone.rampRate}</span>
          </div>
          <div class="p-2 bg-gray-50 rounded border border-gray-100">
            <span class="text-gray-400 block font-mono text-[10px] uppercase">Длительность</span>
            <span class="font-medium text-gray-800">${activeZone.duration}</span>
          </div>
        </div>

        <p class="text-xs text-gray-600 leading-relaxed mt-2">
          <strong class="text-gray-900">Физика процесса:</strong> ${activeZone.purpose}
        </p>

        <div class="mt-2 text-xs text-red-700 bg-red-50 p-2.5 rounded border border-red-100">
          <strong>⚠️ Риск нарушения:</strong> ${activeZone.risk}
        </div>
      </div>
    </div>
  `;

  document.getElementById("btn-alloy-sac")?.addEventListener("click", () => {
    currentAlloy = "sac305";
    renderThermalWidget();
  });
  document.getElementById("btn-alloy-pos")?.addEventListener("click", () => {
    currentAlloy = "pos61";
    renderThermalWidget();
  });
  document.querySelectorAll(".zone-tab-btn").forEach(btn => {
    btn.addEventListener("click", () => {
      currentZoneIndex = parseInt(btn.getAttribute("data-zone-idx"), 10);
      renderThermalWidget();
    });
  });
}

document.addEventListener("DOMContentLoaded", renderThermalWidget);
