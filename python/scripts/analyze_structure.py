"""
Module "Compréhension automatique": inspects every column of a cached table
(row/column counts already known from import_dataset.py) and detects its
type (numeric/text/date/boolean/category), null ratio, distinct count,
sample values and basic stats - populates DatasetColumn per table.
"""

import sys
from pathlib import Path

sys.path.append(str(Path(__file__).resolve().parent.parent))

from common.io_utils import read_table_cache  # noqa: E402
from common.json_utils import run_script  # noqa: E402
from common.type_detection import detect_column  # noqa: E402


def main(input_data: dict) -> dict:
    df = read_table_cache(input_data["storage_path"])

    columns = [detect_column(df[column]) for column in df.columns]

    return {"columns": columns}


if __name__ == "__main__":
    run_script(main)
