// РАБОЧИЙ Capacity Parser для VPS с Puppeteer
// Копия логики из capacity-parser-app/server.js НО через Puppeteer вместо axios

require('dotenv').config();
const express = require('express');
const puppeteer = require('puppeteer-core');
const chromium = require('@sparticuz/chromium');
const dbPool = require('./db.js');

const app = express();
const PORT = process.env.PORT || 3003;

app.use(express.json());

let browser;
const delay = (ms) => new Promise(resolve => setTimeout(resolve, ms));

// Инициализация браузера
async function initBrowser() {
    if (!browser || !browser.isConnected()) {
        console.log('🚀 Initializing browser...');
        browser = await puppeteer.launch({
            args: chromium.args,
            defaultViewport: chromium.defaultViewport,
            executablePath: await chromium.executablePath(),
            headless: chromium.headless,
            ignoreHTTPSErrors: true
        });
        console.log('✅ Browser initialized');
    }
    return browser;
}

// Установка cookie
async function setCookieOnPage(page, cookieString) {
    const cookies = cookieString.split(';').map(c => {
        const [name, value] = c.trim().split('=');
        return {
            name: name.trim(),
            value: value.trim(),
            domain: 'clean.holidayclub.fi',
            path: '/',
            httpOnly: true,
            secure: true
        };
    });
    
    for (const cookie of cookies) {
        await page.setCookie(cookie);
    }
}

// Конвертация даты YYYY-MM-DD → DD.MM.YYYY
function formatDateForForm(isoDate) {
    if (!isoDate) return '';
    const match = isoDate.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (!match) return isoDate;
    const [, year, month, day] = match;
    return `${day}.${month}.${year}`;
}

// Конвертация даты DD.MM.YYYY → YYYY-MM-DD
function convertDate(dateStr) {
    if (!dateStr) return null;
    const parts = dateStr.split('.');
    if (parts.length !== 3) return null;
    const day = parts[0].padStart(2, '0');
    const month = parts[1].padStart(2, '0');
    const year = parts[2];
    return `${year}-${month}-${day}`;
}

function parseNumber(str) {
    const num = parseInt(str, 10);
    return isNaN(num) ? 0 : num;
}

// АВТОЛОГИН
async function loginToCapacityAnalysis(username, password) {
    console.log(`🔐 Logging in as: ${username} (FRESH LOGIN)`);
    
    const browser = await initBrowser();
    const page = await browser.newPage();
    
    try {
        // 1. Идём на страницу HousekeepingHotels
        await page.goto('https://clean.holidayclub.fi/HousekeepingHotels', { 
            waitUntil: 'networkidle2', 
            timeout: 30000 
        });
        
        await delay(2000);
        
        // 2. СНАЧАЛА ВЫБИРАЕМ ОТЕЛЬ (Holiday Club Katinkulta - 110)
        console.log('🏨 Выбираем отель: Holiday Club Katinkulta (110)');
        await page.select('select[name="HoId"]', '110');
        await delay(1000);
        
        // 3. Вводим логин и пароль
        const usernameSelector = '#txtUsername, #username, input[type="text"]';
        await page.waitForSelector(usernameSelector, { timeout: 10000 });
        
        await page.type(usernameSelector, username);
        await page.type('#txtPassword, #password, input[type="password"]', password);
        await page.click('#btnLogin, button[type="submit"]');
        
        // 4. После логина автоматически переходим на страницу с выбором объекта
        await page.waitForNavigation({ waitUntil: 'networkidle2' });
        
        const afterLoginUrl = page.url();
        const afterLoginTitle = await page.title();
        console.log(`📍 После логина - URL: ${afterLoginUrl}`);
        console.log(`📍 После логина - Title: ${afterLoginTitle}`);
        
        // Ждём появления страницы с объектами
        await delay(2000);
        
        // ВАЖНО: Берём cookies ПОСЛЕ того как попали на страницу с объектами!
        const cookies = await page.cookies();
        const cookieString = cookies
            .filter(c => ['ASP.NET_SessionId', 'ApplicationGatewayAffinity', 'ApplicationGatewayAffinityCORS'].includes(c.name))
            .map(c => `${c.name}=${c.value}`)
            .join('; ');
        
        await page.close();
        
        return {
            success: true,
            message: 'Login successful',
            cookie: cookieString
        };
        
    } catch (error) {
        await page.close();
        return {
            success: false,
            error: error.message
        };
    }
}

