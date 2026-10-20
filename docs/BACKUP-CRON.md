# Бэкапы сайта: baseline + cron (ISPmanager)

> Проверенный baseline: `custom.cl480309.tar.gz` (141,8 МБ, ISPmanager Lite 6.152.2).
> Внутри: сайт `data/www/tochka-plavleniya.ru`, дамп БД `cl480309_1`, почта, PHP.
> Архив читается: 9 939 записей; дамп БД — обычный SQL (MariaDB 10.5).
> **В архиве есть секреты** (`wp-config.php`, ключ SSL, пароли БД) — не коммитить,
> не выкладывать в публичные папки, хранить с правами 600.

## 0. Что уже известно

| Параметр | Значение |
|---|---|
| Сайт на сервере | `/var/www/cl480309/data/www/tochka-plavleniya.ru` (проверить в «Сайты») |
| База | `cl480309_1` |
| Пользователь БД | `cl480309_wp` |
| Хост БД | `localhost` |
| Префикс таблиц | `wp_` |
| Панель | ISPmanager Lite |

Пароль БД **не хранить в скрипте** — только в `~/.backup-my.cnf` с правами 600.

## 1. Сначала — встроенные «Резервные копии»

В панели открой **Резервные копии**:

- [ ] включены **файлы + базы данных**;
- [ ] ежедневное расписание;
- [ ] хранение **14 дней**;
- [ ] если есть — удалённое хранилище;
- [ ] создать ручную копию **сейчас** и скачать на компьютер.

Встроенный бэкап — первый слой. Cron ниже — второй, независимый.

## 2. Расписание (рекомендация)

| Что | Когда | Хранение |
|---|---|---|
| База данных | каждый день, **03:30** | 14 дней |
| Файлы сайта (полный tar) | воскресенье, **04:00** | 8 недель |
| Месячный архив | 1-е число, **04:30** | 6 месяцев |
| Встроенный бэкап панели | ежедневно | 14 дней |
| Скачивание на компьютер | раз в неделю/месяц | — |

Почему так: БД меняется каждый день и весит мало; файлы (темы, плагины, загрузки)
меняются реже, полный tar тяжёлый; месяц — точка возврата.

## 3. Каталоги и секрет

```bash
mkdir -p ~/backups/tochka/{db,files,weekly,monthly,logs}
mkdir -p ~/scripts
chmod 700 ~/backups ~/scripts
```

`~/.backup-my.cnf`:

```ini
[client]
user=cl480309_wp
password=ПАРОЛЬ_ИЗ_WP_CONFIG
host=localhost
```

```bash
chmod 600 ~/.backup-my.cnf
```

## 4. Скрипт `~/scripts/backup-site.sh`

