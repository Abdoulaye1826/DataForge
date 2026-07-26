"""
Shared helper for the direct database connectors (Module "Connecteurs SQL"):
builds a SQLAlchemy engine from the connection details Laravel passes through
(DatabaseConnection model), used by both db_list_tables.py (browsing) and
db_import_table.py (actual import). Centralised here so the URL-building and
driver-name mapping only happens in one place.
"""

from sqlalchemy import create_engine
from sqlalchemy.engine import Engine

_DRIVERS = {
    "pgsql": "postgresql+psycopg2",
    "mysql": "mysql+pymysql",
}


def build_engine(connection: dict) -> Engine:
    driver = connection["driver"]

    if driver not in _DRIVERS:
        raise ValueError(f"Driver de base de données non supporté : {driver}")

    dialect = _DRIVERS[driver]
    user = connection["username"]
    password = connection["password"]
    host = connection["host"]
    port = connection["port"]
    database = connection["database"]

    from sqlalchemy.engine import URL

    url = URL.create(
        dialect,
        username=user,
        password=password,
        host=host,
        port=port,
        database=database,
    )

    # pool_pre_ping avoids handing back a dead connection from a stale pool -
    # irrelevant in practice since this process is short-lived (one script
    # call = one connection), but costs nothing and rules out a class of
    # flaky "server has gone away" errors on slow/remote databases.
    return create_engine(url, pool_pre_ping=True)
