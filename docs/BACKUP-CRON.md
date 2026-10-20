# Бэкапы сайта: встроенные + cron (ISPmanager)

> Цель — перед сменой темы иметь **проверенную** копию файлов и базы, а дальше —
> ежедневные бэкапы с хранением вне сайта.
> Схема: **3-2-1** — 3 копии, 2 носителя, 1 вне хостинга.

## 1. Сначала — встроенные «Резервные копии»

В панели (похоже на ISPmanager) открой **Резервные копии** и проверь:

- [ ] включены **файлы + базы данных**;
- [ ] ежедневное расписание;
- [ ] хранение **7–14 дней** (минимум);
- [ ] желательно — удалённое хранилище (S3/Яндекс Диск), если хостинг даёт;
- [ ] есть ручная копия **до** смены темы.

Встроенный бэкап — основа. Cron ниже — **независимая копия**, чтобы не зависеть
от одной панели.

## 2. Ручная копия перед сменой темы

1. «Резервные копии» → **Создать копию** → файлы + БД.
2. Дождаться завершения.
3. Скачать архив на компьютер.
4. Проверить размер: он не должен быть 0 байт.
5. Только после этого менять тему.

## 3. Скрипт бэкапа

Создай каталоги **вне** `public_html`:

```bash
mkdir -p ~/backups/tochka/{db,files,weekly,monthly,logs}
mkdir -p ~/scripts
chmod 700 ~/backups ~/scripts
```

Секреты — в отдельный файл `~/.backup-my.cnf` (не в скрипт!):

```ini
[client]
user=DB_USER
password=DB_PASSWORD
host=localhost
```

```bash
chmod 600 ~/.backup-my.cnf
```

Скрипт `~/scripts/backup-site.sh`:

```bash
#!/usr/bin/env bash
set -euo pipefail

SITE_DIR="${SITE_DIR:-$HOME/www/tochka-plavleniya.ru}"
BACKUP_ROOT="${BACKUP_ROOT:-$HOME/backups/tochka}"
DB_NAME="${DB_NAME:?Укажи DB_NAME}"
DB_CNF="${DB_CNF:-$HOME/.backup-my.cnf}"

STAMP="$(date +%Y-%m-%d_%H-%M)"
DAY_OF_WEEK="$(date +%u)"   # 7 = воскресенье
DAY_OF_MONTH="$(date +%d)"

mkdir -p "$BACKUP_ROOT"/{db,files,weekly,monthly,logs}

# 1. База
mysqldump \
  --defaults-extra-file="$DB_CNF" \
  --single-transaction --quick --lock-tables=false \
  --default-character-set=utf8mb4 \
  "$DB_NAME" | gzip -9 > "$BACKUP_ROOT/db/db-$STAMP.sql.gz"

# 2. Файлы (без кэша и логов)
tar -czf "$BACKUP_ROOT/files/files-$STAMP.tar.gz" \
  -C "$SITE_DIR" \
  --exclude='wp-content/cache' \
  --exclude='wp-content/upgrade' \
  --exclude='*.log' \
  .

# 3. Контрольные суммы
sha256sum "$BACKUP_ROOT/db/db-$STAMP.sql.gz" \
          "$BACKUP_ROOT/files/files-$STAMP.tar.gz" \
  > "$BACKUP_ROOT/logs/sha-$STAMP.txt"

# 4. Недельная и месячная копии
if [ "$DAY_OF_WEEK" = "7" ]; then
  cp "$BACKUP_ROOT/db/db-$STAMP.sql.gz" "$BACKUP_ROOT/weekly/"
  cp "$BACKUP_ROOT/files/files-$STAMP.tar.gz" "$BACKUP_ROOT/weekly/"
fi
if [ "$DAY_OF_MONTH" = "01" ]; then
  cp "$BACKUP_ROOT/db/db-$STAMP.sql.gz" "$BACKUP_ROOT/monthly/"
  cp "$BACKUP_ROOT/files/files-$STAMP.tar.gz" "$BACKUP_ROOT/monthly/"
fi

# 5. Ротация
find "$BACKUP_ROOT/db" -type f -mtime +14 -delete
find "$BACKUP_ROOT/files" -type f -mtime +14 -delete
find "$BACKUP_ROOT/weekly" -type f -mtime +56 -delete
find "$BACKUP_ROOT/monthly" -type f -mtime +186 -delete

echo "[$(date '+%F %T')] backup OK: $STAMP" >> "$BACKUP_ROOT/logs/backup.log"
```

```bash
chmod 700 ~/scripts/backup-site.sh
```

## 4. Задание в «Планировщик CRON»

В панели: **Планировщик CRON → Создать задание**.

- Команда:
  ```bash
  /bin/bash /home/USER/scripts/backup-site.sh >> /home/USER/backups/tochka/logs/cron.log 2>&1
  ```
- Расписание: **каждый день, 03:30**.
- Пользователь: владелец сайта.
- Email/уведомления: включить, если панель умеет.

В cron-форме это обычно:

| Поле | Значение |
|---|---|
| Минута | 30 |
| Час | 3 |
| День | * |
| Месяц | * |
| День недели | * |

## 5. Проверка

1. Запусти скрипт вручную:
   ```bash
   SITE_DIR=/путь/к/сайту DB_NAME=имя_базы /bin/bash ~/scripts/backup-site.sh
   ```
2. Проверь архив:
   ```bash
   gzip -t ~/backups/tochka/db/*.sql.gz
   tar -tzf ~/backups/tochka/files/*.tar.gz | head
   ```
3. Убедись, что файлы не нулевые:
   ```bash
   ls -lh ~/backups/tochka/db ~/backups/tochka/files
   ```
4. Через сутки проверь `logs/backup.log`.

## 6. Восстановление (проверить заранее)

1. Создай тестовую БД.
2. Импортируй дамп:
   ```bash
   gunzip -c db-*.sql.gz | mysql -u USER -p TEST_DB
   ```
3. Распакуй файлы в тестовый каталог:
   ```bash
   mkdir -p ~/restore-test && tar -xzf files-*.tar.gz -C ~/restore-test
   ```
4. Подними тестовый сайт/поддомен и проверь главную, статью, админку.

**Бэкап без проверки восстановления — не бэкап.**

## 7. Удалённая копия

Если есть `rclone`:

```bash
rclone copy "$HOME/backups/tochka" remote:backups/tochka-plavleniya --max-age 25h
```

Или скачивай архивы раз в неделю через FTP/панель. Главное — **не хранить
единственную копию на том же диске**, что и сайт.

## 8. Что не делать

- не хранить бэкапы в `public_html`;
- не писать пароль БД в cron-команду или скрипт;
- не делать только `mysqldump` без файлов (тема/плагины/загрузки тоже нужны);
- не удалять старые копии, пока не проверил новую;
- не менять тему без ручной копии и скачивания на компьютер;
- не игнорировать размер архива (0 байт = ошибка).

## 9. Чек-лист перед сменой темы

- [ ] встроенный бэкап создан;
- [ ] файлы + БД в копии;
- [ ] архив скачан на компьютер;
- [ ] `gzip -t` и `tar -tzf` проходят;
- [ ] cron-задание создано;
- [ ] тестовое восстановление хотя бы раз сделано;
- [ ] только после этого — смена темы.
