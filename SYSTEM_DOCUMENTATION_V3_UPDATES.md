
## 8. Обновления (Февраль 2026)

### Мультиязычные шаблоны (RU/EN)
- **Функционал:** В админ-панели можно загрузить два шаблона PDF (RU и EN).
- **Логика:** При выборе языка в генераторе, система использует соответствующий файл.
    - RU: `uploads/pdf_template.pdf` -> VPS: `template.pdf` (local fallback) / `pdf_template.pdf`
    - EN: `uploads/pdf_template_en.pdf` -> VPS: `pdf_template_en.pdf`
- **Проверка:** Переключатель "Версия: RU | EN" в интерфейсе управления.

### Финальное письмо (Email)
- **Скрипт:** `send_final_email.py`
- **Изменения:**
    - Добавлен текст на русском и английском языках.
    - **PDF файл теперь вкладывается (attach)** в письмо.
    - Ссылка на скачивание исправлена: `http://72.62.114.139/download_signed.php?id=...`
- **Расположение на VPS:** `/var/www/documenso-bridge/send_final_email.py`
- **Скрипт скачивания:** `/var/www/html/download_signed.php`

### Резервное копирование (Backup)
- **Создан Backup:** `/backups/backup_system_v2.tar.gz` на сервере VPS.
- **Содержимое:** Папки `/var/www/documenso-bridge` и `/var/www/html`.
- **Команда восстановления:** `tar -xzf /backups/backup_system_v2.tar.gz -C /`
