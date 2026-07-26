"""
Module "Gestion des Excel multi-feuilles": for a dataset with several tables
(typically one per Excel sheet), suggests candidate join keys between them by
combining column-name similarity with actual value overlap. Suggestions are
never applied automatically - Laravel stores them as status=suggested and the
user confirms or rejects each one (see RelationshipDetectionService).
"""

import re
import sys
from pathlib import Path

sys.path.append(str(Path(__file__).resolve().parent.parent))

from common.io_utils import read_table_cache  # noqa: E402
from common.json_utils import run_script  # noqa: E402

NAME_WEIGHT = 0.4
OVERLAP_WEIGHT = 0.6
MIN_CONFIDENCE = 0.5
MIN_OVERLAP = 0.3


def main(input_data: dict) -> dict:
    tables = input_data["tables"]  # [{"id", "name", "storage_path"}, ...]
    frames = {t["id"]: (t["name"], read_table_cache(t["storage_path"])) for t in tables}

    candidates = []
    ids = list(frames.keys())
    for i, source_id in enumerate(ids):
        source_name, source_df = frames[source_id]
        for target_id in ids[i + 1:]:
            target_name, target_df = frames[target_id]
            candidates.extend(_match_columns(
                source_id, source_name, source_df,
                target_id, target_name, target_df,
            ))

    return {"relationships": _keep_best_per_source_column(candidates)}


def _match_columns(source_id, source_name, source_df, target_id, target_name, target_df) -> list[dict]:
    results = []

    for source_col in source_df.columns:
        source_series = source_df[source_col].dropna()
        if source_series.nunique() < 2:
            continue

        for target_col in target_df.columns:
            target_series = target_df[target_col].dropna()
            if target_series.nunique() < 2:
                continue

            name_score = _name_similarity(source_col, source_name, target_col, target_name)
            overlap = _value_overlap(source_series, target_series)
            confidence = NAME_WEIGHT * name_score + OVERLAP_WEIGHT * overlap

            if confidence >= MIN_CONFIDENCE and overlap >= MIN_OVERLAP:
                results.append({
                    "source_table_id": source_id,
                    "source_column": source_col,
                    "target_table_id": target_id,
                    "target_column": target_col,
                    "confidence_score": round(confidence, 3),
                    "relationship_type": _relationship_type(source_series, target_series),
                })

    return results


def _normalize(name: str) -> str:
    return re.sub(r"[^a-z0-9]", "", name.lower())


def _name_similarity(source_col: str, source_table: str, target_col: str, target_table: str) -> float:
    a, b = _normalize(source_col), _normalize(target_col)

    if a == b:
        return 1.0

    entity_a = re.sub(r"id$", "", a)
    entity_b = re.sub(r"id$", "", b)
    table_a = _normalize(source_table).rstrip("s")
    table_b = _normalize(target_table).rstrip("s")

    if entity_a and (entity_a == table_b or entity_a in table_b or table_b in entity_a):
        return 0.8
    if entity_b and (entity_b == table_a or entity_b in table_a or table_a in entity_b):
        return 0.8
    if a in b or b in a:
        return 0.5

    return 0.0


def _value_overlap(source_series, target_series) -> float:
    source_values = set(source_series.astype(str).unique())
    target_values = set(target_series.astype(str).unique())

    if not source_values or not target_values:
        return 0.0

    return len(source_values & target_values) / len(source_values)


def _relationship_type(source_series, target_series) -> str:
    source_unique = source_series.is_unique
    target_unique = target_series.is_unique

    if source_unique and target_unique:
        return "one_to_one"
    if source_unique != target_unique:
        return "one_to_many"
    return "many_to_many"


def _keep_best_per_source_column(candidates: list[dict]) -> list[dict]:
    best: dict[tuple, dict] = {}
    for candidate in candidates:
        key = (candidate["source_table_id"], candidate["source_column"])
        if key not in best or candidate["confidence_score"] > best[key]["confidence_score"]:
            best[key] = candidate

    return list(best.values())


if __name__ == "__main__":
    run_script(main)
