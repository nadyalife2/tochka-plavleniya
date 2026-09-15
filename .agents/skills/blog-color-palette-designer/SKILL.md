---
name: blog-color-palette-designer
description: Expert web colorist and palette designer tailored for blogs, editorial platforms, and content-heavy websites. Focuses on reading ergonomics (reducing eye strain), WCAG AAA contrast, 60-30-10 distribution, and harmonious accent integration (such as brand yellow/amber).
---

# Blog Color Palette Designer Skill

Use this skill when creating, redesigning, or refining color palettes for blogs, documentation, technical articles, and editorial platforms.

---

## 1. Core Principles of Blog Color Ergonomics

### 1.1 Reader Fatigue Prevention (Защита от зрительной усталости)
Long-form reading demands low visual fatigue:
- **Never use pure #000000 on pure #FFFFFF**: High luminance disparity produces an optical halation effect and tires the eyes within 5–10 minutes of reading.
- **Light Themes**: Use off-white or warm paper bases (`#F9F9FB`, `#F7F5F0`, `#F5F7FA`) paired with deep charcoal or warm slate text (`#1E293B`, `#222426`, `#2A2723`).
- **Dark Themes**: Avoid `#000000` deep black for content blocks; use dark navy or graphite surfaces (`#0F131C`, `#12161F`, `#181A20`) with soft silver-white typography (`#E2E8F0`, `#E5E7EB`).

### 1.2 The 60-30-10 Rule for Editorial Sites
- **60% Primary Surface & Reading Area**: Background canvas, article reading column, neutral body.
- **30% Structural & Typographic Hierarchy**: Sidebar, header border, card outlines, article headings (H1-H3), author avatars, footer, code block containers.
- **10% Brand Accent & Micro-Interactions**: Category tags, active links, bookmarking, reading progress bar, CTA subscribe buttons.

### 1.3 WCAG 2.1 Contrast Standards
- **Body Text**: Target **WCAG AAA** (minimum **7:1** against the reading background).
- **Headings (H1/H2) & Large Text**: Minimum **4.5:1** (WCAG AA) to **7:1**.
- **Interactive borders, icons, form fields**: Minimum **3:1**.

---

## 2. Working with Yellow as a Core Brand Accent

Yellow has the highest perceived luminance of all chromatic hues:
1. **The Light Background Rule**: Never use pure yellow (`#FFD700` or `#FACC15`) for plain text or thin icons on white/cream backgrounds (contrast is under 1.4:1 — unreadable).
2. **Proper Applications of Yellow in Light Themes**:
   - **Badges / Pills**: Yellow fill with dark charcoal text (e.g. `background: #FDE047; color: #1E1B18; font-weight: 600;`).
   - **Highlights & Callouts**: Translucent yellow mark/callout background (`rgba(250, 204, 21, 0.18)`).
   - **Deep Amber / Ochre Alternative**: Use a rich warm ochre/gold (`#D97706`, `#B45309`) for text links or icons when direct contrast against white is required (> 4.5:1).
   - **High-contrast Borders / Shadow Accent**: Highlighting cards on hover (`box-shadow: 4px 4px 0 #EAB308`).
3. **Yellow in Dark Themes**:
   - Exceptional standout power. Electric amber/canary yellow (`#FACC15`, `#FDE047`, `#FFD23F`) creates punchy, high-tech accents against `#0F131C` with > 12:1 contrast.

---

## 3. Curated Blog Palette Archetypes (with Preserved Yellow)

### Palette 1: "Industrial Workbench" (Инженерный верстак / Craft Paper + Caution Yellow)
*Vibe*: Мастерская, DIY, пайка, осязаемый крафт, винтажное качество.
- **Background (60%)**: Теплая фактурная бумага `#F6F4EE`
- **Surface / Cards**: Чистый белый `#FFFFFF` с черным графическим контуром `#222222`
- **Text Main**: Глубокий темный уголь `#1F2022` (Контраст 12.8:1 к фону)
- **Primary Yellow (10%)**: Золотистый медовый янтарь `#F59E0B` / `#EAB308`
- **Supporting Accents**: Паяльная медь / терракота `#E05A1F`, графит `#2D3748`

### Palette 2: "Cyber Solder / Dark Tech" (Тёмный кибер-лабораторный стиль)
*Vibe*: Профессиональная лаборатория электроники, ночная пайка, современные осциллографы.
- **Background (60%)**: Глубокий темный антрацит `#0D1117`
- **Surface / Cards (30%)**: Темный графит плат `#161B22` с тонкой рамкой `#30363D`
- **Text Main**: Мягкий люминесцентный белый `#E6EDF3` (Контраст 14.2:1)
- **Primary Yellow (10%)**: Сигнальный неоновый желтый `#FACC15` / `#FFE600` (Идеальный контраст 13:1 к черному!)
- **Supporting Accents**: Электрический циановый провод `#38BDF8`, флюс-оранж `#FB923C`

### Palette 3: "Nordic Minimalist / Clean Studio" (Скандинавский минимализм + Акцентный канареечный)
*Vibe*: VC.ru / Substack / премиум-медиа, максимум воздуха, фокус на чтении.
- **Background (60%)**: Холодный свежий серый `#F8FAFC`
- **Surface / Cards (30%)**: Абсолютно белый `#FFFFFF` с мягкой тенью и тонким разделителем `#E2E8F0`
- **Text Main**: Чернильный глубокий слейт `#0F172A` (Контраст 16:1)
- **Primary Yellow (10%)**: Солнечный канареечный `#EAB308` (для плашек, тегов рубрик, кнопок с темным текстом)
- **Supporting Accents**: Сдержанный кобальт `#2563EB`, приглушенный шалфей `#475569`

### Palette 4: "PCB Vintage Green & Gold" (Текстолит печатной платы + Золотые дорожки)
*Vibe*: Настоящая электроника, ретро-платы, аутентичный дух инженерии.
- **Background (60%)**: Спокойный бледно-шалфейный `#EEF4F0`
- **Surface / Cards (30%)**: Белый `#FFFFFF` с темно-хвойным контуром `#1E3A2B`
- **Text Main**: Глубокий патинированный зеленый графит `#13251B`
- **Primary Yellow (10%)**: Золото контактов ENIG Gold `#D97706` / `#F59E0B`
- **Supporting Accents**: Изумрудная маска `#059669`, мягкий оранжевый канифоль `#EA580C`

---

## 4. Implementation Checklist for Any Palette Change

1. [ ] **Проверка контраста основного текста** по WCAG (минимум 7:1).
2. [ ] **Проверка кнопок и тегов с желтым**: темный текст `#1E1B18` на желтом фоне (НЕ белый текст на желтом!).
3. [ ] **Тестирование ссылок внутри статьи**: ссылка должна отличаться от основного текста не только цветом, но и подчеркиванием/весом при наведении.
4. [ ] **Кодовые блоки (`<pre><code>`)**: отдельный контрастный фон (темный или нейтрально-серый), чтобы синтаксис схем/кода легко считывался.
5. [ ] **Сохранение адаптивности**: темный режим и светлый режим должны зеркально сохранять узнаваемость бренда.
