"""
Module Export: writes a table's cached data (Parquet) out to whichever
format the user picked (CSV/Excel/JSON) for download. Laravel streams the
resulting file straight back to the browser and deletes the temp copy
afterwards.
"""

import sys
from pathlib import Path

sys.path.append(str(Path(__file__).resolve().parent.parent))

from common.io_utils import read_table_cache  # noqa: E402
from common.json_utils import run_script  # noqa: E402


def main(input_data: dict) -> dict:
    df = read_table_cache(input_data["storage_path"])
    fmt = input_data["format"]

    writers = {
        "csv": lambda path, stem: df.to_csv(path, index=False, encoding="utf-8-sig"),
        "xlsx": lambda path, stem: df.to_excel(path, index=False, sheet_name=stem[:31], engine="openpyxl"),
        "json": lambda path, stem: df.to_json(path, orient="records", indent=2, force_ascii=False),
    }

    if fmt not in writers:
        raise ValueError(f"Unsupported export format: {fmt}")

    output_dir = Path(input_data["output_dir"])
    output_dir.mkdir(parents=True, exist_ok=True)

    stem = "".join(c if c.isalnum() or c in "-_" else "_" for c in input_data["file_stem"])
    path = output_dir / f"{stem}.{fmt}"

    writers[fmt](path, stem)

    return {"file_path": str(path)}


if __name__ == "__main__":
    run_script(main)
