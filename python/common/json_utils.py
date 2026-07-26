"""
JSON helpers shared by every DataForge script.

Laravel's PythonRunnerService always talks to scripts through two JSON files:
an --input file it writes before launching the process, and an --output file
the script must write before exiting. This module is the only place that
knows that contract, so every script reads/writes it the same way.
"""

import argparse
import json
from datetime import date, datetime
from pathlib import Path


def parse_io_args() -> argparse.Namespace:
    """Every script accepts exactly --input and --output file paths."""
    parser = argparse.ArgumentParser()
    parser.add_argument("--input", required=True)
    parser.add_argument("--output", required=True)
    return parser.parse_args()


def read_input(path: str) -> dict:
    with open(path, "r", encoding="utf-8") as f:
        return json.load(f)


def as_dict(value) -> dict:
    """PHP's json_encode() has no way to tell an empty associative array
    from an empty list - array_filter()ing every key out of a config/params
    array collapses it to [] on the wire, which json.load() turns into a
    Python list, not a dict. Scripts that read config/params should pull
    them through this so `{}.get(...)` never blows up on an empty `[]`."""
    return value if isinstance(value, dict) else {}


def _json_default(value):
    """Fallback encoder for numpy/pandas scalars that plain json can't handle."""
    if isinstance(value, (datetime, date)):
        return value.isoformat()

    # numpy/pandas scalars expose .item() to unwrap to a native Python type.
    if hasattr(value, "item"):
        return value.item()

    if hasattr(value, "isoformat"):
        return value.isoformat()

    raise TypeError(f"Object of type {type(value).__name__} is not JSON serializable")


def write_success(path: str, data: dict) -> None:
    payload = {"success": True, "data": data}
    Path(path).write_text(json.dumps(payload, default=_json_default), encoding="utf-8")


def write_error(path: str, message: str) -> None:
    payload = {"success": False, "error": message}
    Path(path).write_text(json.dumps(payload, default=_json_default), encoding="utf-8")


def run_script(handler) -> None:
    """
    Standard entrypoint wrapper: parses --input/--output, calls handler(input)
    -> dict, writes {"success": true, "data": ...} on success or
    {"success": false, "error": ...} on any exception. Every script's
    `if __name__ == "__main__"` block should just call run_script(main).

    The process always exits 0 as long as it managed to write a well-formed
    output file - the {"success": ...} field, not the exit code, is what
    PythonRunnerService uses to tell a business-logic error (bad file, bad
    format) from an actual crash (missing dependency, syntax error, OOM).
    """
    args = parse_io_args()

    try:
        input_data = read_input(args.input)
        result = handler(input_data)
        write_success(args.output, result)
    except Exception as exc:  # noqa: BLE001 - deliberately broad, this is the top-level boundary
        write_error(args.output, str(exc))
