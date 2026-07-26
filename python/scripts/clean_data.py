"""
Module Nettoyage: applies one cleaning operation (dedupe, trim_spaces,
fix_case, fix_dates) to a table's cached data, overwrites the cache in place,
and returns freshly recomputed column stats so Laravel can update
DatasetColumn without a separate analyze_structure.py round-trip.
"""

import sys
from pathlib import Path

import pandas as pd

sys.path.append(str(Path(__file__).resolve().parent.parent))

from common.io_utils import overwrite_table_cache, read_table_cache  # noqa: E402
from common.json_utils import as_dict, run_script  # noqa: E402
from common.type_detection import detect_column  # noqa: E402


def main(input_data: dict) -> dict:
    df = read_table_cache(input_data["storage_path"])
    operation = input_data["operation"]
    params = as_dict(input_data.get("params"))

    rows_before = len(df)

    handlers = {
        "dedupe": _dedupe,
        "trim_spaces": _trim_spaces,
        "fix_case": _fix_case,
        "fix_dates": _fix_dates,
    }

    if operation not in handlers:
        raise ValueError(f"Unknown cleaning operation: {operation}")

    df, rows_affected = handlers[operation](df, params)

    overwrite_table_cache(df, input_data["storage_path"])

    columns = [detect_column(df[column]) for column in df.columns]

    return {
        "rows_before": rows_before,
        "rows_after": len(df),
        "rows_affected": rows_affected,
        "row_count": len(df),
        "column_count": len(df.columns),
        "columns": columns,
    }


def _dedupe(df: pd.DataFrame, params: dict) -> tuple[pd.DataFrame, int]:
    subset = params.get("columns") or None
    before = len(df)
    df = df.drop_duplicates(subset=subset).reset_index(drop=True)
    return df, before - len(df)


def _trim_spaces(df: pd.DataFrame, params: dict) -> tuple[pd.DataFrame, int]:
    columns = params.get("columns") or df.select_dtypes(include="object").columns.tolist()
    changed_rows = pd.Series(False, index=df.index)

    for column in columns:
        original = df[column]
        trimmed = original.astype(str).str.strip()
        trimmed = trimmed.where(original.notna(), original)
        changed_rows |= (original.astype(str) != trimmed.astype(str)) & original.notna()
        df[column] = trimmed

    return df, int(changed_rows.sum())


def _fix_case(df: pd.DataFrame, params: dict) -> tuple[pd.DataFrame, int]:
    columns = params.get("columns") or []
    mode = params.get("mode", "title")
    changed_rows = pd.Series(False, index=df.index)

    caser = {
        "lower": lambda s: s.str.lower(),
        "upper": lambda s: s.str.upper(),
        "title": lambda s: s.str.title(),
    }.get(mode)

    if caser is None:
        raise ValueError(f"Unknown fix_case mode: {mode}")

    for column in columns:
        original = df[column].astype(str)
        updated = caser(original)
        changed_rows |= (original != updated) & df[column].notna()
        df[column] = updated.where(df[column].notna(), df[column])

    return df, int(changed_rows.sum())


def _fix_dates(df: pd.DataFrame, params: dict) -> tuple[pd.DataFrame, int]:
    column = params["column"]
    original = df[column]
    parsed = pd.to_datetime(original, errors="coerce", format="mixed")

    original_str = original.astype(str).str.strip()
    parsed_str = parsed.dt.strftime("%Y-%m-%d %H:%M:%S")
    rows_affected = int((original.notna() & parsed.notna() & (original_str != parsed_str)).sum())

    df[column] = parsed
    return df, rows_affected


if __name__ == "__main__":
    run_script(main)
