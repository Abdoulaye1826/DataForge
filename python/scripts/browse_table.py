"""
Module Compréhension: lets a data analyst actually look at the raw rows of a
table - every other view in DataForge only shows column-level stats/samples,
but spotting a suspicious record still means scrolling/searching the real
data. Reads straight from the Parquet cache, applies an optional free-text
search and single-column sort, then returns one page of rows as plain JSON.
"""

import sys
from pathlib import Path

import pandas as pd

sys.path.append(str(Path(__file__).resolve().parent.parent))

from common.io_utils import read_table_cache  # noqa: E402
from common.json_utils import run_script  # noqa: E402

MAX_PER_PAGE = 200


def main(input_data: dict) -> dict:
    df = read_table_cache(input_data["storage_path"])
    columns = df.columns.tolist()

    search = (input_data.get("search") or "").strip()
    if search:
        mask = df.astype(str).apply(lambda col: col.str.contains(search, case=False, na=False))
        df = df[mask.any(axis=1)]

    sort_column = input_data.get("sort_column")
    if sort_column in columns:
        ascending = input_data.get("sort_direction", "asc") != "desc"
        df = df.sort_values(by=sort_column, ascending=ascending, na_position="last", kind="mergesort")

    total = int(len(df))
    page = max(int(input_data.get("page", 1)), 1)
    per_page = min(int(input_data.get("per_page", 25)), MAX_PER_PAGE)
    start = (page - 1) * per_page

    page_df = df.iloc[start:start + per_page]
    # NaN/NaT aren't valid JSON - normalize to None before converting to records.
    page_df = page_df.astype(object).where(pd.notnull(page_df), None)

    return {
        "columns": columns,
        "rows": page_df.to_dict(orient="records"),
        "total": total,
        "page": page,
        "per_page": per_page,
    }


if __name__ == "__main__":
    run_script(main)
