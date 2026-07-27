"""
Uniform dataset reading so every script deals with plain pandas DataFrames
regardless of the source format the user imported (CSV/Excel/JSON/Parquet).

An Excel file can hold several sheets, each becoming its own "table" (see
DatasetTable in Laravel) - every other format yields exactly one table named
after the source file.
"""

import csv
from pathlib import Path

import pandas as pd
from charset_normalizer import from_path

DEFAULT_ENCODING = "utf-8"
SNIFFER_SAMPLE_BYTES = 65536

# Legacy single-byte Western European codepages (cp1252, iso8859_1...) are
# statistically near-indistinguishable from their Central/Eastern European
# siblings (cp1250, cp1257...) on short accented-text samples - they share
# most byte positions and only disagree on a handful of characters, so
# charset_normalizer often returns several candidates tied on chaos/
# coherence. Preferring cp1252 among ties is the right call for this app's
# actual data (French/Western-European business exports, per the delimiter
# comment below), rather than accepting whichever tied candidate happens to
# sort first.
PREFERRED_TIEBREAK_ENCODING = "cp1252"


def detect_csv_encoding(source_path: str) -> str:
    """Best-guess encoding for a CSV file, so a Windows-1252/Latin-1 export
    (common from French-locale Excel/ERP tools) doesn't crash the import with
    a UnicodeDecodeError the way a hardcoded utf-8 assumption would."""
    matches = from_path(source_path)
    best_match = matches.best()

    if best_match is None:
        return DEFAULT_ENCODING

    tied = [m for m in matches if (m.chaos, m.coherence) == (best_match.chaos, best_match.coherence)]

    if any(m.encoding == PREFERRED_TIEBREAK_ENCODING for m in tied):
        return PREFERRED_TIEBREAK_ENCODING

    # Pure ASCII is valid UTF-8 - report the more familiar/expected label
    # rather than a technically-correct-but-confusing "ascii" in the UI.
    return "utf-8" if best_match.encoding == "ascii" else best_match.encoding


def detect_csv_delimiter(source_path: str, encoding: str) -> str:
    """csv.Sniffer needs a decoded text sample - falls back to comma if the
    sample is too small/ambiguous for it to make a confident guess."""
    try:
        with open(source_path, encoding=encoding, errors="replace") as handle:
            sample = handle.read(SNIFFER_SAMPLE_BYTES)

        return csv.Sniffer().sniff(sample, delimiters=",;\t|").delimiter
    except (csv.Error, OSError):
        return ","


def read_source_tables(
    source_path: str, fmt: str, default_name: str | None = None
) -> tuple[dict[str, pd.DataFrame], dict[str, str | int | None]]:
    """Returns ({table_name: DataFrame}, file_meta) - one table entry per
    Excel sheet, or a single entry keyed by default_name (falling back to the
    file's stem) for every other format. default_name matters because
    source_path usually points at a hashed storage filename, not the name the
    user uploaded. file_meta surfaces what was detected about the source file
    itself (encoding/delimiter for CSV, sheet count for Excel) for the
    Dataset Intelligence report - null for whichever doesn't apply to fmt."""
    stem = default_name or Path(source_path).stem
    file_meta: dict[str, str | int | None] = {
        "encoding": None,
        "delimiter": None,
        "sheet_count": None,
    }

    if fmt == "csv":
        encoding = detect_csv_encoding(source_path)
        delimiter = detect_csv_delimiter(source_path, encoding)
        file_meta["encoding"] = encoding
        file_meta["delimiter"] = delimiter

        # Passing the already-sniffed encoding/delimiter explicitly (rather
        # than sep=None + engine="python") means the reported file_meta is
        # guaranteed to match what was actually parsed, not a second
        # independent guess - and it fixes non-UTF8 CSVs (Windows-1252/
        # Latin-1 exports) that previously crashed on the hardcoded utf-8
        # default.
        tables = {stem: pd.read_csv(source_path, sep=delimiter, encoding=encoding)}

        return tables, file_meta

    if fmt == "xlsx":
        sheets = pd.read_excel(source_path, sheet_name=None)
        file_meta["sheet_count"] = len(sheets)

        return dict(sheets), file_meta

    if fmt == "json":
        return {stem: pd.read_json(source_path)}, file_meta

    if fmt == "parquet":
        return {stem: pd.read_parquet(source_path)}, file_meta

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
