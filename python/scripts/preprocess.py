"""
Module Prétraitement: applies one preprocessing operation to a table's
cached data - rename/drop columns, merge/split columns, filter rows, add a
calculated column, convert a type, encode/normalize/standardize/categorize a
column - overwrites the cache in place, and returns freshly recomputed column
stats (see clean_data.py for the same pattern on the cleaning side).
"""

import sys
from pathlib import Path

import numpy as np
import pandas as pd

sys.path.append(str(Path(__file__).resolve().parent.parent))

from common.io_utils import overwrite_table_cache, read_table_cache  # noqa: E402
from common.json_utils import as_dict, run_script  # noqa: E402
from common.type_detection import detect_column  # noqa: E402

FILTER_OPERATORS = {
    "eq": lambda s, v: s == v,
    "neq": lambda s, v: s != v,
    "gt": lambda s, v: pd.to_numeric(s, errors="coerce") > float(v),
    "gte": lambda s, v: pd.to_numeric(s, errors="coerce") >= float(v),
    "lt": lambda s, v: pd.to_numeric(s, errors="coerce") < float(v),
    "lte": lambda s, v: pd.to_numeric(s, errors="coerce") <= float(v),
    "contains": lambda s, v: s.astype(str).str.contains(str(v), case=False, na=False),
    "is_null": lambda s, v: s.isna(),
    "is_not_null": lambda s, v: s.notna(),
}

TYPE_CONVERTERS = {
    "integer": lambda s: pd.to_numeric(s, errors="coerce").round().astype("Int64"),
    "float": lambda s: pd.to_numeric(s, errors="coerce"),
    "string": lambda s: s.astype(str),
    "date": lambda s: pd.to_datetime(s, errors="coerce", format="mixed").dt.normalize(),
    "datetime": lambda s: pd.to_datetime(s, errors="coerce", format="mixed"),
    "boolean": lambda s: s.astype(str).str.strip().str.lower().isin(["true", "1", "yes", "oui"]),
}


def main(input_data: dict) -> dict:
    df = read_table_cache(input_data["storage_path"])
    operation = input_data["operation"]
    params = as_dict(input_data.get("params"))

    handlers = {
        "rename_column": _rename_column,
        "drop_column": _drop_column,
        "merge": _merge_columns,
        "split": _split_column,
        "filter": _filter_rows,
        "create_column": _create_column,
        "convert_type": _convert_type,
        "encode": _encode,
        "normalize": _normalize,
        "standardize": _standardize,
        "categorize": _categorize,
    }

    if operation not in handlers:
        raise ValueError(f"Unknown preprocessing operation: {operation}")

    df, rows_affected = handlers[operation](df, params)

    overwrite_table_cache(df, input_data["storage_path"])

    columns = [detect_column(df[column]) for column in df.columns]

    return {
        "rows_affected": rows_affected,
        "row_count": len(df),
        "column_count": len(df.columns),
        "columns": columns,
    }


def _rename_column(df: pd.DataFrame, params: dict) -> tuple[pd.DataFrame, None]:
    df = df.rename(columns={params["old_name"]: params["new_name"]})
    return df, None


def _drop_column(df: pd.DataFrame, params: dict) -> tuple[pd.DataFrame, None]:
    df = df.drop(columns=params["columns"])
    return df, None


def _merge_columns(df: pd.DataFrame, params: dict) -> tuple[pd.DataFrame, int]:
    columns = params["columns"]
    separator = params.get("separator", " ")
    new_column = params["new_column"]

    df[new_column] = df[columns].astype(str).agg(separator.join, axis=1)
    return df, len(df)


def _split_column(df: pd.DataFrame, params: dict) -> tuple[pd.DataFrame, int]:
    column = params["column"]
    separator = params.get("separator", " ")
    new_columns = params["new_columns"]

    parts = df[column].astype(str).str.split(separator, n=len(new_columns) - 1, expand=True)
    rows_affected = int(df[column].notna().sum())

    for i, name in enumerate(new_columns):
        df[name] = parts[i] if i in parts.columns else None

    return df, rows_affected


def _filter_rows(df: pd.DataFrame, params: dict) -> tuple[pd.DataFrame, int]:
    column = params["column"]
    operator = params["operator"]
    value = params.get("value")

    if operator not in FILTER_OPERATORS:
        raise ValueError(f"Unknown filter operator: {operator}")

    before = len(df)
    mask = FILTER_OPERATORS[operator](df[column], value)
    df = df[mask].reset_index(drop=True)
    return df, before - len(df)


def _create_column(df: pd.DataFrame, params: dict) -> tuple[pd.DataFrame, int]:
    new_column = params["new_column"]
    expression = params["expression"]

    # df.eval only allows referencing existing columns and arithmetic/
    # comparison operators - no attribute access or arbitrary function
    # calls - so a user-entered formula can't execute arbitrary code.
    df[new_column] = df.eval(expression)
    return df, int(df[new_column].notna().sum())


def _convert_type(df: pd.DataFrame, params: dict) -> tuple[pd.DataFrame, int]:
    column = params["column"]
    target_type = params["target_type"]

    if target_type not in TYPE_CONVERTERS:
        raise ValueError(f"Unknown target type: {target_type}")

    before_notna = df[column].notna()
    df[column] = TYPE_CONVERTERS[target_type](df[column])
    rows_affected = int((before_notna & df[column].notna()).sum())

    return df, rows_affected


def _encode(df: pd.DataFrame, params: dict) -> tuple[pd.DataFrame, int]:
    column = params["column"]
    method = params.get("method", "label")

    if method == "label":
        codes, _ = pd.factorize(df[column])
        df[f"{column}_encoded"] = codes
        return df, len(df)

    if method == "onehot":
        dummies = pd.get_dummies(df[column], prefix=column, dtype=int)
        df = pd.concat([df, dummies], axis=1)
        return df, len(df)

    raise ValueError(f"Unknown encoding method: {method}")


def _normalize(df: pd.DataFrame, params: dict) -> tuple[pd.DataFrame, int]:
    column = params["column"]
    series = pd.to_numeric(df[column], errors="coerce")
    min_val, max_val = series.min(), series.max()

    if max_val == min_val:
        df[f"{column}_norm"] = 0.0
    else:
        df[f"{column}_norm"] = (series - min_val) / (max_val - min_val)

    return df, int(series.notna().sum())


def _standardize(df: pd.DataFrame, params: dict) -> tuple[pd.DataFrame, int]:
    column = params["column"]
    series = pd.to_numeric(df[column], errors="coerce")
    std = series.std()

    df[f"{column}_std"] = 0.0 if std == 0 or pd.isna(std) else (series - series.mean()) / std
    return df, int(series.notna().sum())


def _categorize(df: pd.DataFrame, params: dict) -> tuple[pd.DataFrame, int]:
    column = params["column"]
    bins = params["bins"]
    labels = params.get("labels")

    series = pd.to_numeric(df[column], errors="coerce")
    categories = pd.cut(series, bins=bins, labels=labels)
    df[f"{column}_category"] = categories.astype(str).replace("nan", np.nan)

    return df, int(categories.notna().sum())


if __name__ == "__main__":
    run_script(main)
