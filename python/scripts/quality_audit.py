"""
Module Audit qualité: computes a 0-100 quality score for a table plus the
detailed findings behind it (duplicates, constant/quasi-empty columns,
highly correlated pairs, outliers). Column-level type/null/distinct stats
reuse the same detection logic as analyze_structure.py so the two never
disagree about what a column looks like.
"""

import sys
from pathlib import Path

import numpy as np
import pandas as pd

sys.path.append(str(Path(__file__).resolve().parent.parent))

from common.io_utils import read_table_cache  # noqa: E402
from common.json_utils import run_script  # noqa: E402
from common.type_detection import detect_column  # noqa: E402

QUASI_EMPTY_THRESHOLD = 95.0
CORRELATION_THRESHOLD = 0.9
OUTLIER_IQR_MULTIPLIER = 1.5


def main(input_data: dict) -> dict:
    df = read_table_cache(input_data["storage_path"])

    total_rows = len(df)
    columns = [detect_column(df[column]) for column in df.columns]

    duplicate_rows = int(df.duplicated().sum())
    duplicate_ratio = (duplicate_rows / total_rows) if total_rows else 0.0

    constant_columns = [c["name"] for c in columns if c["distinct_count"] <= 1]
    quasi_empty_columns = [c["name"] for c in columns if c["null_percentage"] > QUASI_EMPTY_THRESHOLD]
    useless_columns = sorted(set(constant_columns) | set(quasi_empty_columns))

    numeric_columns = [c["name"] for c in columns if c["detected_type"] in ("integer", "float")]
    correlated_pairs = _highly_correlated_pairs(df, numeric_columns)
    outliers = _detect_outliers(df, numeric_columns)

    avg_null_percentage = float(np.mean([c["null_percentage"] for c in columns])) if columns else 0.0
    avg_outlier_ratio = float(np.mean([o["ratio"] for o in outliers])) if outliers else 0.0

    score = _compute_score(
        duplicate_ratio=duplicate_ratio,
        avg_null_percentage=avg_null_percentage,
        useless_columns_count=len(useless_columns),
        avg_outlier_ratio=avg_outlier_ratio,
        correlated_pairs_count=len(correlated_pairs),
    )

    return {
        "score": score,
        "grade": _grade(score),
        "summary": {
            "total_rows": total_rows,
            "total_columns": len(columns),
            "duplicate_rows": duplicate_rows,
            "useless_columns_count": len(useless_columns),
            "constant_columns_count": len(constant_columns),
            "quasi_empty_columns_count": len(quasi_empty_columns),
            "highly_correlated_pairs_count": len(correlated_pairs),
            "avg_null_percentage": round(avg_null_percentage, 2),
        },
        "details": {
            "duplicate_rows": duplicate_rows,
            "duplicate_ratio": round(duplicate_ratio * 100, 2),
            "constant_columns": constant_columns,
            "quasi_empty_columns": quasi_empty_columns,
            "useless_columns": useless_columns,
            "highly_correlated_pairs": correlated_pairs,
            "outliers": outliers,
        },
    }


def _highly_correlated_pairs(df: pd.DataFrame, numeric_columns: list[str]) -> list[dict]:
    if len(numeric_columns) < 2:
        return []

    corr = df[numeric_columns].corr(numeric_only=True).abs()
    pairs = []
    for i, col_a in enumerate(numeric_columns):
        for col_b in numeric_columns[i + 1:]:
            value = corr.loc[col_a, col_b]
            if pd.notna(value) and value >= CORRELATION_THRESHOLD:
                pairs.append({"column_a": col_a, "column_b": col_b, "correlation": round(float(value), 3)})

    return pairs


def _detect_outliers(df: pd.DataFrame, numeric_columns: list[str]) -> list[dict]:
    results = []
    for column in numeric_columns:
        series = pd.to_numeric(df[column], errors="coerce").dropna()
        if series.empty:
            continue

        q1, q3 = series.quantile(0.25), series.quantile(0.75)
        iqr = q3 - q1
        lower = q1 - OUTLIER_IQR_MULTIPLIER * iqr
        upper = q3 + OUTLIER_IQR_MULTIPLIER * iqr
        count = int(((series < lower) | (series > upper)).sum())

        if count > 0:
            results.append({
                "column": column,
                "count": count,
                "ratio": round(count / len(series), 4),
                "lower_bound": round(float(lower), 2),
                "upper_bound": round(float(upper), 2),
            })

    return results


def _compute_score(
    duplicate_ratio: float,
    avg_null_percentage: float,
    useless_columns_count: int,
    avg_outlier_ratio: float,
    correlated_pairs_count: int,
) -> int:
    duplicate_penalty = min(25, round(duplicate_ratio * 100))
    null_penalty = min(25, round(avg_null_percentage / 4))
    useless_penalty = min(20, useless_columns_count * 5)
    outlier_penalty = min(15, round(avg_outlier_ratio * 100))
    correlation_penalty = min(15, correlated_pairs_count * 5)

    score = 100 - duplicate_penalty - null_penalty - useless_penalty - outlier_penalty - correlation_penalty
    return max(0, min(100, round(score)))


def _grade(score: int) -> str:
    if score >= 90:
        return "excellent"
    if score >= 75:
        return "good"
    if score >= 50:
        return "average"
    return "poor"


if __name__ == "__main__":
    run_script(main)
