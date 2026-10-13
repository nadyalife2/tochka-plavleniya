# Лицензии используемых ассетов (Assets & Fonts Licenses) — «ТОЧКА ПЛАВЛЕНИЯ» (theme4)

В теме `theme4` используются открытые шрифты и иконки, соответствующие принципам автономности, независимости от сторонних CDN и открытого ПО.

---

## 1. Иконки: Lucide Icons (SVG)
- **Расположение:** `theme4/assets/icons/*.svg`
- **Лицензия:** [ISC License](https://github.com/lucide-icons/lucide/blob/main/LICENSE)
- **Авторское право:** Copyright (c) Lucide Contributors (fork of Feather Icons by Cole Bemis)
- **Условия:** Разрешается свободное коммерческое и некоммерческое использование, изменение и распространение при условии сохранения уведомления об авторских правах.

```text
ISC License

Copyright (c) for portions of Lucide are held by Cole Bemis 2013-2022 as part of Feather (MIT).
All other copyright (c) 2022-present Lucide Contributors.

Permission to use, copy, modify, and/or distribute this software for any
purpose with or without fee is hereby granted, provided that the above
copyright notice and this permission notice appear in all copies.
```

---

## 2. Шрифты (Typography)
Все локальные шрифты темы распространяются по открытой лицензии **SIL Open Font License, Version 1.1 (OFL-1.1)**:

### 2.1. Inter
- **Автор:** Rasmus Andersson (rsms)
- **Лицензия:** [SIL Open Font License 1.1](https://scripts.sil.org/OFL)
- **Применение:** Основной наборный текст (UI & Body).

### 2.2. Space Grotesk
- **Автор:** Florian Karsten
- **Лицензия:** [SIL Open Font License 1.1](https://scripts.sil.org/OFL)
- **Применение:** Заголовки, логотип и инженерные плашки.

### 2.3. JetBrains Mono
- **Автор:** JetBrains s.r.o.
- **Лицензия:** [SIL Open Font License 1.1 / Apache 2.0](https://www.jetbrains.com/lp/mono/)
- **Применение:** Технические характеристики, калькуляторы, моноширинные табло и код.

### 2.4. Caveat
- **Автор:** Impallari Type
- **Лицензия:** [SIL Open Font License 1.1](https://scripts.sil.org/OFL)
- **Применение:** Рукописные редакторские ремарки и заметки на полях (исключительно для TL;DR и цитат, не в элементах управления).

### 2.5. Newsreader
- **Автор:** Production Type
- **Лицензия:** [SIL Open Font License 1.1](https://scripts.sil.org/OFL)
- **Применение:** Лонгриды и заголовки статей журнала.

---

## 3. Политика автономности
1. Все SVG-иконки хранятся локально в `theme4/assets/icons/` и отдаются сервером без внешних запросов.
2. Сторонние шрифтовые иконки (шрифты иконок) исключены для ускорения отрисовки (FCP < 0.8s) и надежности без интернета.
3. Лицензии позволяют использование в производственных средах как на standalone PHP-сервере, так и в WordPress.