```bash
#!/usr/bin/env bash
set -euo pipefail

SITE_DIR="/var/www/cl480309/data/www/tochka-plavleniya.ru"
BACKUP_ROOT="$HOME/backups/tochka"
DB_NAME="cl480309_1"
DB_CNF="$HOME/.backup-my.cnf"
MODE="${1:-db}"
STAMP="$(date +%Y-%m-%d_%H-%M)"

mkdir -p "$BACKUP_ROOT"/{db,files,weekly,monthly,logs}

backup_db() {
  mysqldump --defaults-extra-file="$DB_CNF" \
    --single-transaction --quick --lock-tables=false \
    --default-character-set=utf8mb4 "$DB_NAME" \
    | gzip -9 > "$BACKUP_ROOT/db/db-$STAMP.sql.gz"
  sha256sum "$BACKUP_ROOT/db/db-$STAMP.sql.gz" \
    > "$BACKUP_ROOT/logs/db-$STAMP.sha256"
  find "$BACKUP_ROOT/db" -type f -mtime +14 -delete
}

backup_files() {
  local dest="$1"
  tar -czf "$dest/files-$STAMP.tar.gz" \
    -C "$SITE_DIR" \
    --exclude='wp-content/cache' \
    --exclude='wp-content/upgrade' \
    --exclude='.tmb' \
    --exclude='*.log' \
    .
  sha256sum "$dest/files-$STAMP.tar.gz" \
    > "$BACKUP_ROOT/logs/files-$STAMP.sha256"
}

case "$MODE" in
  db)
    backup_db
    ;;
  full)
    backup_db
    backup_files "$BACKUP_ROOT/files"
    find "$BACKUP_ROOT/files" -type f -mtime +14 -delete
    find "$BACKUP_ROOT/weekly" -type f -mtime +56 -delete
    cp "$BACKUP_ROOT/db/db-$STAMP.sql.gz" "$BACKUP_ROOT/weekly/" 2>/dev/null || true
    cp "$BACKUP_ROOT/files/files-$STAMP.tar.gz" "$BACKUP_ROOT/weekly/" 2>/dev/null || true
    ;;
  monthly)
    backup_db
    backup_files "$BACKUP_ROOT/monthly"
    find "$BACKUP_ROOT/monthly" -type f -mtime +186 -delete
    cp "$BACKUP_ROOT/db/db-$STAMP.sql.gz" "$BACKUP_ROOT/monthly/" 2>/dev/null || true
    ;;
  *)
    echo "Usage: $0 [db|full|monthly]" >&2
    exit 2
    ;;
esac

echo "[$(date '+%F %T')] $MODE OK: $STAMP" >> "$BACKUP_ROOT/logs/backup.log"
```

```bash
chmod 700 ~/scripts/backup-site.sh
```

## 5. Три задания в CRON

**Планировщик CRON → Создать задание**.

### 1. База — ежедневно
```
30 3 * * * /bin/bash /var/www/cl480309/scripts/backup-site.sh db >> /var/www/cl480309/backups/tochka/logs/cron-db.log 2>&1
```

### 2. Файлы — воскресенье
```
0 4 * * 0 /bin/bash /var/www/cl480309/scripts/backup-site.sh full >> /var/www/cl480309/backups/tochka/logs/cron-full.log 2>&1
```

### 3. Месячный архив — 1-е число
```
30 4 1 * * /bin/bash /var/www/cl480309/scripts/backup-site.sh monthly >> /var/www/cl480309/backups/tochka/logs/cron-monthly.log 2>&1
```

Если путь `~` другой — замени `/var/www/cl480309` на фактический домашний каталог.

## 6. Проверка

```bash
/var/www/cl480309/scripts/backup-site.sh db
ls -lh ~/backups/tochka/db ~/backups/tochka/files
gzip -t ~/backups/tochka/db/*.sql.gz
tar -tzf ~/backups/tochka/files/*.tar.gz | head
```

Если `mysqldump` не найден, замени на `mariadb-dump` (MariaDB 10.5).

## 7. Восстановление из baseline

Архив `custom.cl480309.tar.gz` — это **полный образ ISPmanager**, а не формат cron.
Для восстановления сайта:

1. Распакуй архив в отдельный каталог.
2. Возьми `data/www/tochka-plavleniya.ru` — файлы сайта.
3. Возьми `.system/db.mysql.cl480309_1` — SQL-дамп базы.
4. Импортируй дамп в тестовую БД, проверь главную/статью/админку.

Проверять восстановление **до** смены темы, не после.

## 8. Что не делать

- не хранить бэкапы в `public_html`;
- не писать пароль БД в cron/скрипт;
- не коммитить `custom.cl480309.tar.gz` (там ключи и пароли);
- не делать только БД без файлов;
- не удалять старые копии, пока новая не проверена;
- не менять тему без скачанной копии.

## 9. Чек-лист перед сменой темы

- [ ] baseline `custom.cl480309.tar.gz` скачан на компьютер;
- [ ] `tar -tzf` читается;
- [ ] встроенный бэкап создан;
- [ ] cron-задания добавлены;
- [ ] ручной запуск `db` и `full` прошёл;
- [ ] тестовое восстановление сделано;
- [ ] только после этого — смена темы.
