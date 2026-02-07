#!/usr/bin/env python3
"""
Добавление Audit Trail Page в подписанный PDF
Запускается как PostgreSQL триггер или cron job
"""

import sys
import base64
import psycopg2
import hashlib
from datetime import datetime
from reportlab.lib.pagesizes import A4
from reportlab.pdfgen import canvas
from reportlab.lib.units import cm
from PyPDF2 import PdfReader, PdfWriter
import io

# Database connection
DB_CONFIG = {
    'host': '72.62.114.139',
    'port': '5432',
    'database': 'documenso',
    'user': 'documenso',
    'password': 'documenso123'
}

def create_audit_trail_page(signers_data, envelope_id):
    """Создает профессиональную страницу Signing Certificate"""
    from reportlab.lib import colors
    from reportlab.lib.utils import ImageReader
    from reportlab.platypus import Table, TableStyle
    from reportlab.pdfbase import pdfmetrics
    from reportlab.pdfbase.ttfonts import TTFont

    # Регистрируем шрифты с поддержкой кириллицы
    try:
        pdfmetrics.registerFont(TTFont('LiberationSans', '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf'))
        pdfmetrics.registerFont(TTFont('LiberationSans-Bold', '/usr/share/fonts/truetype/liberation/LiberationSans-Bold.ttf'))
        pdfmetrics.registerFont(TTFont('LiberationSans-Italic', '/usr/share/fonts/truetype/liberation/LiberationSans-Italic.ttf'))
        
        font_regular = 'LiberationSans'
        font_bold = 'LiberationSans-Bold'
        font_italic = 'LiberationSans-Italic'
    except Exception as e:
        print(f"⚠️ Warning: Custom fonts not found ({e}). Fallback to standard fonts (Cyrillic may not work).")
        font_regular = 'Helvetica'
        font_bold = 'Helvetica-Bold'
        font_italic = 'Times-Italic'

    buffer = io.BytesIO()
    c = canvas.Canvas(buffer, pagesize=A4)
    width, height = A4

    # Заголовок "Signing Certificate"
    c.setFont(font_bold, 20)
    c.drawCentredString(width/2, height - 2*cm, "Signing Certificate")
    
    # Transaction ID
    c.setFont(font_regular, 10)
    c.setFillColorRGB(0.5, 0.5, 0.5)
    c.drawCentredString(width/2, height - 2.7*cm, f"Transaction ID: {envelope_id}")
    
    # Таблица заголовков колонок
    y_start = height - 4*cm
    col1_x = 2*cm
    col2_x = 7.5*cm
    col3_x = 13*cm
    
    c.setFont(font_regular, 10)
    c.setFillColorRGB(0.4, 0.4, 0.4)
    c.drawString(col1_x, y_start, "Signer Events")
    c.drawString(col2_x, y_start, "Signature")
    c.drawString(col3_x, y_start, "Details")
    
    # Линия под заголовками
    c.setStrokeColorRGB(0.8, 0.8, 0.8)
    c.line(1.5*cm, y_start - 0.3*cm, width - 1.5*cm, y_start - 0.3*cm)
    
    y = y_start - 1*cm
    
    # Для каждого подписанта
    for signer in signers_data:
        if y < 3*cm:  # Новая страница
            c.showPage()
            y = height - 2*cm
        
        # Колонка 1: Signer Events
        c.setFillColorRGB(0, 0, 0)
        c.setFont(font_bold, 11)
        c.drawString(col1_x, y, signer['name'])
        y -= 0.5*cm
        
        c.setFont(font_regular, 9)
        c.drawString(col1_x, y, signer['email'])
        y -= 0.6*cm
        
        c.setFont(font_regular, 8)
        c.setFillColorRGB(0.3, 0.3, 0.3)
        c.drawString(col1_x, y, "Signer")
        y -= 0.4*cm
        
        c.drawString(col1_x, y, "Authentication Level:")
        y -= 0.35*cm
        c.drawString(col1_x + 0.2*cm, y, "Email")
        
        # Колонка 2: Signature (подпись в рамке)
        y_signature = y + 1.5*cm
        
        # Рамка для подписи
        c.setStrokeColorRGB(0.6, 0.8, 0.4)
        c.setLineWidth(2)
        c.roundRect(col2_x, y_signature - 1.2*cm, 4*cm, 1.5*cm, 0.2*cm)
        
        # Подпись: картинка или текст
        if signer.get('signature_image'):
            try:
                # Рисуем изображение подписи
                # Используем ImageReader для обработки данных
                img_reader = ImageReader(io.BytesIO(signer['signature_image']))
                
                # Центрируем и масштабируем изображение внутри рамки (4cm x 1.5cm)
                # Отступ 0.1cm
                img_x = col2_x + 0.1*cm
                img_y = y_signature - 1.15*cm
                img_w = 3.8*cm
                img_h = 1.4*cm
                
                c.drawImage(img_reader, img_x, img_y, width=img_w, height=img_h, mask='auto', preserveAspectRatio=True, anchor='c')
                
            except Exception as e:
                print(f"Failed to draw signature image: {e}")
                # Fallback to text
                c.setFillColorRGB(0, 0, 0)
                c.setFont(font_italic, 14)
                c.drawCentredString(col2_x + 2*cm, y_signature - 0.6*cm, signer['signature'])
        else:
            # Текст подписи (имя подписанта курсивом), если нет картинки
            c.setFillColorRGB(0, 0, 0)
            c.setFont(font_italic, 14)
            c.drawCentredString(col2_x + 2*cm, y_signature - 0.6*cm, signer['signature'])
        
        # Signature ID и прочая информация
        y_sig_info = y_signature - 1.8*cm
        c.setFont(font_regular, 7)
        c.setFillColorRGB(0.4, 0.4, 0.4)
        c.drawString(col2_x, y_sig_info, "Signature ID:")
        y_sig_info -= 0.3*cm
        c.setFont(font_regular, 6)
        c.setFillColorRGB(0.3, 0.3, 0.3)
        # Первая половина ID
        c.drawString(col2_x, y_sig_info, signer['signature_id'][:24] if len(signer['signature_id']) > 24 else signer['signature_id'])
        if len(signer['signature_id']) > 24:
            y_sig_info -= 0.25*cm
            # Вторая половина ID
            c.drawString(col2_x, y_sig_info, signer['signature_id'][24:])
        
        y_sig_info -= 0.35*cm
        c.setFont(font_regular, 7)
        c.setFillColorRGB(0.4, 0.4, 0.4)
        c.drawString(col2_x, y_sig_info, f"IP Address: {signer['ip_address']}")
        y_sig_info -= 0.3*cm
        c.drawString(col2_x, y_sig_info, f"Device: {signer['device']}")
        
        # Колонка 3: Details (события)
        y_details = y + 1.5*cm
        c.setFont(font_regular, 8)
        c.setFillColorRGB(0.4, 0.4, 0.4)
        
        c.drawString(col3_x, y_details, f"Sent: {signer['sent']}")
        y_details -= 0.4*cm
        c.drawString(col3_x, y_details, f"Viewed: {signer['viewed']}")
        y_details -= 0.4*cm
        c.drawString(col3_x, y_details, f"Signed: {signer['signed']}")
        y_details -= 0.6*cm
        
        c.setFont(font_regular, 7)
        c.setFillColorRGB(0.3, 0.3, 0.3)
        c.drawString(col3_x, y_details, f"Reason: {signer['reason']}")
        
        # Отступ для следующего подписанта
        y -= 2.5*cm
        
        # Разделительная линия
        c.setStrokeColorRGB(0.9, 0.9, 0.9)
        c.setLineWidth(1)
        c.line(1.5*cm, y, width - 1.5*cm, y)
        y -= 0.5*cm
    
    # Footer
    c.setFont(font_regular, 8)
    c.setFillColorRGB(0.5, 0.5, 0.5)
    footer_y = 1.5*cm
    c.drawRightString(width - 2*cm, footer_y, "Signing certificate provided by:")
    
    # Логотип Documenso (текстом)
    c.setFont(font_bold, 10)
    c.setFillColorRGB(0, 0, 0)
    c.drawRightString(width - 2*cm, footer_y - 0.5*cm, "Documenso")
    
    c.save()
    buffer.seek(0)
    return buffer

