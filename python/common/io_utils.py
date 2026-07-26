"""
Uniform dataset reading so every script deals with plain pandas DataFrames
regardless of the source format the user imported (CSV/Excel/JSON/Parquet).

An Excel file can hold several sheets, each becoming its own "table" (see
DatasetTable in Laravel) - every other format yields exactly one table named
after the source file.
"""

from pathlib import Path

import pandas as pd


def read_source_tables(source_path: str, fmt: str, default_name: str | None = None) -> dict[str, pd.DataFrame]:
    """Returns {table_name: DataFrame} - one entry per Excel sheet, or a
    single entry keyed by default_name (falling back to the file's stem) for
    every other format. default_name matters because source_path usually
    points at a hashed storage filename, not the name the user uploaded."""
    stem = default_name or Path(source_path).stem

    if fmt == "csv":
        # sep=None + the python engine auto-detects the delimiter (comma,
        # semicolon, tab...) via csv.Sniffer instead of assuming comma -
        # semicolon-delimited exports (common from French-locale Excel/ERP
        # tools) would otherwise come back as a single unsplit column.
        return {stem: pd.read_csv(source_path, sep=None, engine="python")}

    if fmt == "xlsx":
        sheets = pd.read_excel(source_path, sheet_name=None)
        return dict(sheets)

    if fmt == "json":
        return {stem: pd.read_json(source_path)}

    if fmt == "parquet":
        return {stem: pd.read_parquet(source_path)}

    raise ValueError(f"Unsupported format: {fmt}")


def write_table_cache(df: pd.DataFrame, output_dir: str, table_name: str) -> str:
    """Persists a table as Parquet so later pipeline steps (cleaning, EDA...)
    can re-read it quickly without re-parsing the original file. Returns the
    absolute path written, which becomes DatasetTable.storage_path."""
    Path(output_dir).mkdir(parents=True, exist_ok=True)
    safe_name = "".join(c if c.isalnum() or c in "-_" else "_" for c in table_name)
    path = str(Path(output_dir) / f"{safe_name}.parquet")
    df.to_parquet(path, index=False)
    return path


def read_table_cache(storage_path: str) -> pd.DataFrame:
    return pd.read_parquet(storage_path)


def overwrite_table_cache(df: pd.DataFrame, storage_path: str) -> None:
    """Persists a table back to its existing cache path - used by every
    pipeline step (cleaning/preprocessing) so a table's storage_path never
    changes, only its contents."""
    df.to_parquet(storage_path, index=False)
