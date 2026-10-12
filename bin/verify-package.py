#!/usr/bin/env python3
"""Install the exact release artifact in a clean environment and verify it."""
from pathlib import Path
import argparse
import hashlib
import json
import os
import re
import subprocess
import zipfile

parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument("--check-only", action="store_true", help="Run Plugin Check only; omit checkout and safety suites")
arguments = parser.parse_args()

ROOT = Path(__file__).resolve().parent.parent
SLUG = "checkout-blocker-by-talistech"
ENV = str(ROOT / "node_modules/.bin/wp-env")
DIRECTORY = ROOT / "verification/package"
DIRECTORY.mkdir(parents=True, exist_ok=True)
version = re.search(r"\* Version: ([\d.]+)", (ROOT / f"{SLUG}.php").read_text()).group(1)
archive = ROOT / "dist" / f"{SLUG}-{version}.zip"
with zipfile.ZipFile(archive) as bundle:
    assert bundle.testzip() is None
    assert not any(part in name.split("/") for name in bundle.namelist() for part in ("vendor", "node_modules", "tests", "dist", ".git"))
    bundle.extractall(DIRECTORY)
config = {
    "core": "https://wordpress.org/wordpress-7.1.2.zip",
    "phpVersion": "8.3",
    "plugins": ["https://downloads.wordpress.org/plugin/woocommerce.11.1.2.zip", "https://downloads.wordpress.org/plugin/plugin-check.latest-stable.zip", str(DIRECTORY / SLUG)],
    "port": 8895,
    "testsEnvironment": False,
    "config": {"WP_DEBUG": True, "WP_DEBUG_LOG": True, "WP_ENVIRONMENT_TYPE": "local"},
    "mappings": {"wp-content/tcb-verification": str(ROOT / "tests"), "wp-content/mu-plugins": str(ROOT / "tests/integration/mu-plugins")},
}
(DIRECTORY / ".wp-env.json").write_text(json.dumps(config, indent=2) + "\n")
env = {**os.environ, "TCB_ENV_DIRECTORY": str(DIRECTORY), "TCB_TEST_URL": "http://localhost:8895"}
commands = [
    [ENV, "start"],
    [ENV, "run", "cli", "wp", "eval-file", "wp-content/tcb-verification/integration/safety.php"],
    ["python3", str(ROOT / "tests/integration/checkout.py")],
    [ENV, "run", "cli", "wp", "plugin", "check", SLUG, "--format=strict-json"],
]
for index, command in enumerate(commands):
    if arguments.check_only and index < 3:
        continue
    log_path = DIRECTORY / ("plugin-check.log" if index == 3 else f"step-{index}.log")
    print(f"Package verification {index + 1}/{len(commands)}", flush=True)
    with log_path.open("w") as log:
        result = subprocess.run(command, cwd=DIRECTORY, env=env, stdout=log, stderr=subprocess.STDOUT)
    if result.returncode:
        raise RuntimeError(log_path.read_text()[-6000:])
check_log = (DIRECTORY / "plugin-check.log").read_text()
# strict-json emits one JSON result array across all checked files.
decoder = json.JSONDecoder()
findings = None
for start, char in enumerate(check_log):
    if char != "[":
        continue
    try:
        parsed, _ = decoder.raw_decode(check_log[start:])
    except json.JSONDecodeError:
        continue
    if isinstance(parsed, list) and (not parsed or isinstance(parsed[0], dict)):
        findings = parsed
        break
if findings is None and "Success: Checks complete. No errors found." in check_log:
    findings = []
assert findings is not None, "Plugin Check did not emit JSON or its clean-result success message"
errors = [finding for finding in findings if finding.get("type") == "ERROR"]
assert not errors, errors
assert not findings, findings
(DIRECTORY / "result.json").write_text(json.dumps({"zip": archive.name, "sha256": hashlib.sha256(archive.read_bytes()).hexdigest(), "plugin_check": findings, "passed": True}, indent=2) + "\n")
summary = "Plugin Check" if arguments.check_only else "Release ZIP verified: 62 HTTP checks; Plugin Check"
print(f"{summary}: {len(errors)} errors, {len(findings)} warnings.", flush=True)