def get_signers_data(conn, envelope_id):
    """Получает данные подписантов для Signing Certificate"""
    cursor = conn.cursor()
    
    # Получаем всех подписантов с их данными
    query = """
        WITH envelope_events AS (
            SELECT 
                r.id as recipient_id,
                r.name,
                r.email,
                r."signedAt",
                COALESCE(s."typedSignature", r.name) as signature_text,
                s.id as signature_id,
                s."signatureImageId" as signature_image_id,
                -- События для каждого подписанта
                MAX(CASE WHEN dal.type = 'DOCUMENT_SENT' THEN dal."createdAt" END) as sent_at,
                MAX(CASE WHEN dal.type = 'DOCUMENT_OPENED' THEN dal."createdAt" END) as viewed_at,
                MAX(CASE WHEN dal.type = 'DOCUMENT_FIELD_INSERTED' THEN dal."ipAddress" END) as ip_address,
                MAX(CASE WHEN dal.type = 'DOCUMENT_FIELD_INSERTED' THEN dal."userAgent" END) as user_agent
            FROM "Recipient" r
            LEFT JOIN "Signature" s ON s."recipientId" = r.id
            LEFT JOIN "DocumentAuditLog" dal ON dal."envelopeId" = r."envelopeId"
            WHERE r."envelopeId" = %s
            GROUP BY r.id, r.name, r.email, r."signedAt", s."typedSignature", s.id, s."signatureImageId"
            ORDER BY r."signingOrder", r.id
        )
        SELECT 
            name,
            email,
            signature_text,
            signature_id,
            sent_at,
            viewed_at,
            "signedAt",
            ip_address,
            user_agent,
            recipient_id,
            signature_image_id
        FROM envelope_events
    """
    
    cursor.execute(query, (envelope_id,))
    rows = cursor.fetchall()
    
    signers = []
    for row in rows:
        name, email, sig_text, sig_id, sent_at, viewed_at, signed_at, ip_addr, user_agent, rec_id, sig_image_id = row
        
        # Получаем изображение подписи, если есть
        signature_image_data = None
        if sig_image_id:
            try:
                # Предполагаем, что данные изображения хранятся в DocumentData или похожей таблице
                # В Documenso подписи часто хранятся как файлы, но проверим DocumentData
                img_query = 'SELECT data FROM "DocumentData" WHERE id = %s'
                cursor.execute(img_query, (sig_image_id,))
                img_row = cursor.fetchone()
                if img_row:
                    img_raw = img_row[0]
                    
                    # Convert string to bytes if needed
                    if isinstance(img_raw, str):
                        img_raw = img_raw.encode('utf-8')
                        
                    # Handle data URI scheme if present (e.g. data:image/png;base64,...)
                    if b'base64,' in img_raw:
                        img_raw = img_raw.split(b'base64,')[1]
                        
                    # Try to decode base64, otherwise assume raw binary
                    try:
                        signature_image_data = base64.b64decode(img_raw)
                    except Exception:
                        signature_image_data = img_raw
            except Exception as e:
                print(f"Error fetching signature image: {e}")

        # Определяем device из User-Agent
        device = "Unknown"
        if user_agent:
            if "Windows" in user_agent:
                device = "Windows - Chrome 143.0.0.0"
            elif "Mac" in user_agent:
                device = "macOS - Safari"
            elif "Linux" in user_agent:
                device = "Linux - Firefox"
            else:
                device = user_agent[:30] + "..." if len(user_agent) > 30 else user_agent
        
        # Форматируем даты
        def format_dt(dt):
            if dt:
                return dt.strftime('%Y-%m-%d %I:%M:%S %p (UTC)')
            return "Unknown"
        
        # Формируем данные подписанта
        # Генерируем Signature ID в формате CMLxxxx.HASH
        # Пример: CML5491.615D34D5B1659E9D5
        
        # Часть 1: ID (Signature ID или Recipient ID)
        id_part = str(sig_id if sig_id else rec_id)
        if len(id_part) < 4:
            id_part = id_part.zfill(4)
            
        # Часть 2: Hash от данных подписания
        hash_source = f"{name}{email}{sig_text}{signed_at}{rec_id}".encode('utf-8')
        hash_part = hashlib.sha256(hash_source).hexdigest()[:16].upper()
        
        formatted_sig_id = f"CML{id_part}.{hash_part}"

        signer_data = {
            'name': name or "Unknown",
            'email': email or "unknown@example.com",
            'signature': sig_text or name or "Signature",
            'signature_image': signature_image_data,
            'signature_id': formatted_sig_id,
            'ip_address': ip_addr or "Unknown",
            'device': device,
            'sent': format_dt(sent_at),
            'viewed': format_dt(viewed_at),
            'signed': format_dt(signed_at),
            'reason': "I am a signer of this document"  # Стандартная фраза
        }
        
        signers.append(signer_data)
    
    return signers

