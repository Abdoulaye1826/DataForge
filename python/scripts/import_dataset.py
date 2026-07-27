"""
Module 3 (Importation): reads the uploaded file - whatever its format - and
turns it into one or more cached tables (one per Excel sheet, one otherwise),
each written to output_dir as Parquet so the rest of the pipeline can re-read
it quickly. Returns per-table row/column counts and cache paths; per-column
analysis is a separate step (see analyze_structure.py).
"""

import sys
from pathlib import Path

sys.path.append(str(Path(__file__).resolve().parent.parent))

from common.io_utils import read_source_tables, write_table_cache  # noqa: E402
from common.json_utils import run_script  # noqa: E402


def main(input_data: dict) -> dict:
    source_path = input_data["source_path"]
    fmt = input_data["format"]
    output_dir = input_data["output_dir"]
    default_name = input_data.get("default_name")

    tables, file_meta = read_source_tables(source_path, fmt, default_name)

    results = []
    skipped = []

    for name, df in tables.items():
        # A sheet built from charts/pivot tables/dashboards rather than
        # literal cell data comes back empty - keeping it would create a
        # useless 0-row/0-column table, so it's dropped and reported instead.
        if df.empty:
            skipped.append(name)
            continue

        storage_path = write_table_cache(df, output_dir, name)
        results.append({
            "name": name,
            "row_count": len(df),
            "column_count": len(df.columns),
            "storage_path": storage_path,
        })

    if not results:
        raise ValueError("Aucune table exploitable trouvée dans le fichier (toutes les feuilles sont vides).")

    return {"tables": results, "skipped_sheets": skipped, "file_meta": file_meta}


if __name__ == "__main__":
    run_script(main)
