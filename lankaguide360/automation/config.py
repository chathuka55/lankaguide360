"""Shared configuration for the LankaGuide 360 Selenium automation suite."""
import os

try:
    from dotenv import load_dotenv
    load_dotenv()
except ImportError:
    pass

# Base URL of the running app. Override with BASE_URL env var.
#   PHP built-in server:  http://localhost:8000
#   Docker compose:       http://localhost:8080
BASE_URL = os.getenv("BASE_URL", "http://localhost:8000").rstrip("/")

# Demo credentials (activate them first via tools/generate-password-hash.php).
DEMO_PASSWORD = os.getenv("DEMO_PASSWORD", "Lanka@123")
STAFF = {
    "dispatcher": os.getenv("DISPATCHER_EMAIL", "dispatcher@lankaguide360.lk"),
    "manager":    os.getenv("MANAGER_EMAIL", "manager@lankaguide360.lk"),
    "admin":      os.getenv("ADMIN_EMAIL", "admin@lankaguide360.lk"),
}

# Selenium timing
PAGE_TIMEOUT = int(os.getenv("PAGE_TIMEOUT", "20"))
SHORT_WAIT = 2


def url(path: str = "") -> str:
    return f"{BASE_URL}/{path.lstrip('/')}"
