# Triad-Omni Cyberdeck Web UI (Автономная Веб-Версия)

Автономный дистрибутив кибердек веб-интерфейса кластера **Triad-Omni Edge AI** (Xiaomi Mi 10T 5G, OnePlus Nord N10 5G, Redmi Note 4).

## Содержимое архива
- web_ui/index.html — единый интерактивный Cyberdeck интерфейс (управление кластером, живой мониторинг АКБ, переключение картриджей LoRA, OpenAI чат-терминал).
- web_ui/multi_lora_cartridge_architecture.html — интерактивная схема архитектуры Multi-LoRA картриджей.
- web_ui/cartridges/manifest.json — манифест установленных картриджей.
- 
outer.py — легковесный Zero-Dependency сервер на чистом Python (Standard Library: http.server, urllib, sqlite3). Обеспечивает работу веб-интерфейса, эмуляцию/проксирование OpenAI API (/v1/chat/completions) и управление устройствами через ADB/HTTP.
- start_web_ui.bat — 1-кликовый запуск для Windows.
- start_web_ui.sh — 1-кликовый запуск для Linux / macOS / Termux.

## Быстрый старт

### На Windows:
Дважды кликните на start_web_ui.bat. Автоматически поднимется сервер и откроется браузер по адресу http://localhost:8000.

### На Linux / macOS / Termux (Android):
`ash
chmod +x start_web_ui.sh
./start_web_ui.sh
`

### Прямой запуск через Python:
`ash
python router.py
`
После чего откройте в любом браузере: http://localhost:8000.

## Возможности веб-интерфейса
1. **Управление узлами:** Кнопки **▶️ Запустить**, **🔄 Ребут** и **🛑 Отключить** для каждого телефона кластера или всех сразу.
2. **Аппаратная телеметрия АКБ:** Отображение реального процента заряда, температуры SoC и статуса байпаса питания (0 мА).
3. **DeepSeek Harness & OpenAI Bridge:** Совместимый эндпоинт http://<IP>:8000/v1 для бенчмарков и агентов с ускорителем инференса <think>\n</think>\n.
