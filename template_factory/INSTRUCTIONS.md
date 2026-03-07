# Matrang Universal Project Template

Этот набор инструментов позволяет создать портативный архив текущего проекта и развернуть его на любом новом VPS.

## 1. Как создать шаблон (Template)

1. Установите Python, если его нет (на Windows).
2. Зайдите в папку `template_factory`.
3. Запустите скрипт сборки:
   ```bash
   python prepare_package.py
   ```
   *Что делает скрипт:*
   * Копирует текущий проект (API + Frontend) в папку `build_output`.
   * **Очищает** лишнее (логи, git, node_modules).
   * **Параметризует** код: находит IP `72.62...` и заменяет на `{{SERVER_IP}}`, домены на `{{DOMAIN}}`.
   * Создает архив `matrang_template.zip`.

4. **ВАЖНО: База данных**
   Скрипт не может сам выкачать базу с удаленного VPS без пароля.
   Зайдите на *исходный* VPS и выполните команду для эспорта "чистой" структуры:
   ```bash
   docker exec -t documenso-postgres pg_dump -U documenso --schema-only documenso > schema.sql
   ```
   Скачайте этот `schema.sql` и добавьте его внутрь `matrang_template.zip` в папку `database/` (создайте её).

---

## 2. Как развернуть на новом сервере

1. Купите новый VPS (Ubuntu 22.04+).
2. Загрузите архив `matrang_template.zip` на сервер.
3. Распакуйте:
   ```bash
   unzip matrang_template.zip -d installer
   cd installer
   ```
4. Запустите автоматическую установку:
   ```bash
   chmod +x install.sh
   ./install.sh
   ```
   
   Скрипт спросит вас:
   * Новый домен
   * Новый IP
   * Пароль для базы данных
   
   И сам настроит Nginx, Docker и файлы проекта.
