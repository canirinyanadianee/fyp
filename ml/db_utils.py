"""
Database utilities for ML modules.
Creates a SQLAlchemy engine using MySQL credentials from environment variables.
"""
import os
from sqlalchemy import create_engine


def get_engine():
    host = os.getenv('DB_HOST', 'localhost')
    name = os.getenv('DB_NAME', '')
    user = os.getenv('DB_USER', '')
    pw = os.getenv('DB_PASS', '')

    # Use mysql+mysqlconnector driver
    url = f"mysql+mysqlconnector://{user}:{pw}@{host}/{name}?charset=utf8mb4"
    engine = create_engine(url, pool_pre_ping=True)
    return engine
