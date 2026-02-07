# Documenso Bridge System Documentation (V2)

**Дата создания:** 7 февраля 2026 г.
**Версия:** 2.0 (Real-time Audit Trail & Email)

## 1. Обзор Системы
Система предназначена для автоматического управления жизненным циклом документов в Documenso (Self-Hosted), включая:
1.  Создание конвертов через API.
2.  Мгновенную генерацию сертификата подписи (Audit Trail) после завершения.
3.  Отправку финального письма всем участникам со ссылкой на скачивание.

### Архитектура
*   **Backend:** PHP (API), Python (Backend Logic).
*   **Database:** PostgreSQL (Documenso DB).
*   **Server:** VPS (Debian) @ `72.62.114.139`.
*   **Real-time:** PostgreSQL `NOTIFY/LISTEN` + Python Daemon (`systemd`).

---

## 2. Рабочий Процесс (Workflow)

1.  **Подписание:** Пользователь подписывает документ через интерфейс Documenso.
2.  **Триггер БД:** Как только статус конверта меняется на `COMPLETED`, срабатывает SQL-триггер `auto_complete_envelope`.
3.  **Уведомление:** Триггер отправляет сигнал `pg_notify('envelope_completed', envelope_id)`.
4.  **Watcher Service:**
    *   Служба `documenso-audit-watcher.service` (Python) постоянно "слушает" этот канал.
    *   При получении сигнала ID конверта передается в обработчик.
5.  **Генерация Сертификата (`add_audit_trail.py`):**
    *   Скрипт извлекает данные подписантов из БД.
    *   Генерирует PDF-страницу "Signing Certificate".
    *   **Особенности:**
        *   Поддержка кириллицы (шрифт `LiberationSans`).
        *   Отрисовка реальной подписи (картинки) или красивого текста.
        *   Генерация `Transaction ID` и `Signature ID` (Hash).
    *   Склеивает сертификат с оригинальным документом и обновляет БД.
6.  **Уведомление (Email):**
    *   Сразу после генерации запускается `send_final_email.py`.
    *   Отправляет письмо с прямой ссылкой на скачивание `download_signed.php`.

---

## 3. Компоненты и Файлы

### Локальные (Repo) -> Удаленные (`/var/www/documenso-bridge/`)

| Файл | Описание |
| :--- | :--- |
| `audit_trail_watcher.py` | Демон, слушающий PostgreSQL. Управляет процессом. |
| `add_audit_trail.py` | Логика генерации PDF (ReportLab). |
| `send_final_email.py` | Отправка писем через SMTP. |
| `download_signed.php` | Скрипт прямой отдачи PDF из БД (лежит также в `/var/www/html/`). |
| `smtp_config.php` | Конфигурация SMTP (Hostinger/Gmail). |
| `documenso-audit-watcher.service` | Systemd юнит файл (в `/etc/systemd/system/`). |

---

## 4. Развертывание (Deployment)

### Доступы
*   **Host:** `72.62.114.139`
*   **User:** `root`
*   **Git:** `https://github.com/spillover21/matrang.com.git`
*   **Path:** `/var/www/documenso-bridge`
*   **DB:** User `documenso`, DB `documenso` (в Docker контейнере `documenso-postgres`).

### Инструкция по обновлению
Если вы внесли изменения в Python скрипты:

1.  **Загрузка файлов на сервер:**
    Можно использовать вспомогательный скрипт `deploy_email.py` или `scp` вручную.
    ```powershell
    python deploy_email.py
    ```
    *Или вручную:*
    ```bash
    scp add_audit_trail.py audit_trail_watcher.py send_final_email.py root@72.62.114.139:/var/www/documenso-bridge/
    ```

2.  **Перезапуск сервиса:**
    Так как watcher загружает модули в память, после обновления кода его нужно перезапустить.
    ```bash
    ssh root@72.62.114.139 "systemctl restart documenso-audit-watcher"
    ```

3.  **Проверка логов:**
    ```bash
    ssh root@72.62.114.139 "journalctl -u documenso-audit-watcher -f"
    ```

---

## 5. Известные ошибки и решения (History)

### Проблема: "Черные прямоугольники" вместо текста (Tofu)
*   **Причина:** Стандартный шрифт Helvetica в PDF не поддерживает кириллицу.
*   **Решение:** Внедрен шрифт `LiberationSans` (путь `/usr/share/fonts/truetype/liberation/`).
*   **Код:** См. `add_audit_trail.py` -> `pdfmetrics.registerFont`.

### Проблема: 5-минутная задержка сертификата
*   **Причина:** Ранее использовался CRON, запускающийся раз в 5 минут.
*   **Решение:** Переход на Event-Driven архитектуру (Postgres LISTEN/NOTIFY). Генерация теперь занимает ~2 секунды.

### Проблема: Ошибка `column s.signatureImageId does not exist`
*   **Причина:** Неверное имя колонки в SQL запросе. В базе Documenso поле называется `signatureImageAsBase64`.
*   **Решение:** SQL запрос в `add_audit_trail.py` исправлен. Добавлена логика декодирования Base64 изображения.

### Проблема: Ошибка SMTP `451 Ratelimit exceeded`
*   **Причина:** Лимиты хостинга Hostinger на отправку писем.
*   **Решение:** 
    1. Использовать `smtp_config.php` для настройки (можно сменить на Gmail App Password).
    2. Скрипт `send_final_email.py` логирует ошибку, но не роняет сервис. Письма просто не уходят, пока лимит не спадет.

---

## 6. Как тестировать

1.  **Подписать документ:** Пройти флоу подписания в браузере.
2.  **Имитация (без подписания):**
    Отправить сигнал вручную из консоли сервера:
    ```bash
    docker exec -i documenso-postgres psql -U documenso -d documenso -c "NOTIFY envelope_completed, 'ВАШ_ENVELOPE_ID';"
    ```
3.  **Проверка:**
    *   Смотреть логи: `journalctl -u documenso-audit-watcher -f`
    *   Скачать PDF: `http://72.62.114.139/download_signed.php?id=ВАШ_ENVELOPE_ID`

---

## 7. Git & Restore Point
Текущее состояние зафиксировано в ветке `main`.
Для отката использовать: `git reset --hard <commit-hash>`

**Важные файлы для бэкапа:**
* `add_audit_trail.py`
* `audit_trail_watcher.py`
* `send_final_email.py`
* `documenso-audit-watcher.service`
