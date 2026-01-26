# ✉️ НАСТРОЙКА SMTP ДЛЯ ОТПРАВКИ ПИСЕМ

## Что сделано

✅ Установлен PHPMailer через Composer  
✅ Создан файл `api/send_email.php` с функциями отправки  
✅ Создан файл `api/smtp_config.php` с настройками  
✅ Заменён `mail()` на SMTP во всех местах отправки  

## Что нужно сделать СРОЧНО

### 1️⃣ Создать email ящик на Hostinger

1. Войдите в hPanel Hostinger
2. Перейдите в раздел **Email** → **Email Accounts**
3. Создайте новый ящик: **noreply@matrang.com**
4. Установите пароль (запишите его!)

### 2️⃣ Настроить SMTP конфигурацию

Откройте файл `/api/smtp_config.php` на хостинге и **ЗАМЕНИТЕ**:

```php
'username' => 'noreply@matrang.com', // ваш созданный email
'password' => 'ВАШ_ПАРОЛЬ_ЗДЕСЬ',    // пароль от noreply@matrang.com
```

### 3️⃣ Проверить настройки

Файл `api/smtp_config.php` должен содержать:

```php
<?php
return [
    'host' => 'smtp.hostinger.com',
    'port' => 587,
    'encryption' => 'tls',
    'auth' => true,
    'username' => 'noreply@matrang.com',      // ← ЗАМЕНИТЕ
    'password' => 'YOUR_ACTUAL_PASSWORD',      // ← ЗАМЕНИТЕ
    'from_email' => 'noreply@matrang.com',
    'from_name' => 'GREAT LEGACY BULLY',
    'reply_to' => 'greatlegacybully@gmail.com'
];
```

## Как это работает

### Старый способ (НЕ РАБОТАЕТ - заблокирован):
```php
mail($to, $subject, $message, $headers);
```

### Новый способ (SMTP через PHPMailer):
```php
require_once 'api/send_email.php';

sendEmailSMTP(
    'buyer@example.com',           // Кому
    'Договор №DOG-2026-0001',      // Тема
    '<html>...</html>',            // HTML письмо
    [                               // Вложения
        ['path' => '/path/to/contract.pdf', 'name' => 'contract.pdf']
    ],
    'reply@example.com'            // Reply-To
);
```

## Функции отправки

### `sendEmailSMTP()` - простая отправка
```php
$attachments = [
    ['path' => '/full/path/to/file.pdf', 'name' => 'contract.pdf']
];

$success = sendEmailSMTP(
    'user@example.com',
    'Тема письма',
    '<p>HTML содержимое</p>',
    $attachments,
    'reply-to@example.com' // опционально
);
```

### `sendEmailSMTPWithCC()` - отправка с копиями
```php
$success = sendEmailSMTPWithCC(
    'main@example.com',           // Основной получатель
    ['copy1@example.com', 'copy2@example.com'], // Копии
    'Тема',
    '<p>HTML</p>',
    $attachments
);
```

## Где используется

1. **api/api.php** - отправка договоров:
   - Покупателю (с PDF)
   - Копия питомнику

2. Все места где был `mail()` теперь используют `sendEmailSMTP()`

## Логи

Все отправки логируются в `/data/mail.log`:
```
2026-01-26 10:30:15 - SMTP Mail sent to: buyer@example.com | Subject: Договор №DOG-2026-0001 | Status: SUCCESS
2026-01-26 10:30:16 - SMTP Mail sent to: kennel@example.com | Subject: Копия договора | Status: SUCCESS
```

## Проверка работы

После настройки:
1. Попробуйте отправить тестовый договор
2. Проверьте `/data/mail.log` - должно быть "Status: SUCCESS"
3. Проверьте почту получателя
4. Если ошибка - смотрите лог, там будет описание проблемы

## Возможные ошибки

### "SMTP Error: Could not authenticate"
- Неверный пароль в `smtp_config.php`
- Проверьте логин/пароль от noreply@matrang.com

### "SMTP Error: Could not connect"
- Проблемы с сетью хостинга
- Проверьте порт (587) и host (smtp.hostinger.com)

### "File not found" при вложении
- Неверный путь к PDF
- Проверьте что файл существует: `file_exists($path)`

## Безопасность

⚠️ **ВАЖНО**: Файл `smtp_config.php` содержит пароль!

Добавьте в `.gitignore`:
```
api/smtp_config.php
```

Или создайте `smtp_config.example.php` для Git, а реальный файл редактируйте только на сервере.

## Альтернативные настройки

Если порт 587 не работает, попробуйте:

```php
'port' => 465,
'encryption' => 'ssl',
```

Или без шифрования (не рекомендуется):
```php
'port' => 25,
'encryption' => '',
```
