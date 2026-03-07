# Как исправить IP адреса подписчиков (Исправление 127.0.0.1)

Проблема заключается в том, что пользователи попадают в Documenso напрямую через порт 9000 или через прокси без передачи заголовков IP. Чтобы в Audit Log (журнале событий) отображались реальные IP адреса людей, подписывающих документ, нужно настроить **Nginx Reverse Proxy** перед Documenso.

### Шаг 0: Создание бекапа (ВАЖНО!)

Перед любыми действиями скачайте файл `backup_vps_before_changes.sh` на VPS и запустите:
```bash
chmod +x backup_vps_before_changes.sh
./backup_vps_before_changes.sh
```
Это сохранит текущие скрипты, базу данных и конфиги в `/root/backups/`.

---

### Шаг 1: Настройка Nginx на VPS

1. Загрузите файл `nginx_documenso_proxy.conf` из этой папки на сервер в `/etc/nginx/sites-available/documenso`.
2. Отредактируйте его, указав ваш домен (или IP сервер, если домена нет):
   ```nginx
   server_name ваш-домен.com; 
   ```
3. Активируйте конфиг:
   ```bash
   sudo ln -s /etc/nginx/sites-available/documenso /etc/nginx/sites-enabled/
   sudo nginx -t
   sudo systemctl reload nginx
   ```

### Шаг 2: Обновление ссылки в скрипте

После того как Nginx заработает, все запросы должны идти через стандартный HTTP порт (80) или HTTPS (443), а Nginx будет пересылать их в Documenso (порт 9000) **с правильными IP заголовками**.

Теперь нужно обновить файл `api/create_envelope_api_v2.php` на сервере:

Найдите строку:
```php
const DOCUMENSO_BASE_URL = 'http://72.62.114.139:9000';
```

Замените на ваш домен (без порта 9000):
```php
const DOCUMENSO_BASE_URL = 'http://documenso.matrang.com'; // Ваш домен из Шага 1
```

### Почему это работает?

- Ссылки на подписание (`/sign/...`) теперь будут вести на Nginx (порт 80).
- Nginx принимает соединение от Клиента (знает его реальный IP).
- Nginx передает запрос в Documenso, добавляя заголовок `X-Forwarded-For: <REAL_IP>`.
- Documenso читает этот заголовок и записывает РЕАЛЬНЫЙ IP в базу данных при подписании.