// ПАРСИНГ HOTELS
async function parseHotels(cookie, startDate, endDate) {
    console.log('\n📥 === ПАРСИНГ HOTELS ===');
    const allRecords = [];
    
    const browser = await initBrowser();
    const page = await browser.newPage();
    await setCookieOnPage(page, cookie);
    
    try {
        // 1. Загружаем /HousekeepingHotels - после логина должны быть на странице с выбором объекта
        console.log('📄 Загрузка /HousekeepingHotels...');
        await page.goto('https://clean.holidayclub.fi/HousekeepingHotels', {
            waitUntil: 'networkidle2',
            timeout: 30000
        });
        
        await delay(2000);
        
        // 2. Парсим доступные объекты (Huoneet, HC Villas 1, HC Villas 2, HC Villas KKL1)
        // Ищем все селекты на странице
        const objectsInfo = await page.evaluate(() => {
            const selects = Array.from(document.querySelectorAll('select'));
            const result = {
                title: document.title,
                url: window.location.href,
                selectsCount: selects.length,
                selects: selects.map((s, i) => ({
                    index: i,
                    id: s.id,
                    name: s.name,
                    optionsCount: s.options.length,
                    options: Array.from(s.options).map(opt => ({
                        value: opt.value,
                        text: opt.textContent.trim()
                    }))
                }))
            };
            return result;
        });
        
        console.log(`🔍 Страница: ${objectsInfo.title}`);
        console.log(`🔍 URL: ${objectsInfo.url}`);
        console.log(`🔍 Найдено селектов: ${objectsInfo.selectsCount}`);
        
        // DEBUG: Показываем ВСЕ селекты
        objectsInfo.selects.forEach((s, i) => {
            console.log(`  Селект ${i}: name="${s.name}" id="${s.id}" (${s.optionsCount} options)`);
            if (s.options.length <= 5) {
                console.log(`    Опции:`, JSON.stringify(s.options, null, 6));
            }
        });
        
        // Ищем селект с объектами (не HoId!)
        let objectSelect = null;
        for (const sel of objectsInfo.selects) {
            if (sel.name !== 'HoId') {
                objectSelect = sel;
                break;
            }
        }
        
        if (!objectSelect) {
            console.log('⚠️ Селект с объектами не найден! Выбираем отель Holiday Club Katinkulta (110)...');
            
            // Используем FORM SUBMIT вместо page.select (для ASP.NET Bootstrap selectpicker)
            await page.evaluate(() => {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '/HousekeepingHotels';
                
                const input = document.createElement('input');
                input.name = 'HoId';
                input.value = '110';
                form.appendChild(input);
                
                document.body.appendChild(form);
                form.submit();
            });
            
            // Ждём navigation после submit
            await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 });
            await delay(2000);
            
            // Проверяем появился ли второй селект
            const afterSelect = await page.evaluate(() => {
                const selects = Array.from(document.querySelectorAll('select'));
                return {
                    selectsCount: selects.length,
                    selects: selects.map(s => ({
                        name: s.name,
                        id: s.id,
                        optionsCount: s.options.length,
                        options: Array.from(s.options).slice(0, 5).map(opt => ({
                            value: opt.value,
                            text: opt.textContent.trim()
                        }))
                    }))
                };
            });
            
            console.log(`🔍 После выбора отеля: найдено ${afterSelect.selectsCount} селектов`);
            
            // Ищем селект объектов заново
            for (const sel of afterSelect.selects) {
                if (sel.name !== 'HoId') {
                    objectSelect = sel;
                    break;
                }
            }
            
            if (!objectSelect) {
                console.log('❌ Селект с объектами НЕ появился даже после выбора отеля!');
                console.log('❌ Все селекты:', JSON.stringify(afterSelect.selects, null, 2));
                return [];
            }
            
            console.log(`✅ После выбора отеля появился селект: name="${objectSelect.name}" (${objectSelect.optionsCount} options)`);
        }
        
        console.log(`✅ Найден селект объектов: name="${objectSelect.name}" (${objectSelect.optionsCount} options)`);
        
        // Фильтруем объекты (пропускаем KKGP)
        const capacityTypes = objectSelect.options.filter(opt => {
            const text = opt.text;
            return opt.value && text && 
                   text !== 'Choose' && 
                   text.indexOf('HC Villas KKGP') === -1;
        });
        
        console.log(`✅ Найдено объектов для парсинга: ${capacityTypes.length}`);
        
        // 3. Для каждого объекта (Huoneet, HC Villas 1, HC Villas 2, HC Villas KKL1)
        for (let i = 0; i < capacityTypes.length; i++) {
            const capType = capacityTypes[i];
            console.log(`\n📂 [${i + 1}/${capacityTypes.length}] ${capType.text} (Value: ${capType.value})`);
            
            // 3.1 Возвращаемся на главную страницу если не первая итерация
            if (i > 0) {
                await page.goto('https://clean.holidayclub.fi/HousekeepingHotels', {
                    waitUntil: 'networkidle2',
                    timeout: 30000
                });
                await delay(1500);
            }
            
            // 3.2 Выбираем объект из селекта
            await page.evaluate((value) => {
                const selects = Array.from(document.querySelectorAll('select'));
                // Находим НЕ HoId селект
                const objectSelect = selects.find(s => s.name !== 'HoId');
                if (objectSelect) {
                    objectSelect.value = value;
                    objectSelect.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }, capType.value);
            
            console.log(`  ├─ Объект выбран: ${capType.text}`);
            await delay(1000);
            
            // 3.3 Нажимаем кнопку "Kapasiteettianalyysi"
            const buttonClicked = await page.evaluate(() => {
                const buttons = Array.from(document.querySelectorAll('button, a'));
                const kapButton = buttons.find(b => 
                    b.textContent.includes('Kapasiteettianalyysi') ||
                    b.textContent.includes('Capacity') ||
                    b.href && b.href.includes('CapacityAnalysis')
                );
                if (kapButton) {
                    kapButton.click();
                    return true;
                }
                return false;
            });
            
            if (!buttonClicked) {
                console.log('  ├─ ❌ Кнопка Kapasiteettianalyysi не найдена!');
                continue;
            }
            
            console.log(`  ├─ Нажата кнопка Kapasiteettianalyysi`);
            
            // 3.4 Ждём перехода на /Home/CapacityAnalysis
            await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 });
            await delay(2000);
            
            // 3.5 Парсим типы комнат из #capacitySelect
            const roomTypes = await page.evaluate(() => {
                const select = document.querySelector('#capacitySelect');
                if (!select) return [];
                
                return Array.from(select.options)
                    .filter(opt => {
                        const value = opt.value;
                        const text = opt.textContent.trim();
                        return value && value !== '0' && text && 
                               text !== 'Choose' && text !== '<all>';
                    })
                    .map(opt => ({
                        value: opt.value,
                        text: opt.textContent.trim()
                    }));
            });
            
            console.log(`  ├─ Найдено типов комнат: ${roomTypes.length}`);
            
            // 3.5 Для каждого типа комнаты
            for (let j = 0; j < roomTypes.length; j++) {
                const roomType = roomTypes[j];
                
                try {
                    const formattedStartDate = formatDateForForm(startDate);
                    const formattedEndDate = formatDateForForm(endDate);
                    
                    console.log(`  │  ├─ ${roomType.text}: ${formattedStartDate} - ${formattedEndDate}`);
                    
                    // 3.6 Заполняем даты и выбираем тип комнаты, нажимаем OK
                    await page.evaluate((sDate, eDate, caId) => {
                        // Находим поля дат (Alkupäivä и Loppupäivä)
                        const inputs = Array.from(document.querySelectorAll('input[type="text"]'));
                        if (inputs.length >= 2) {
                            inputs[0].value = sDate; // Alkupäivä (начало)
                            inputs[1].value = eDate; // Loppupäivä (окончание)
                        }
                        
                        // Выбираем тип комнаты из #capacitySelect
                        const select = document.querySelector('#capacitySelect');
                        if (select) {
                            select.value = caId;
                        }
                        
                        // Ищем и нажимаем кнопку OK
                        const buttons = Array.from(document.querySelectorAll('button, input[type="submit"]'));
                        const okButton = buttons.find(btn => 
                            btn.textContent.includes('OK') || 
                            btn.value === 'OK' ||
                            btn.type === 'submit'
                        );
                        
                        if (okButton) {
                            okButton.click();
                        }
                    }, formattedStartDate, formattedEndDate, roomType.value);
                    
                    // Ждём загрузки таблицы
                    await page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }).catch(() => {});
                    await delay(2000);
                    
                    // 3.7 Парсим таблицу
                    const tableData = await page.evaluate(() => {
                        const rows = [];
                        const table = document.querySelector('table.table');
                        if (!table) return rows;
                        
                        const tbody = table.querySelector('tbody');
                        if (!tbody) return rows;
                        
                        tbody.querySelectorAll('tr').forEach(row => {
                            const cells = [];
                            row.querySelectorAll('td').forEach(cell => {
                                cells.push(cell.textContent.trim());
                            });
                            
                            if (cells.length >= 13) {
                                rows.push({
                                    date: cells[0],
                                    stayovers_rooms: cells[1],
                                    stayovers_adults: cells[2],
                                    stayovers_children: cells[3],
                                    dayrooms_rooms: cells[4],
                                    dayrooms_adults: cells[5],
                                    dayrooms_children: cells[6],
                                    departures_rooms: cells[7],
                                    departures_adults: cells[8],
                                    departures_children: cells[9],
                                    arrivals_rooms: cells[10],
                                    arrivals_adults: cells[11],
                                    arrivals_children: cells[12]
                                });
                            }
                        });
                        
                        return rows;
                    });
                    
                    if (tableData.length > 0) {
                        tableData.forEach(row => {
                            console.log(`🐛 DEBUG row.date: "${row.date}" | converted: ${convertDate(row.date)}`);
                            allRecords.push({
                                portal_type: 'HousekeepingHotels',
                                capacity_type_id: capType.value,
                                capacity_type_name: capType.text,
                                room_type_id: roomType.value,
                                room_type_name: roomType.text,
                                date: convertDate(row.date),
                                stayovers_rooms: parseNumber(row.stayovers_rooms),
                                stayovers_adults: parseNumber(row.stayovers_adults),
                                stayovers_children: parseNumber(row.stayovers_children),
                                dayrooms_rooms: parseNumber(row.dayrooms_rooms),
                                dayrooms_adults: parseNumber(row.dayrooms_adults),
                                dayrooms_children: parseNumber(row.dayrooms_children),
                                departures_rooms: parseNumber(row.departures_rooms),
                                departures_adults: parseNumber(row.departures_adults),
                                departures_children: parseNumber(row.departures_children),
                                arrivals_rooms: parseNumber(row.arrivals_rooms),
                                arrivals_adults: parseNumber(row.arrivals_adults),
                                arrivals_children: parseNumber(row.arrivals_children)
                            });
                        });
                        
                        console.log(`  │  ├─ ${roomType.text}: ${tableData.length} строк`);
                    }
                    
                } catch (error) {
                    console.error(`  │  ├─ ❌ ${roomType.text}: ${error.message}`);
                }
                
                await delay(500);
            }
        }
        
        console.log(`\n✅ Hotels парсинг завершён: ${allRecords.length} записей`);
        
    } catch (error) {
        console.error('❌ Ошибка парсинга Hotels:', error.message);
    } finally {
        await page.close();
    }
    
    return allRecords;
}

