"""
Column type detection shared by analyze_structure.py today and, later,
quality_audit.py / preprocess.py (type conversion suggestions). Kept in one
place so "what counts as a date/boolean/category" never drifts between
scripts.
"""

import numpy as np
import pandas as pd

BOOLEAN_TOKENS = {"true", "false", "1", "0", "yes", "no", "oui", "non"}
CATEGORY_MAX_DISTINCT = 50
CATEGORY_MAX_RATIO = 0.5
PARSE_SUCCESS_THRESHOLD = 0.9


def is_numeric_series(series: pd.Series) -> bool:
    """pandas' own is_numeric_dtype() counts booleans as numeric (bool is a
    numpy integer subtype), but treating a boolean column as numeric breaks
    the moment anything calls .quantile()/.std() on it - numpy refuses `-`
    between bools during quantile interpolation. Every script that builds a
    "numeric columns" list for stats/correlation/charts should use this
    instead of calling pd.api.types.is_numeric_dtype() directly."""
    return pd.api.types.is_numeric_dtype(series) and not pd.api.types.is_bool_dtype(series)


def detect_column(series: pd.Series) -> dict:
    total = len(series)
    non_null = series.dropna()
    null_count = int(total - len(non_null))
    distinct_count = int(non_null.nunique())

    detected_type = _detect_type(series, non_null, distinct_count, total)
    stats = _compute_stats(non_null, detected_type)

    return {
        "name": str(series.name),
        "detected_type": detected_type,
        "null_count": null_count,
        "null_percentage": round((null_count / total * 100) if total else 0.0, 2),
        "distinct_count": distinct_count,
        "sample_values": _sample_values(non_null),
        "stats": stats,
        "is_useless": bool(total > 0 and (null_count / total > 0.95 or distinct_count <= 1)),
    }


def _detect_type(series: pd.Series, non_null: pd.Series, distinct_count: int, total: int) -> str:
    if pd.api.types.is_bool_dtype(series):
        return "boolean"

    if pd.api.types.is_integer_dtype(series):
        return "integer"

    if pd.api.types.is_float_dtype(series):
        return "float"

    if pd.api.types.is_datetime64_any_dtype(series):
        return "date" if _all_midnight(non_null) else "datetime"

    if non_null.empty:
        return "unknown"

    # object dtype: sniff out booleans, dates, and numbers hiding as strings.
    lowered = non_null.astype(str).str.strip().str.lower()

    if distinct_count <= 2 and set(lowered.unique()).issubset(BOOLEAN_TOKENS):
        return "boolean"

    # Numbers before dates: pd.to_datetime() never fails on a numeric
    # column - it silently reinterprets each value as a nanosecond-since-
    # epoch offset (25 becomes 1970-01-01T00:00:00.000000025), so checking
    # dates first would misdetect any numeric column stored as object dtype
    # (common with Excel exports, thousands separators, stray blanks...) as
    # a date/datetime column instead of integer/float.
    parsed_numbers = pd.to_numeric(non_null, errors="coerce")
    if parsed_numbers.notna().mean() >= PARSE_SUCCESS_THRESHOLD:
        return "integer" if (parsed_numbers.dropna() % 1 == 0).all() else "float"

    parsed_dates = pd.to_datetime(non_null, errors="coerce", format="mixed")
    if parsed_dates.notna().mean() >= PARSE_SUCCESS_THRESHOLD:
        return "date" if _all_midnight(parsed_dates.dropna()) else "datetime"

    if distinct_count <= CATEGORY_MAX_DISTINCT and (total == 0 or distinct_count / total <= CATEGORY_MAX_RATIO):
        return "category"

    return "string"


def _all_midnight(series: pd.Series) -> bool:
    if series.empty:
        return False
    times = pd.to_datetime(series).dt.time
    return bool((times == pd.Timestamp("00:00:00").time()).all())


def _compute_stats(non_null: pd.Series, detected_type: str) -> dict:
    if non_null.empty:
        return {}

    if detected_type in ("integer", "float"):
        numeric = pd.to_numeric(non_null, errors="coerce").dropna()
        if numeric.empty:
            return {}
        return {
            "min": float(numeric.min()),
            "max": float(numeric.max()),
            "mean": float(numeric.mean()),
            "median": float(numeric.median()),
            "std": float(numeric.std()) if len(numeric) > 1 else 0.0,
            "q1": float(numeric.quantile(0.25)),
            "q3": float(numeric.quantile(0.75)),
        }

    if detected_type in ("date", "datetime"):
        parsed = pd.to_datetime(non_null, errors="coerce").dropna()
        if parsed.empty:
            return {}
        return {"min": parsed.min().isoformat(), "max": parsed.max().isoformat()}

    # category / string / boolean: most frequent values.
    counts = non_null.astype(str).value_counts().head(10)
    return {"top_values": {str(k): int(v) for k, v in counts.items()}}


def _sample_values(non_null: pd.Series, limit: int = 5) -> list:
    values = non_null.drop_duplicates().head(limit).tolist()
    return [v.isoformat() if hasattr(v, "isoformat") else v for v in values]
