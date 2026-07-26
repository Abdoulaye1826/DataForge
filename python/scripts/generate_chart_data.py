"""
Module Visualisations: aggregates a table's cached data into whatever shape
the chosen chart type needs for rendering (Chart.js for bar/line/pie/donut/
scatter/histogram/radar, ApexCharts for heatmap/treemap/boxplot - see
ChartType::usesApexCharts()).
"""

import sys
from pathlib import Path

import numpy as np
import pandas as pd

sys.path.append(str(Path(__file__).resolve().parent.parent))

from common.io_utils import read_table_cache  # noqa: E402
from common.json_utils import as_dict, run_script  # noqa: E402
from common.type_detection import is_numeric_series  # noqa: E402

MAX_SCATTER_POINTS = 1000
MAX_RADAR_CATEGORIES = 6
HISTOGRAM_BINS = 10


def main(input_data: dict) -> dict:
    df = read_table_cache(input_data["storage_path"])
    df = _apply_filter(df, as_dict(input_data.get("filter")))
    chart_type = input_data["chart_type"]
    config = as_dict(input_data.get("config"))

    handlers = {
        "bar": _bar_or_line,
        "line": _bar_or_line,
        "pie": _pie_or_donut,
        "donut": _pie_or_donut,
        "scatter": _scatter,
        "histogram": _histogram,
        "heatmap": _heatmap,
        "radar": _radar,
        "treemap": _treemap,
        "boxplot": _boxplot,
    }

    if chart_type not in handlers:
        raise ValueError(f"Unknown chart type: {chart_type}")

    return handlers[chart_type](df, config)


def _apply_filter(df: pd.DataFrame, filter_spec: dict) -> pd.DataFrame:
    """Module Dashboard: the dashboard's global filter bar re-requests a
    widget's chart data with this applied - a no-op whenever the filtered
    column doesn't exist on this particular table, since not every widget on
    a dashboard necessarily comes from the same table as the one being
    filtered on."""
    column = filter_spec.get("column")

    if not column or column not in df.columns:
        return df

    operator = filter_spec.get("operator")

    if operator == "eq":
        return df[df[column].astype(str) == str(filter_spec.get("value"))]

    if operator == "between":
        series = pd.to_datetime(df[column], errors="coerce")
        mask = pd.Series(True, index=df.index)

        start = filter_spec.get("start")
        end = filter_spec.get("end")
        if start:
            mask &= series >= pd.to_datetime(start)
        if end:
            mask &= series <= pd.to_datetime(end)

        return df[mask]

    return df


def _aggregate(df: pd.DataFrame, group_column: str, value_column: str | None, aggregation: str) -> pd.Series:
    if value_column is None:
        return df.groupby(group_column, dropna=True).size()

    numeric = pd.to_numeric(df[value_column], errors="coerce")
    grouped = numeric.groupby(df[group_column])

    return {"sum": grouped.sum, "mean": grouped.mean, "count": grouped.count}[aggregation]()


def _bar_or_line(df: pd.DataFrame, config: dict) -> dict:
    series = _aggregate(df, config["x_column"], config.get("y_column"), config.get("aggregation", "count"))
    series = series.sort_index()

    return {"labels": series.index.astype(str).tolist(), "data": [round(float(v), 4) for v in series.values]}


def _pie_or_donut(df: pd.DataFrame, config: dict) -> dict:
    series = _aggregate(df, config["category_column"], config.get("value_column"), config.get("aggregation", "count"))
    series = series.sort_values(ascending=False)

    return {"labels": series.index.astype(str).tolist(), "data": [round(float(v), 4) for v in series.values]}


def _scatter(df: pd.DataFrame, config: dict) -> dict:
    subset = df[[config["x_column"], config["y_column"]]].dropna()
    subset = subset.head(MAX_SCATTER_POINTS)

    x = pd.to_numeric(subset[config["x_column"]], errors="coerce")
    y = pd.to_numeric(subset[config["y_column"]], errors="coerce")

    points = [{"x": round(float(a), 4), "y": round(float(b), 4)} for a, b in zip(x, y) if pd.notna(a) and pd.notna(b)]

    return {"points": points}


def _histogram(df: pd.DataFrame, config: dict) -> dict:
    numeric = pd.to_numeric(df[config["column"]], errors="coerce").dropna()
    bins = int(config.get("bins", HISTOGRAM_BINS))

    if numeric.empty:
        return {"labels": [], "counts": []}

    counts, edges = np.histogram(numeric, bins=bins)
    labels = [f"{edges[i]:.2f} – {edges[i + 1]:.2f}" for i in range(len(edges) - 1)]

    return {"labels": labels, "counts": counts.tolist()}


def _heatmap(df: pd.DataFrame, config: dict) -> dict:
    columns = config.get("columns") or [c for c in df.columns if is_numeric_series(df[c])]

    if len(columns) < 2:
        return {"labels": columns, "matrix": []}

    corr = df[columns].corr(numeric_only=True).round(3)
    matrix = [[None if pd.isna(v) else v for v in row] for row in corr.values.tolist()]

    return {"labels": columns, "matrix": matrix}


def _radar(df: pd.DataFrame, config: dict) -> dict:
    category_column = config["category_column"]
    value_columns = config["value_columns"]

    grouped = df.groupby(category_column)[value_columns].mean(numeric_only=True)
    grouped = grouped.head(MAX_RADAR_CATEGORIES)

    datasets = [
        {"label": str(category), "data": [round(float(v), 4) if pd.notna(v) else 0 for v in row]}
        for category, row in grouped.iterrows()
    ]

    return {"labels": value_columns, "datasets": datasets}


def _treemap(df: pd.DataFrame, config: dict) -> dict:
    series = _aggregate(df, config["category_column"], config.get("value_column"), config.get("aggregation", "count"))
    series = series.sort_values(ascending=False)

    return {"data": [{"name": str(k), "value": round(float(v), 4)} for k, v in series.items()]}


def _boxplot(df: pd.DataFrame, config: dict) -> dict:
    columns = config["columns"]
    data = []

    for column in columns:
        numeric = pd.to_numeric(df[column], errors="coerce").dropna()
        if numeric.empty:
            continue

        q1, median, q3 = numeric.quantile(0.25), numeric.median(), numeric.quantile(0.75)
        data.append({
            "x": column,
            "y": [round(float(numeric.min()), 4), round(float(q1), 4), round(float(median), 4), round(float(q3), 4), round(float(numeric.max()), 4)],
        })

    return {"data": data}


if __name__ == "__main__":
    run_script(main)