// ПАРСИНГ LODGE
async function parseVillas(cookie, startDate, endDate) {
    console.log('\n📥 === ПАРСИНГ LODGE ===');
    const allRecords = [];
    
    const browser = await initBrowser();
    const page = await browser.newPage();
    await setCookieOnPage(page, cookie);
    
    try {
        // 1. Загружаем Capacity Analysis для Lodge
        await page.goto('https://clean.holidayclub.fi/Housekeeping/Home/CapacityAnalysis', {
            waitUntil: 'networkidle2',
            timeout: 30000
        });
        
        await delay(2000);
        
        // 2. Парсим Room Types (только с "(v)")
        const roomTypes = await page.evaluate(() => {
            const select = document.querySelector('#capacitySelect');
            if (!select) return [];
            
            return Array.from(select.options)
                .filter(opt => {
                    const value = opt.value;
                    const text = opt.textContent.trim();
                    const lowerText = text.toLowerCase();
                    return value && value !== '0' && text && 
                           text !== 'Choose' && text !== '<all>' &&
                           (lowerText.includes('(v)') || lowerText.includes(' (v)'));
                })
                .map(opt => ({
                    value: opt.value,
                    text: opt.textContent.trim()
                }));
        });
        
        console.log(`✅ Найдено Lodge Room Types: ${roomTypes.length}`);
        
        // 3. Для каждого Room Type
        for (let i = 0; i < roomTypes.length; i++) {
            const roomType = roomTypes[i];
            console.log(`\n📂 [${i + 1}/${roomTypes.length}] ${roomType.text}`);
            
            try {
                const formattedStartDate = formatDateForForm(startDate);
                const formattedEndDate = formatDateForForm(endDate);
                
                // 3.1 POST с датами и CaId
                await page.evaluate((sDate, eDate, caId) => {
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = '/Housekeeping/Home/CapacityAnalysis';
                    
                    const inputStart = document.createElement('input');
                    inputStart.name = 'StartDate';
                    inputStart.value = sDate;
                    form.appendChild(inputStart);
                    
                    const inputEnd = document.createElement('input');
                    inputEnd.name = 'EndDate';
                    inputEnd.value = eDate;
                    form.appendChild(inputEnd);
                    
                    const inputCaId = document.createElement('input');
                    inputCaId.name = 'CaId';
                    inputCaId.value = caId;
                    form.appendChild(inputCaId);
                    
                    document.body.appendChild(form);
                    form.submit();
                }, formattedStartDate, formattedEndDate, roomType.value);
                
                await page.waitForNavigation({ waitUntil: 'networkidle2' });
                await delay(1500);
                
                // 3.2 Парсим таблицу
                const tableData = await page.evaluate(() => {
                    const rows = [];
                    const table = document.querySelector('table.table');
                    if (!table) return rows;
                    
                    const tbody = table.querySelector('tbody');
                    if (!tbody) return rows;
                    
                    tbody.querySelectorAll('tr').forEach(row => {
                        const cells = [];
                        row.querySelectorAll('td').forEach(cell => {
                            cells.push(cell.textContent.trim());
                        });
                        
                        if (cells.length >= 13) {
                            rows.push({
                                date: cells[0],
                                stayovers_rooms: cells[1],
                                stayovers_adults: cells[2],
                                stayovers_children: cells[3],
                                dayrooms_rooms: cells[4],
                                dayrooms_adults: cells[5],
                                dayrooms_children: cells[6],
                                departures_rooms: cells[7],
                                departures_adults: cells[8],
                                departures_children: cells[9],
                                arrivals_rooms: cells[10],
                                arrivals_adults: cells[11],
                                arrivals_children: cells[12]
                            });
                        }
                    });
                    
                    return rows;
                });
                
                if (tableData.length > 0) {
                    tableData.forEach(row => {
                        console.log(`🐛 DEBUG Lodge row.date: "${row.date}" | converted: ${convertDate(row.date)}`);
                        allRecords.push({
                            portal_type: 'Housekeeping',
                            capacity_type_id: null,
                            capacity_type_name: '',
                            room_type_id: roomType.value,
                            room_type_name: roomType.text,
                            date: convertDate(row.date),
                            stayovers_rooms: parseNumber(row.stayovers_rooms),
                            stayovers_adults: parseNumber(row.stayovers_adults),
                            stayovers_children: parseNumber(row.stayovers_children),
                            dayrooms_rooms: parseNumber(row.dayrooms_rooms),
                            dayrooms_adults: parseNumber(row.dayrooms_adults),
                            dayrooms_children: parseNumber(row.dayrooms_children),
                            departures_rooms: parseNumber(row.departures_rooms),
                            departures_adults: parseNumber(row.departures_adults),
                            departures_children: parseNumber(row.departures_children),
                            arrivals_rooms: parseNumber(row.arrivals_rooms),
                            arrivals_adults: parseNumber(row.arrivals_adults),
                            arrivals_children: parseNumber(row.arrivals_children)
                        });
                    });
                    
                    console.log(`  ├─ Записей: ${tableData.length}`);
                }
                
            } catch (error) {
                console.error(`  ├─ ❌ Ошибка: ${error.message}`);
            }
            
            await delay(500);
        }
        
        console.log(`\n✅ Lodge парсинг завершён: ${allRecords.length} записей`);
        
    } catch (error) {
        console.error('❌ Ошибка парсинга Lodge:', error.message);
    } finally {
        await page.close();
    }
    
    return allRecords;
}

