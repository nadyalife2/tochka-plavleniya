---
name: solder-seo-planner
description: SEO planning and semantic optimization for tochka-plavleniya.ru: intent clustering, Rank Math snippet optimization, FAQ schema, and internal linking.
argument-hint: "[plan-seo|optimize-snippets|cluster-keywords] [topic]"
metadata:
  author: claudekit
  version: "1.1.0"
---

# Solder SEO Planner

Использовать для семантического анализа, проектирования перелинковки и сниппетов.
Обязательно прочитать [редакционную книгу](../../../gem-knowledge/MASTER_EDITORIAL_BOOK.md) (глава 6) и [инструкцию Агента 1](../../../gem-knowledge/AGENT_01_RESEARCH_AND_SEO.md).

Семантическая кластеризация, карта перелинковки, требования к метатегам и Answer First описаны в [`AGENT_01_RESEARCH_AND_SEO.md`](../../../gem-knowledge/AGENT_01_RESEARCH_AND_SEO.md).
Не создавать конфликтующих схем микроразметки. Поле `tchp_faq_items` передаётся массивом с ключами `q` и `a` (4–6 пар). Перелинковка планируется только по опубликованным страницам (для неопубликованных — отложенная связь без активного URL).