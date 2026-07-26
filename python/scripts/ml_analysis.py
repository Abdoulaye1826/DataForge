"""
Module Machine Learning: the two scikit-learn analyses a data analyst reaches
for beyond descriptive EDA - K-Means segmentation (group similar rows) and a
simple linear trend forecast (extrapolate a numeric series over time).
Neither needs a persisted model: both run fresh against the table's cached
data every time and store just their result.
"""

import sys
from pathlib import Path

import numpy as np
import pandas as pd
from sklearn.cluster import KMeans
from sklearn.decomposition import PCA
from sklearn.linear_model import LinearRegression
from sklearn.preprocessing import StandardScaler

sys.path.append(str(Path(__file__).resolve().parent.parent))

from common.io_utils import read_table_cache  # noqa: E402
from common.json_utils import as_dict, run_script  # noqa: E402
from common.type_detection import is_numeric_series  # noqa: E402

MAX_CLUSTERS = 10
MAX_FORECAST_PERIODS = 24


def main(input_data: dict) -> dict:
    df = read_table_cache(input_data["storage_path"])
    config = as_dict(input_data.get("config"))
    analysis_type = input_data["analysis_type"]

    handlers = {"clustering": _clustering, "forecast": _forecast}

    if analysis_type not in handlers:
        raise ValueError(f"Unknown ML analysis type: {analysis_type}")

    return handlers[analysis_type](df, config)


def _clustering(df: pd.DataFrame, config: dict) -> dict:
    columns = config["columns"]
    n_clusters = min(int(config["n_clusters"]), MAX_CLUSTERS)

    if len(columns) < 2:
        raise ValueError("Il faut au moins 2 colonnes numériques pour visualiser des clusters.")

    subset = df[columns].apply(pd.to_numeric, errors="coerce").dropna()

    if len(subset) < n_clusters:
        raise ValueError("Pas assez de lignes valides pour ce nombre de clusters.")

    scaled = StandardScaler().fit_transform(subset)

    kmeans = KMeans(n_clusters=n_clusters, n_init=10, random_state=42)
    labels = kmeans.fit_predict(scaled)

    subset = subset.assign(__cluster__=labels)
    cluster_sizes = subset["__cluster__"].value_counts().sort_index()
    cluster_means = subset.groupby("__cluster__")[columns].mean().round(3)

    if len(columns) == 2:
        coords, axis_labels = scaled, columns
    else:
        coords = PCA(n_components=2, random_state=42).fit_transform(scaled)
        axis_labels = ["Composante 1", "Composante 2"]

    groups = []
    for cluster_id in sorted(cluster_sizes.index):
        mask = labels == cluster_id
        points = [{"x": round(float(a), 4), "y": round(float(b), 4)} for a, b in coords[mask]]
        groups.append({"label": f"Cluster {cluster_id}", "points": points})

    return {
        "n_clusters": n_clusters,
        "columns": columns,
        "inertia": round(float(kmeans.inertia_), 4),
        "cluster_sizes": {str(k): int(v) for k, v in cluster_sizes.items()},
        "cluster_means": {str(k): row.to_dict() for k, row in cluster_means.iterrows()},
        "scatter": {"groups": groups, "axis_labels": list(axis_labels)},
    }


def _forecast(df: pd.DataFrame, config: dict) -> dict:
    period_column, value_column = config["date_column"], config["value_column"]
    periods = min(int(config["periods"]), MAX_FORECAST_PERIODS)

    series = df[[period_column, value_column]].copy()
    series[value_column] = pd.to_numeric(series[value_column], errors="coerce")

    # Not every "time" axis is a calendar date - a lot of real datasets use a
    # plain sequential period number instead (week 1, 2, 3... / month 1-12).
    # pd.to_datetime() on a numeric column doesn't fail - it silently
    # reinterprets the numbers as epoch nanoseconds, which is never what's
    # wanted here - so numeric columns always take the ordinal path, and
    # only non-numeric columns get a chance at being parsed as real dates.
    if is_numeric_series(df[period_column]):
        is_date = False
        series[period_column] = pd.to_numeric(series[period_column], errors="coerce")
    else:
        parsed_dates = pd.to_datetime(series[period_column], errors="coerce")
        is_date = parsed_dates.notna().mean() > 0.8
        series[period_column] = parsed_dates if is_date else pd.to_numeric(series[period_column], errors="coerce")
    series = series.dropna().sort_values(period_column)
    series = series.groupby(period_column, as_index=False)[value_column].mean()

    if len(series) < 3:
        raise ValueError("Pas assez de points de données pour une prévision (minimum 3).")

    x = np.arange(len(series)).reshape(-1, 1)
    y = series[value_column].to_numpy()

    model = LinearRegression().fit(x, y)
    r2 = model.score(x, y)

    deltas = series[period_column].diff().dropna()
    step = deltas.median() if not deltas.empty else (pd.Timedelta(days=1) if is_date else 1)

    future_x = np.arange(len(series), len(series) + periods).reshape(-1, 1)
    future_y = model.predict(future_x)
    last_period = series[period_column].iloc[-1]
    future_periods = [last_period + step * (i + 1) for i in range(periods)]

    if is_date:
        historical_labels = series[period_column].dt.strftime("%Y-%m-%d").tolist()
        future_labels = [p.strftime("%Y-%m-%d") for p in future_periods]
    else:
        historical_labels = [_format_period(p) for p in series[period_column]]
        future_labels = [_format_period(p) for p in future_periods]

    labels = historical_labels + future_labels
    historical = [round(float(v), 4) for v in y] + [None] * periods
    # Repeat the last historical point as the forecast series' starting
    # anchor so the two line segments connect visually with no gap.
    forecast = [None] * (len(y) - 1) + [round(float(y[-1]), 4)] + [round(float(v), 4) for v in future_y]

    slope = float(model.coef_[0])

    return {
        "labels": labels,
        "datasets": [
            {"label": "Historique", "data": historical},
            {"label": "Prévision", "data": forecast},
        ],
        "slope": round(slope, 4),
        "r2": round(float(r2), 4),
        "trend": "hausse" if slope > 0 else ("baisse" if slope < 0 else "stable"),
    }


def _format_period(value: float) -> str:
    return str(int(value)) if float(value).is_integer() else f"{value:.2f}"


if __name__ == "__main__":
    run_script(main)
