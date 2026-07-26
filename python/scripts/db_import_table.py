"""
Module "Connecteurs SQL": reads one table from a live PostgreSQL/MySQL
database in full and caches it as Parquet, exactly like import_dataset.py
does for an uploaded file - the rest of the pipeline (onboarding, cleaning,
EDA...) never needs to know a table came from a live connection instead of a
file. Uses pd.read_sql_table() (SQLAlchemy reflection) rather than a raw
"SELECT * FROM {table}" string so the table name - which the user picked from
a dropdown built by db_list_tables.py, not free text - never gets interpolated
into SQL directly.
"""

import sys
from pathlib import Path

sys.path.append(str(Path(__file__).resolve().parent.parent))

import pandas as pd  # noqa: E402

from common.db_utils import build_engine  # noqa: E402
from common.io_utils import write_table_cache  # noqa: E402
from common.json_utils import run_script  # noqa: E402


def main(input_data: dict) -> dict:
    table_name = input_data["table_name"]
    output_dir = input_data["output_dir"]
    engine = build_engine(input_data["connection"])

    with engine.connect() as conn:
        df = pd.read_sql_table(table_name, conn)

    if df.empty:
        raise ValueError(f"La table « {table_name} » est vide - rien à importer.")

    storage_path = write_table_cache(df, output_dir, table_name)

    return {
        "name": table_name,
        "row_count": len(df),
        "column_count": len(df.columns),
        "storage_path": storage_path,
    }


if __name__ == "__main__":
    run_script(main)
