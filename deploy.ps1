# Скрипт автоматического деплоя на matrang.com
# Использование: .\deploy.ps1

Write-Host "🚀 Начинаем деплой на matrang.com..." -ForegroundColor Green

# Проверка наличия папки dist
if (-not (Test-Path "dist")) {
    Write-Host "❌ Папка dist не найдена! Сначала выполните: npm run build" -ForegroundColor Red
    exit 1
}

Write-Host "✅ Папка dist найдена" -ForegroundColor Green

# Показываем информацию о файлах
Write-Host "`n📦 Файлы для загрузки:" -ForegroundColor Cyan
Get-ChildItem -Path "dist" -Recurse | Select-Object FullName, Length | Format-Table -AutoSize

Write-Host "`n📋 Инструкция по загрузке на сервер:" -ForegroundColor Yellow
Write-Host "============================================" -ForegroundColor Yellow
Write-Host ""
Write-Host "ВАРИАНТ 1: Через cPanel File Manager (рекомендуется)" -ForegroundColor White
Write-Host "1. Откройте: https://hpanel.hostinger.com" -ForegroundColor Gray
Write-Host "2. Выберите домен matrang.com" -ForegroundColor Gray
Write-Host "3. File Manager → public_html" -ForegroundColor Gray
Write-Host "4. Удалите старые файлы assets/" -ForegroundColor Gray
Write-Host "5. Загрузите ВСЕ файлы из папки:" -ForegroundColor Gray
Write-Host "   $PWD\dist" -ForegroundColor Cyan
Write-Host ""
Write-Host "ВАРИАНТ 2: Через FTP" -ForegroundColor White
Write-Host "1. Откройте FileZilla или WinSCP" -ForegroundColor Gray
Write-Host "2. Подключитесь к серверу (данные в Hostinger cPanel→FTP)" -ForegroundColor Gray
Write-Host "3. Перейдите в /public_html" -ForegroundColor Gray
Write-Host "4. Загрузите файлы из:" -ForegroundColor Gray
Write-Host "   $PWD\dist" -ForegroundColor Cyan
Write-Host ""
Write-Host "⚠️  ВАЖНО: Загружайте СОДЕРЖИМОЕ папки dist, не саму папку!" -ForegroundColor Yellow
Write-Host "============================================" -ForegroundColor Yellow

# Открываем папку dist в проводнике для удобства
Write-Host "`n📂 Открываю папку dist в проводнике..." -ForegroundColor Green
Start-Process "explorer.exe" -ArgumentList "$PWD\dist"

# Открываем браузер с cPanel
Write-Host "🌐 Открываю Hostinger cPanel..." -ForegroundColor Green
Start-Process "https://hpanel.hostinger.com"

Write-Host "`n✅ Готово! Загрузите файлы вручную через File Manager." -ForegroundColor Green
Write-Host "После загрузки проверьте: https://matrang.com/admin" -ForegroundColor Cyan