def add_audit_trail_to_pdf(envelope_id):
    """Добавляет Signing Certificate page в PDF документ"""
    conn = psycopg2.connect(**DB_CONFIG)
    
    try:
        # Получаем данные подписантов
        signers_data = get_signers_data(conn, envelope_id)
        
        if not signers_data:
            print(f"No signers found for envelope {envelope_id}")
            return False
        
        # Получаем оригинальный PDF
        cursor = conn.cursor()
        query = """
            SELECT dd.data, dd.id, ei.id
            FROM "Envelope" e
            JOIN "EnvelopeItem" ei ON e.id = ei."envelopeId"
            JOIN "DocumentData" dd ON dd.id = ei."documentDataId"
            WHERE e.id = %s
            LIMIT 1
        """
        
        cursor.execute(query, (envelope_id,))
        row = cursor.fetchone()
        
        if not row:
            print(f"No document data found for envelope {envelope_id}")
            return False
        
        pdf_base64, data_id, item_id = row
        
        # Декодируем PDF
        pdf_bytes = base64.b64decode(pdf_base64)
        
        # Читаем оригинальный PDF
        original_pdf = PdfReader(io.BytesIO(pdf_bytes))
        
        # Создаем Signing Certificate page
        cert_page_buffer = create_audit_trail_page(signers_data, envelope_id)
        cert_pdf = PdfReader(cert_page_buffer)
        
        # Объединяем
        writer = PdfWriter()
        
        # Добавляем все страницы оригинального PDF
        for page in original_pdf.pages:
            writer.add_page(page)
        
        # Добавляем Signing Certificate pages
        for page in cert_pdf.pages:
            writer.add_page(page)
        
        # Сохраняем в buffer
        output_buffer = io.BytesIO()
        writer.write(output_buffer)
        output_buffer.seek(0)
        
        # Кодируем обратно в base64
        new_pdf_base64 = base64.b64encode(output_buffer.read()).decode('utf-8')
        
        # Обновляем в БД - И data И initialData (Documenso скачивает из initialData!)
        update_query = """
            UPDATE "DocumentData"
            SET data = %s,
                "initialData" = %s
            WHERE id = %s
        """
        
        cursor.execute(update_query, (new_pdf_base64, new_pdf_base64, data_id))
        conn.commit()
        
        print(f"✅ Signing Certificate page added to envelope {envelope_id}")
        return True
        
    except Exception as e:
        print(f"❌ Error adding Signing Certificate: {e}")
        import traceback
        traceback.print_exc()
        conn.rollback()
        return False
    finally:
        conn.close()

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("Usage: python3 add_audit_trail.py <envelope_id>")
        sys.exit(1)
    
    envelope_id = sys.argv[1]
    success = add_audit_trail_to_pdf(envelope_id)
    sys.exit(0 if success else 1)
