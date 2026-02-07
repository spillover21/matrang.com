#!/usr/bin/env python3
"""
Watcher script that listens for 'envelope_completed' notifications
and immediately generates the signing certificate.
"""
import select
import psycopg2
import psycopg2.extensions
import time
import sys
import os

# Import generation function
from add_audit_trail import add_audit_trail_to_pdf, DB_CONFIG

# Unbuffer stdout so logs appear immediately in systemd journal
sys.stdout.reconfigure(line_buffering=True)

def listen_loop():
    print("🚀 Starting Audit Trail Watcher...")
    
    # Connect to DB
    try:
        conn = psycopg2.connect(**DB_CONFIG)
        conn.set_isolation_level(psycopg2.extensions.ISOLATION_LEVEL_AUTOCOMMIT)
        
        curs = conn.cursor()
        curs.execute("LISTEN envelope_completed;")
        print("✅ Connected to PostgreSQL. Waiting for 'envelope_completed' notifications...")
        
    except Exception as e:
        print(f"❌ Connection failed: {e}")
        sys.exit(1)

    while True:
        try:
            # Wait for notifications (timeout 5s to allow responding to signals/keepalive)
            if select.select([conn], [], [], 5) == ([], [], []):
                pass
            else:
                conn.poll()
                while conn.notifies:
                    notify = conn.notifies.pop(0)
                    envelope_id = notify.payload
                    print(f"🔔 Event received for envelope: {envelope_id}")
                    
                    try:
                        # Wait 2 seconds to ensure transaction ensures and PDF file is ready
                        time.sleep(2)
                        
                        print(f"⏳ Processing {envelope_id}...")
                        result = add_audit_trail_to_pdf(envelope_id)
                        
                        if result:
                            print(f"✅ Certificate generated for {envelope_id}")
                        else:
                            print(f"⚠️ Failed to generate for {envelope_id}")
                            
                    except Exception as e:
                        print(f"❌ Error processing {envelope_id}: {e}")
                        import traceback
                        traceback.print_exc()
                        
        except psycopg2.OperationalError:
            print("⚠️ Database connection lost. Reconnecting...")
            try:
                conn = psycopg2.connect(**DB_CONFIG)
                conn.set_isolation_level(psycopg2.extensions.ISOLATION_LEVEL_AUTOCOMMIT)
                curs = conn.cursor()
                curs.execute("LISTEN envelope_completed;")
                print("✅ Reconnected.")
            except Exception as e:
                print(f"❌ Reconnection failed: {e}")
                time.sleep(5)

if __name__ == "__main__":
    listen_loop()