// СОХРАНЕНИЕ В БД
async function saveToDatabase(records) {
    if (records.length === 0) {
        console.log('⚠️ Нет записей для сохранения');
        return 0;
    }
    
    const connection = await dbPool.getConnection();
    
    try {
        await connection.beginTransaction();
        
        const sql = `
            INSERT INTO capacity_analysis (
                portal_type, capacity_type_id, capacity_type_name,
                room_type_id, room_type_name, date,
                stayovers_rooms, stayovers_adults, stayovers_children,
                dayrooms_rooms, dayrooms_adults, dayrooms_children,
                departures_rooms, departures_adults, departures_children,
                arrivals_rooms, arrivals_adults, arrivals_children
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                stayovers_rooms = VALUES(stayovers_rooms),
                stayovers_adults = VALUES(stayovers_adults),
                stayovers_children = VALUES(stayovers_children),
                dayrooms_rooms = VALUES(dayrooms_rooms),
                dayrooms_adults = VALUES(dayrooms_adults),
                dayrooms_children = VALUES(dayrooms_children),
                departures_rooms = VALUES(departures_rooms),
                departures_adults = VALUES(departures_adults),
                departures_children = VALUES(departures_children),
                arrivals_rooms = VALUES(arrivals_rooms),
                arrivals_adults = VALUES(arrivals_adults),
                arrivals_children = VALUES(arrivals_children),
                updated_at = CURRENT_TIMESTAMP
        `;
        
        for (const record of records) {
            await connection.query(sql, [
                record.portal_type,
                record.capacity_type_id,
                record.capacity_type_name,
                record.room_type_id,
                record.room_type_name,
                record.date,
                record.stayovers_rooms,
                record.stayovers_adults,
                record.stayovers_children,
                record.dayrooms_rooms,
                record.dayrooms_adults,
                record.dayrooms_children,
                record.departures_rooms,
                record.departures_adults,
                record.departures_children,
                record.arrivals_rooms,
                record.arrivals_adults,
                record.arrivals_children
            ]);
        }
        
        await connection.commit();
        console.log('✅ Все записи сохранены в БД');
        
        return records.length;
        
    } catch (error) {
        await connection.rollback();
        console.error('❌ Ошибка БД:', error.message);
        throw error;
    } finally {
        connection.release();
    }
}

