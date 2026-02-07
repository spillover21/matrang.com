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
    
    conn.close()
    
    if not recipients:
        print(f"No recipients found for {envelope_id}")
        return

    download_link = f"http://72.62.114.139/download_signed.php?id={envelope_id}"
    
    subject = f"Document Signed: {envelope_id}"
    
    for email, name in recipients:
        # Avoid sending to empty emails
        if not email or '@' not in email:
            continue
            
        print(f"Sending email to {name} <{email}>...")
        
        msg = MIMEMultipart()
        msg['From'] = f"{SMTP_CONFIG['from_name']} <{SMTP_CONFIG['from_email']}>"
        msg['To'] = email
        msg['Subject'] = subject
        
        body = f"""
        <html>
        <body>
            <h2>Document Completed</h2>
            <p>Hello {name},</p>
            <p>The document <strong>{envelope_id}</strong> has been signed by all parties.</p>
            <p>You can download the final signed document with the audit trail certificate using the link below:</p>
            <p>
                <a href="{download_link}" style="background-color: #4CAF50; color: white; padding: 10px 20px; text-align: center; text-decoration: none; display: inline-block; border-radius: 4px;">Download Signed Document</a>
            </p>
            <p>Or verify it using the Transaction ID provided on the certificate.</p>
            <hr>
            <p><small>Sent by Documenso Bridge</small></p>
        </body>
        </html>
        """
        
        msg.attach(MIMEText(body, 'html'))
        
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
