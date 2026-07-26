"""
Module Gestion des relations: turns a confirmed DatasetRelationship into an
actual joined table the user can analyze - detecting that patients.service
and staff.service are the same key is only half the job; a data analyst
needs the combined rows to cross-tabulate satisfaction against staff morale,
for instance. Writes a brand-new Parquet cache; Laravel wraps it in its own
DatasetTable and runs it through the normal onboarding pipeline (columns,
quality, EDA, insights, charts) exactly like a freshly imported table.
"""

import sys
from pathlib import Path

import pandas as pd

sys.path.append(str(Path(__file__).resolve().parent.parent))

from common.io_utils import read_table_cache, write_table_cache  # noqa: E402
from common.json_utils import run_script  # noqa: E402

JOIN_TYPES = {"inner", "left", "right", "outer"}


def main(input_data: dict) -> dict:
    left = read_table_cache(input_data["left_storage_path"])
    right = read_table_cache(input_data["right_storage_path"])

    left_column = input_data["left_column"]
    right_column = input_data["right_column"]
    join_type = input_data["join_type"]

    if join_type not in JOIN_TYPES:
        raise ValueError(f"Unsupported join type: {join_type}")

    if left_column == right_column:
        # Same key name on both sides - pandas coalesces it into one column.
        merged = pd.merge(left, right, on=left_column, how=join_type, suffixes=("_gauche", "_droite"))
    else:
        # Different key names: keep both columns rather than guessing which
        # one to drop - dropping the wrong one would silently lose the join
        # key for unmatched rows on a left/right/outer join.
        merged = pd.merge(
            left, right,
            left_on=left_column, right_on=right_column,
            how=join_type, suffixes=("_gauche", "_droite"),
        )

    storage_path = write_table_cache(merged, input_data["output_dir"], input_data["file_stem"])

    return {
        "storage_path": storage_path,
        "row_count": int(len(merged)),
        "column_count": int(len(merged.columns)),
    }


if __name__ == "__main__":
    run_script(main)
