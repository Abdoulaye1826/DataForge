"""
Verifies the Laravel <-> Python bridge end-to-end: reads the --input JSON,
confirms the Python environment can actually import the data-science stack
DataForge depends on, and echoes a result back through --output.

Not part of the data pipeline itself - used by DashboardController@pythonCheck
during setup to confirm the environment is wired correctly before any real
script (import_dataset.py, quality_audit.py, ...) is written against it.
"""

import sys
from pathlib import Path

sys.path.append(str(Path(__file__).resolve().parent.parent))

from common.json_utils import run_script  # noqa: E402


def main(input_data: dict) -> dict:
    installed = {}
    for package in ["pandas", "numpy", "sklearn", "matplotlib", "openpyxl"]:
        try:
            module = __import__(package)
            installed[package] = getattr(module, "__version__", "unknown")
        except ImportError:
            installed[package] = None

    return {
        "message": "DataForge Python bridge is working.",
        "echo": input_data,
        "python_version": sys.version,
        "packages": installed,
    }


if __name__ == "__main__":
    run_script(main)
