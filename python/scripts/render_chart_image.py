"""
Module Rapport: renders a batch of already-computed chart datasets (the same
shapes generate_chart_data.py produces) into static PNG images, base64-encoded
for embedding in the PDF report. dompdf has no JS engine, so the Chart.js/
ApexCharts canvases shown on screen can't be reused - Matplotlib is the only
part of the mandated stack that can rasterize a chart headlessly.
"""

import base64
import io
import sys
from pathlib import Path

import matplotlib

matplotlib.use("Agg")

import matplotlib.pyplot as plt  # noqa: E402
import numpy as np  # noqa: E402

sys.path.append(str(Path(__file__).resolve().parent.parent))

from common.json_utils import run_script  # noqa: E402

FIGSIZE = (6, 3.5)
DPI = 120


def main(input_data: dict) -> dict:
    images = []

    for chart in input_data["charts"]:
        fig = _render(chart["chart_type"], chart["data"], chart.get("name", ""))
        images.append({"name": chart.get("name", ""), "base64": _to_base64(fig)})
        plt.close(fig)

    return {"images": images}


def _to_base64(fig) -> str:
    buffer = io.BytesIO()
    fig.savefig(buffer, format="png", dpi=DPI, bbox_inches="tight")
    buffer.seek(0)
    return base64.b64encode(buffer.read()).decode("ascii")


def _new_figure(title: str):
    fig, ax = plt.subplots(figsize=FIGSIZE)
    if title:
        ax.set_title(title, fontsize=10)
    return fig, ax


def _render(chart_type: str, data: dict, title: str):
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

    return handlers[chart_type](data, title)


def _bar_or_line(data: dict, title: str):
    fig, ax = _new_figure(title)
    labels, values = data.get("labels", []), data.get("data", [])

    if not labels:
        return fig

    ax.bar(labels, values) if len(labels) <= 20 else ax.plot(labels, values)
    ax.tick_params(axis="x", labelrotation=45, labelsize=7)
    return fig


def _pie_or_donut(data: dict, title: str):
    fig, ax = _new_figure(title)
    labels, values = data.get("labels", []), data.get("data", [])

    if not labels:
        return fig

    wedge_width = 0.4 if title.lower().startswith("donut") else 1.0
    ax.pie(values, labels=labels, autopct="%1.0f%%", textprops={"fontsize": 7},
           wedgeprops={"width": wedge_width})
    return fig


def _scatter(data: dict, title: str):
    fig, ax = _new_figure(title)
    points = data.get("points", [])

    if not points:
        return fig

    ax.scatter([p["x"] for p in points], [p["y"] for p in points], s=10, alpha=0.6)
    return fig


def _histogram(data: dict, title: str):
    fig, ax = _new_figure(title)
    labels, counts = data.get("labels", []), data.get("counts", [])

    if not labels:
        return fig

    ax.bar(range(len(labels)), counts, width=1.0, edgecolor="white")
    ax.set_xticks(range(len(labels)))
    ax.set_xticklabels(labels, rotation=45, fontsize=6, ha="right")
    return fig


def _heatmap(data: dict, title: str):
    fig, ax = _new_figure(title)
    labels, matrix = data.get("labels", []), data.get("matrix", [])

    if not matrix:
        return fig

    grid = np.array([[np.nan if v is None else v for v in row] for row in matrix])
    im = ax.imshow(grid, cmap="RdBu_r", vmin=-1, vmax=1)
    ax.set_xticks(range(len(labels)))
    ax.set_yticks(range(len(labels)))
    ax.set_xticklabels(labels, rotation=45, fontsize=6, ha="right")
    ax.set_yticklabels(labels, fontsize=6)

    for i in range(len(labels)):
        for j in range(len(labels)):
            if not np.isnan(grid[i, j]):
                ax.text(j, i, f"{grid[i, j]:.2f}", ha="center", va="center", fontsize=6)

    fig.colorbar(im, ax=ax, shrink=0.8)
    return fig


def _radar(data: dict, title: str):
    labels = data.get("labels", [])
    datasets = data.get("datasets", [])

    if not labels or not datasets:
        return _new_figure(title)[0]

    angles = np.linspace(0, 2 * np.pi, len(labels), endpoint=False).tolist()
    angles += angles[:1]

    fig = plt.figure(figsize=FIGSIZE)
    ax = fig.add_subplot(111, polar=True)
    if title:
        ax.set_title(title, fontsize=10)

    for dataset in datasets:
        values = dataset["data"] + dataset["data"][:1]
        ax.plot(angles, values, linewidth=1, label=dataset.get("label"))
        ax.fill(angles, values, alpha=0.1)

    ax.set_xticks(angles[:-1])
    ax.set_xticklabels(labels, fontsize=6)
    ax.legend(fontsize=6, loc="upper right", bbox_to_anchor=(1.3, 1.1))
    return fig


def _treemap(data: dict, title: str):
    # No treemap primitive in Matplotlib without extra deps (squarify) outside
    # the mandated stack - a sorted horizontal bar chart conveys the same
    # "share of total" information for a static report image.
    fig, ax = _new_figure(title)
    items = sorted(data.get("data", []), key=lambda item: item["value"], reverse=True)

    if not items:
        return fig

    names = [item["name"] for item in items]
    values = [item["value"] for item in items]
    ax.barh(names, values)
    ax.invert_yaxis()
    ax.tick_params(axis="y", labelsize=7)
    return fig


def _boxplot(data: dict, title: str):
    fig, ax = _new_figure(title)
    items = data.get("data", [])

    if not items:
        return fig

    stats = [
        {
            "label": item["x"],
            "whislo": item["y"][0],
            "q1": item["y"][1],
            "med": item["y"][2],
            "q3": item["y"][3],
            "whishi": item["y"][4],
            "fliers": [],
        }
        for item in items
    ]
    ax.bxp(stats, showfliers=False)
    ax.tick_params(axis="x", labelrotation=45, labelsize=7)
    return fig


if __name__ == "__main__":
    run_script(main)