// ENDPOINTS
app.get('/health', (req, res) => {
    res.json({
        success: true,
        service: 'Capacity Parser',
        version: '3.0.0-WORKING',
        timestamp: new Date().toISOString()
    });
});

app.post('/login', async (req, res) => {
    try {
        const { username, password } = req.body;
        
        if (!username || !password) {
            return res.status(400).json({
                success: false,
                error: 'Username и password обязательны'
            });
        }
        
        const result = await loginToCapacityAnalysis(username, password);
        res.json(result);
        
    } catch (error) {
        console.error('[Login API] Ошибка:', error.message);
        res.status(500).json({
            success: false,
            error: error.message
        });
    }
});

app.post('/start-parsing', async (req, res) => {
    const startTime = Date.now();
    const { cookie, start_date, end_date } = req.body;
    
    if (!cookie) {
        return res.status(400).json({
            success: false,
            error: 'Cookie обязателен'
        });
    }
    
    console.log('\n🚀 === ЗАПУСК ПАРСИНГА CAPACITY ANALYSIS ===');
    console.log(`📅 Период: ${start_date} → ${end_date}`);
    
    try {
        let allRecords = [];
        
        // 1. Hotels
        const hotelsRecords = await parseHotels(cookie, start_date, end_date);
        allRecords = allRecords.concat(hotelsRecords);
        
        // 2. Lodge
        const lodgeRecords = await parseVillas(cookie, start_date, end_date);
        allRecords = allRecords.concat(lodgeRecords);
        
        console.log(`\n📊 ИТОГО записей: ${allRecords.length}`);
        
        // 3. Сохранение в БД
        if (allRecords.length > 0) {
            await saveToDatabase(allRecords);
        }
        
        const duration = Math.round((Date.now() - startTime) / 1000);
        
        res.json({
            success: true,
            records: allRecords.length,
            duration: duration,
            message: `Парсинг завершён: ${allRecords.length} записей за ${duration} сек`
        });
        
    } catch (error) {
        console.error('❌ Ошибка парсинга:', error);
        res.status(500).json({
            success: false,
            error: error.message
        });
    }
});

// Запуск сервера
app.listen(PORT, '0.0.0.0', () => {
    console.log(`\n🚀 Capacity Parser (WORKING) запущен на порту ${PORT}`);
    console.log(`📍 Endpoints:`);
    console.log(`   POST http://0.0.0.0:${PORT}/login`);
    console.log(`   POST http://0.0.0.0:${PORT}/start-parsing`);
    console.log(`   GET  http://0.0.0.0:${PORT}/health\n`);
});

// Подключение к БД
(async () => {
    try {
        const connection = await dbPool.getConnection();
        console.log('✅ Подключение к БД успешно установлено');
        connection.release();
    } catch (error) {
        console.error('❌ Ошибка подключения к БД:', error.message);
    }
})();
