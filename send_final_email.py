#!/usr/bin/env python3
"""
Send Final Email for Documenso Envelope
"""
import sys
import smtplib
import psycopg2
from email.mime.text import MIMEText
from email.mime.multipart import MIMEMultipart
import re

# Load config
SMTP_CONFIG_FILE = '/var/www/documenso-bridge/smtp_config.php'
# Default fallback
SMTP_CONFIG = {
    'host': 'smtp.hostinger.com',
    'port': 587,
    'username': 'noreply@matrang.com',
    'password': 'Gibson2104)))',
    'from_email': 'noreply@matrang.com',
    'from_name': 'Great Legacy Bully'
}

def load_php_config(path):
    config = {}
    try:
        with open(path, 'r') as f:
            content = f.read()
            # Simple regex parser for PHP array return
            matches = re.findall(r"'(\w+)'\s*=>\s*['\"]?(.*?)['\"]?(?:,|$)", content)
            for key, val in matches:
                if val.lower() == 'true': val = True
                elif val.lower() == 'false': val = False
                elif val.isdigit(): val = int(val)
                config[key] = val
    except Exception as e:
        print(f"Error loading PHP config: {e}. Using defaults.")
    
    # Merge
    for k, v in config.items():
        SMTP_CONFIG[k] = v

load_php_config(SMTP_CONFIG_FILE)

DB_CONFIG = {
    'host': '72.62.114.139',
    'port': '5432',
    'database': 'documenso',
    'user': 'documenso',
    'password': 'documenso123'
}


def send_final_email(envelope_id):
    conn = psycopg2.connect(**DB_CONFIG)
    cursor = conn.cursor()
    
    # Get recipients
    cursor.execute('SELECT email, name FROM "Recipient" WHERE "envelopeId" = %s', (envelope_id,))
    recipients = cursor.fetchall()

    # Fetch PDF data
    cursor.execute("""
        SELECT dd.data 
        FROM "Envelope" e
        JOIN "EnvelopeItem" ei ON e.id = ei."envelopeId"
        JOIN "DocumentData" dd ON dd.id = ei."documentDataId"
        WHERE e.id = %s
        LIMIT 1
    """, (envelope_id,))
    pdf_row = cursor.fetchone()
    
    conn.close()
    
    if not recipients:
        print(f"No recipients found for {envelope_id}")
        return

    # Prepare PDF attachment if available
    pdf_attachment = None
    if pdf_row:
        import base64
        from email.mime.application import MIMEApplication
        try:
            pdf_bytes = base64.b64decode(pdf_row[0])
            pdf_attachment = MIMEApplication(pdf_bytes, _subtype="pdf")
            pdf_attachment.add_header('Content-Disposition', 'attachment', filename=f"Contract_{envelope_id}.pdf")
        except Exception as e:
            print(f"Error preparing PDF attachment: {e}")

    # Use the public URL or fallback to IP
    download_link = f"http://72.62.114.139/download_signed.php?id={envelope_id}"
    
    subject = f"Contract Signed / Договор подписан: {envelope_id}"
    
    for email, name in recipients:
        # Avoid sending to empty emails - simplistic check
        if not email or '@' not in email:
            continue
            
        print(f"Sending email to {name} <{email}>...")
        
        msg = MIMEMultipart()
        msg['From'] = f"{SMTP_CONFIG['from_name']} <{SMTP_CONFIG['from_email']}>"
        msg['To'] = email
        msg['Subject'] = subject
        
        body = f"""
        <html>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <div style="margin-bottom: 20px;">
                <h2 style="color: #2c3e50;">Поздравляем!</h2>
                <p>Здравствуйте!</p>
                <p>Процесс подписания успешно завершен. К письму прикреплен ваш экземпляр договора. Данный файл содержит Лист Аудита, подтверждающий подлинность сделки.</p>
                <p>Мы рекомендуем сохранить этот файл.</p>
                <p><strong>Поздравляем с приобретением будущего члена семьи!</strong></p>
            </div>
            
            <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">
            
            <div style="margin-bottom: 20px;">
                <h2 style="color: #2c3e50;">Congratulations!</h2>
                <p>Hello {name}!</p>
                <p>The signing process has been successfully completed. Attached to this email is your copy of the contract. This file contains an Audit Trail confirming the authenticity of the transaction.</p>
                <p>We recommend saving this file.</p>
                <p><strong>Congratulations on acquiring a future family member!</strong></p>
            </div>

            <div style="background-color: #f9f9f9; padding: 15px; border-radius: 5px; text-align: center;">
                <p>Скачать договор / Download Contract:</p>
                <p>
                    <a href="{download_link}" style="background-color: #4CAF50; color: white; padding: 10px 20px; text-decoration: none; display: inline-block; border-radius: 5px; font-weight: bold;">
                        Download Signed Document
                    </a>
                </p>
            </div>
        </body>
        </html>
        """
        
        msg.attach(MIMEText(body, 'html'))
        
        if pdf_attachment:
            msg.attach(pdf_attachment)

        
        try:
            server = smtplib.SMTP(SMTP_CONFIG['host'], SMTP_CONFIG['port'])
            server.starttls()
            server.login(SMTP_CONFIG['username'], SMTP_CONFIG['password'])
            server.send_message(msg)
            server.quit()
            print("Email sent successfully.")
        except Exception as e:
            print(f"Failed to send email to {email}: {e}")

if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("Usage: send_final_email.py <envelope_id>")
        sys.exit(1)
        
    envelope_id = sys.argv[1]
    send_final_email(envelope_id)
