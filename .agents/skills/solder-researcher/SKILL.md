---
name: solder-researcher
description: Research and structure topics for tochka-plavleniya.ru: analyze beginner intent, uncover competitor gaps, identify safety risks, design H2/H3 article outline, and generate FAQ ideas.
argument-hint: "[research|outline|analyze-intent] [topic or slug]"
metadata:
  author: claudekit
  version: "1.1.0"
---

# Solder Researcher

Использовать для исследования тем, проверки физико-химических фактов и проектирования структуры статьи.
Обязательно прочитать [редакционную книгу](../../../gem-knowledge/MASTER_EDITORIAL_BOOK.md) и [инструкцию Агента 1](../../../gem-knowledge/AGENT_01_RESEARCH_AND_SEO.md).

Роль, пошаговый алгоритм исследования, 13 пунктов брифа и стоп-условия зафиксированы в [`AGENT_01_RESEARCH_AND_SEO.md`](../../../gem-knowledge/AGENT_01_RESEARCH_AND_SEO.md).
Не дублировать реестр сплавов и температуры в навыке — единственным источником истины является `theme4/data/materials-registry.php`. Блок FAQ проектируется строго из 4–6 вопросов с ключами `q` и `a`. Для тем без пайки (инструмент, оптика, верстак) температурный режим отмечается как «Не применимо». Результат работы переводит задачу в статус `ТЗ подготовлено`.