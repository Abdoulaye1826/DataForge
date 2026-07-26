"""
Module Analyse exploratoire: computes everything a data analyst would want
in one pass over a table - descriptive stats, a full correlation matrix,
histogram bins and boxplot summaries for numeric columns, and value-count
distributions for categorical ones. Bundled into a single Analysis record
per run since it's all produced by the same DataFrame pass.
"""

import sys
from pathlib import Path

import numpy as np
import pandas as pd

sys.path.append(str(Path(__file__).resolve().parent.parent))

from common.io_utils import read_table_cache  # noqa: E402
from common.json_utils import run_script  # noqa: E402
from common.type_detection import is_numeric_series  # noqa: E402

OUTLIER_IQR_MULTIPLIER = 1.5
MAX_OUTLIER_SAMPLES = 20
MAX_DISTRIBUTION_CATEGORIES = 15
HISTOGRAM_BINS = 10


def main(input_data: dict) -> dict:
    df = read_table_cache(input_data["storage_path"])

    numeric_columns = [c for c in df.columns if is_numeric_series(df[c])]
    categorical_columns = [c for c in df.columns if c not in numeric_columns]

    return {
        "descriptive_stats": {c: _descriptive_stats(df[c]) for c in numeric_columns},
        "correlation_matrix": _correlation_matrix(df, numeric_columns),
        "histograms": {c: _histogram(df[c]) for c in numeric_columns},
        "boxplots": {c: _boxplot(df[c]) for c in numeric_columns},
        "distributions": {c: _distribution(df[c]) for c in categorical_columns},
    }


def _descriptive_stats(series: pd.Series) -> dict:
    numeric = pd.to_numeric(series, errors="coerce").dropna()
    if numeric.empty:
        return {}

    mode = numeric.mode()

    return {
        "count": int(numeric.count()),
        "mean": float(numeric.mean()),
        "median": float(numeric.median()),
        "mode": float(mode.iloc[0]) if not mode.empty else None,
        "variance": float(numeric.var()) if len(numeric) > 1 else 0.0,
        "std": float(numeric.std()) if len(numeric) > 1 else 0.0,
        "min": float(numeric.min()),
        "max": float(numeric.max()),
        "q1": float(numeric.quantile(0.25)),
        "q3": float(numeric.quantile(0.75)),
    }


def _correlation_matrix(df: pd.DataFrame, numeric_columns: list[str]) -> dict:
    if len(numeric_columns) < 2:
        return {"labels": numeric_columns, "matrix": []}

    corr = df[numeric_columns].corr(numeric_only=True).round(3)
    matrix = corr.values.tolist()
    matrix = [[None if pd.isna(v) else v for v in row] for row in matrix]

    return {"labels": numeric_columns, "matrix": matrix}


def _histogram(series: pd.Series) -> dict:
    numeric = pd.to_numeric(series, errors="coerce").dropna()
    if numeric.empty:
        return {"bins": [], "counts": []}

    counts, edges = np.histogram(numeric, bins=HISTOGRAM_BINS)
    labels = [f"{edges[i]:.2f} – {edges[i + 1]:.2f}" for i in range(len(edges) - 1)]

    return {"bins": labels, "counts": counts.tolist()}


def _boxplot(series: pd.Series) -> dict:
    numeric = pd.to_numeric(series, errors="coerce").dropna()
    if numeric.empty:
        return {}

    q1, median, q3 = numeric.quantile(0.25), numeric.median(), numeric.quantile(0.75)
    iqr = q3 - q1
    lower, upper = q1 - OUTLIER_IQR_MULTIPLIER * iqr, q3 + OUTLIER_IQR_MULTIPLIER * iqr
    outliers = numeric[(numeric < lower) | (numeric > upper)]

    return {
        "min": float(numeric.min()),
        "q1": float(q1),
        "median": float(median),
        "q3": float(q3),
        "max": float(numeric.max()),
        "outliers": outliers.head(MAX_OUTLIER_SAMPLES).tolist(),
    }


def _distribution(series: pd.Series) -> dict:
    non_null = series.dropna()
    if non_null.empty:
        return {"categories": [], "counts": []}

    counts = non_null.astype(str).value_counts().head(MAX_DISTRIBUTION_CATEGORIES)

    return {"categories": counts.index.tolist(), "counts": counts.values.tolist()}


if __name__ == "__main__":
    run_script(main)
