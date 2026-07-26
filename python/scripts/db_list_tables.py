"""
Module "Connecteurs SQL": connects to a live PostgreSQL/MySQL database and
lists its tables (name, column count, row count) so the user can pick one to
import - see DatabaseConnectionService::listTables(). Also doubles as the
"test connection" step when a connection is first saved: if this script
succeeds, the credentials are valid and reachable.
"""

import sys
from pathlib import Path

sys.path.append(str(Path(__file__).resolve().parent.parent))

from sqlalchemy import inspect, text  # noqa: E402

from common.db_utils import build_engine  # noqa: E402
from common.json_utils import run_script  # noqa: E402


def main(input_data: dict) -> dict:
    engine = build_engine(input_data["connection"])

    with engine.connect() as conn:
        inspector = inspect(conn)
        table_names = inspector.get_table_names()

        tables = []
        for name in table_names:
            columns = inspector.get_columns(name)
            row_count = conn.execute(
                text(f'SELECT COUNT(*) FROM "{name}"' if engine.dialect.name == "postgresql" else f"SELECT COUNT(*) FROM `{name}`")
            ).scalar()
            tables.append({
                "name": name,
                "column_count": len(columns),
                "row_count": row_count,
            })

    return {"tables": tables}


if __name__ == "__main__":
    run_script(main)
