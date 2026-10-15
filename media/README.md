# Демонстрационные материалы и скриншоты темы «Точка плавления» (theme4)

В этой папке собраны контрольные скриншоты на всех адаптивных контрольных точках (375px, 768px, 1024px, 1440px) и видеозапись работы интерактивных инструментов, сделанные после полного цикла аудита, устранения замечаний и синхронизации с `DESIGN.md`.

---

## 📹 Видеообзор работы темы
Файл: [`media/video/site_demo_walkthrough.webp`](video/site_demo_walkthrough.webp) (анимированный WebP, ~2.5 МБ)

Демонстрирует:
1. Загрузку главной страницы `index.php` со всеми восстановленными разделами (задачи, база знаний, калькуляторы, 10 мини-уроков, доверие, FAQ, Schema.org разметка).
2. Плавный скролл и якорную навигацию (`#top`, `#tasks`, `#articles`, `#rubrics`, `#lessons`, `#faq`, `#trust`).
3. Переход в «Интерактивный верстак инженера» `interactive.php`.
4. Интерактивную работу калькулятора режимов пайки:
   - Выбор припоя (ПОС-61 с Тпл 183–190°C по ГОСТ 21931 vs Sn63Pb37 с эвтектической Тпл 183°C).
   - Выбор теплоемкости платы и типа жала.
   - Расчет практического диапазона температур жала (260–295 °C).
5. 2D тепловой симулятор платы:
   - Активный термоконтакт с платой.
   - Динамический расчет изотерм с реальной дельтой времени (`performance.now()`).
   - Неснижаемый учет времени нахождения выше ликвидуса (TAL).

---

## 📸 Скриншоты по разрешениям экрана

### 1. Главная страница (`index.php`)
- **375px (Mobile):** [screenshots/index_375.png](screenshots/index_375.png)
- **768px (Tablet):** [screenshots/index_768.png](screenshots/index_768.png)
- **1024px (Laptop):** [screenshots/index_1024.png](screenshots/index_1024.png)
- **1440px (Desktop):** [screenshots/index_1440.png](screenshots/index_1440.png)

### 2. Интерактивный верстак (`interactive.php`)
- **375px (Mobile):** [screenshots/interactive_375.png](screenshots/interactive_375.png)
- **768px (Tablet):** [screenshots/interactive_768.png](screenshots/interactive_768.png)
- **1024px (Laptop):** [screenshots/interactive_1024.png](screenshots/interactive_1024.png)
- **1440px (Desktop):** [screenshots/interactive_1440.png](screenshots/interactive_1440.png)

### 3. Рубрика / Каталог (`category.php`)
- **375px (Mobile):** [screenshots/category_375.png](screenshots/category_375.png)
- **768px (Tablet):** [screenshots/category_768.png](screenshots/category_768.png)
- **1024px (Laptop):** [screenshots/category_1024.png](screenshots/category_1024.png)
- **1440px (Desktop):** [screenshots/category_1440.png](screenshots/category_1440.png)

### 4. Статья / Руководство (`article.php`)
- **375px (Mobile):** [screenshots/article_375.png](screenshots/article_375.png)
- **768px (Tablet):** [screenshots/article_768.png](screenshots/article_768.png)
- **1024px (Laptop):** [screenshots/article_1024.png](screenshots/article_1024.png)
- **1440px (Desktop):** [screenshots/article_1440.png](screenshots/article_1440.png)

### 5. Страница 404 (`404.php`)
- **375px (Mobile):** [screenshots/notfound_375.png](screenshots/notfound_375.png)
- **768px (Tablet):** [screenshots/notfound_768.png](screenshots/notfound_768.png)
- **1024px (Laptop):** [screenshots/notfound_1024.png](screenshots/notfound_1024.png)
- **1440px (Desktop):** [screenshots/notfound_1440.png](screenshots/notfound_1440.png)
