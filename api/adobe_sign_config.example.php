<?php
/**
 * ПРИМЕР конфигурации Adobe Sign API
 * 
 * Это файл-пример. Скопируйте его как adobe_sign_config.php
 * и заполните своими данными.
 * 
 * НЕ КОММИТЬТЕ adobe_sign_config.php с реальными ключами в Git!
 */

return [
    // Adobe Sign API endpoint
    // Выберите регион:
    // EU (Европа): https://api.eu1.adobesign.com/api/rest/v6
    // US (США): https://api.na1.adobesign.com/api/rest/v6
    // APAC (Азия): https://api.ap1.adobesign.com/api/rest/v6
    'base_url' => 'https://api.eu1.adobesign.com/api/rest/v6',
    
    // OAuth credentials из Adobe Developer Console
    'client_id' => 'CBJCHBCAABAAxxxxxxxxxxxxxxxxxxxxxx',
    'client_secret' => 'xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx',
    
    // Access Token (получается через OAuth flow)
    // Пример получения:
    // curl -X POST https://api.eu1.adobesign.com/oauth/v2/token \
    //   -d "grant_type=authorization_code" \
    //   -d "client_id=YOUR_CLIENT_ID" \
    //   -d "client_secret=YOUR_CLIENT_SECRET" \
    //   -d "code=AUTHORIZATION_CODE" \
    //   -d "redirect_uri=YOUR_REDIRECT_URI"
    'access_token' => '3AAABLblqZhBxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
    
    // Refresh Token для автоматического обновления Access Token
    // Access token живет 1 час, refresh token - бессрочно (пока не отозван)
    'refresh_token' => '3AAABLblqZhCxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx',
    
    // Webhook URL для получения уведомлений от Adobe Sign
    // Должен быть доступен из интернета через HTTPS
    'webhook_url' => 'https://matrang.com/api/api.php?action=adobesignwebhook',
    
    // Email отправителя (должен быть подтвержден в Adobe Sign)
    'sender_email' => 'noreply@matrang.com',
    
    // Включить/выключить Adobe Sign
    // true = использовать Adobe Sign API
    // false = использовать email-рассылку без электронной подписи
    'enabled' => false
];

/*
 * ИНСТРУКЦИЯ ПО ПОЛУЧЕНИЮ ТОКЕНОВ:
 * 
 * 1. Зарегистрируйтесь на https://acrobat.adobe.com/sign.html
 * 
 * 2. Создайте приложение в Adobe Developer Console:
 *    https://developer.adobe.com/console
 *    - Create new project
 *    - Add API → Adobe Acrobat Sign API
 *    - OAuth Server-to-Server
 * 
 * 3. Получите Client ID и Client Secret
 * 
 * 4. Получите Authorization Code:
 *    Откройте в браузере (замените YOUR_CLIENT_ID):
 *    https://secure.eu1.adobesign.com/public/oauth/v2?redirect_uri=https://matrang.com/oauth/callback&response_type=code&client_id=YOUR_CLIENT_ID&scope=user_read:account+user_write:account+agreement_read:account+agreement_write:account+agreement_send:account
 * 
 * 5. Обменяйте код на токены:
 *    curl -X POST https://api.eu1.adobesign.com/oauth/v2/token \
 *      -H "Content-Type: application/x-www-form-urlencoded" \
 *      -d "grant_type=authorization_code" \
 *      -d "client_id=YOUR_CLIENT_ID" \
 *      -d "client_secret=YOUR_CLIENT_SECRET" \
 *      -d "code=AUTHORIZATION_CODE" \
 *      -d "redirect_uri=https://matrang.com/oauth/callback"
 * 
 * 6. Сохраните access_token и refresh_token в этот файл
 * 
 * 7. Включите Adobe Sign: 'enabled' => true
 * 
 * АВТОМАТИЧЕСКОЕ ОБНОВЛЕНИЕ ТОКЕНА:
 * 
 * Access token действует 1 час. Для обновления:
 * curl -X POST https://api.eu1.adobesign.com/oauth/v2/refresh \
 *   -H "Content-Type: application/x-www-form-urlencoded" \
 *   -d "grant_type=refresh_token" \
 *   -d "client_id=YOUR_CLIENT_ID" \
 *   -d "client_secret=YOUR_CLIENT_SECRET" \
 *   -d "refresh_token=YOUR_REFRESH_TOKEN"
 * 
 * Рекомендуется добавить автоматическое обновление в api.php
 * 
 * НАСТРОЙКА WEBHOOK:
 * 
 * curl -X POST https://api.eu1.adobesign.com/api/rest/v6/webhooks \
 *   -H "Authorization: Bearer YOUR_ACCESS_TOKEN" \
 *   -H "Content-Type: application/json" \
 *   -d '{
 *     "name": "Matrang Contract Notifications",
 *     "scope": "ACCOUNT",
 *     "state": "ACTIVE",
 *     "webhookSubscriptionEvents": ["AGREEMENT_WORKFLOW_COMPLETED"],
 *     "webhookUrlInfo": {
 *       "url": "https://matrang.com/api/api.php?action=adobesignwebhook"
 *     }
 *   }'
 */
