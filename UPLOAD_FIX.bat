@echo off
echo ====================================
echo ЗАГРУЗКА ContractService.php НА ХОСТИНГ
echo ====================================
echo.
echo ИНСТРУКЦИЯ:
echo 1. Откройте https://hpanel.hostinger.com
echo 2. Войдите в аккаунт
echo 3. Нажмите "File Manager" для домена matrang.com
echo 4. Перейдите в папку: public_html/api/
echo 5. Найдите файл ContractService.php
echo 6. Нажмите правой кнопкой → Delete (удалить)
echo 7. Нажмите "Upload Files" (загрузить файлы)
echo 8. Выберите файл: E:\pitbull\public_html\api\ContractService.php
echo 9. После загрузки откройте: https://matrang.com/api/clear_cache.php
echo 10. Проверьте что оба файла показывают "NEW VERSION"
echo.
echo ====================================
echo ФАЙЛ ДЛЯ ЗАГРУЗКИ:
echo E:\pitbull\public_html\api\ContractService.php
echo ====================================
echo.
pause
start https://hpanel.hostinger.com
explorer /select,"E:\pitbull\public_html\api\ContractService.php"
