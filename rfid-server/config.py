import os
from dotenv import load_dotenv

load_dotenv()

API_URL   = os.getenv('API_URL', '').rstrip('/')
API_KEY   = os.getenv('API_KEY', '')
DEVICE_ID = os.getenv('DEVICE_ID', 'GATE-01')
TIMEZONE  = os.getenv('TIMEZONE', 'Asia/Manila')

DISPLAY_FULLSCREEN  = os.getenv('DISPLAY_FULLSCREEN', '1') == '1'
DISPLAY_CLEAR_AFTER = int(os.getenv('DISPLAY_CLEAR_AFTER', 4))

SYNC_INTERVAL = int(os.getenv('SYNC_INTERVAL', 2))
LOCAL_DB_PATH = os.getenv('LOCAL_DB_PATH', 'buffer.db')
