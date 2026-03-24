import psycopg2
from psycopg2 import pool as pg_pool
from config import DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASSWORD

_pool: pg_pool.ThreadedConnectionPool | None = None


def init_pool() -> None:
    global _pool
    _pool = pg_pool.ThreadedConnectionPool(
        minconn=1,
        maxconn=5,
        host=DB_HOST,
        port=DB_PORT,
        dbname=DB_NAME,
        user=DB_USER,
        password=DB_PASSWORD,
        connect_timeout=10,
    )
    # Smoke-test the connection
    conn = _pool.getconn()
    _pool.putconn(conn)
    print(f"[DB] Connected to {DB_NAME}@{DB_HOST}:{DB_PORT}")


def get_conn():
    return _pool.getconn()


def put_conn(conn) -> None:
    _pool.putconn(conn)


def close_pool() -> None:
    if _pool:
        _pool.closeall()
