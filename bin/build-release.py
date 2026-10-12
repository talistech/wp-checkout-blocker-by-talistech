#!/usr/bin/env python3
"""Build a deterministic, installable plugin ZIP from an explicit file allowlist."""
from pathlib import Path
import hashlib
import re
import zipfile

ROOT = Path(__file__).resolve().parent.parent
SLUG = "checkout-blocker-by-talistech"
main = (ROOT / f"{SLUG}.php").read_text()
version = re.search(r"\* Version: ([\d.]+)", main).group(1)
readme = (ROOT / "readme.txt").read_text()
headers = dict(re.findall(r"^\s*\*\s*([A-Za-z ]+):[ \t]*(.+)$", main, re.MULTILINE))
assert not headers.get("Plugin URI") or headers.get("Plugin URI").rstrip("/") != headers.get("Author URI", "").rstrip("/"), "Plugin URI must differ from Author URI"
assert headers.get("License") == "GPLv2 or later", "Declare the GPL license before building"
assert headers.get("License URI") == "https://www.gnu.org/licenses/gpl-2.0.html", "License URI is missing or incorrect"
assert re.search(r"^Tested up to: \d+\.\d+$", readme, re.MULTILINE), "Declare the tested WordPress major version"
assert f"Stable tag: {version}" in readme, "Readme and plugin versions differ"
assert re.search(r"TCB_VERSION\s*=\s*'" + re.escape(version) + "'", main), "Runtime and header versions differ"
assert f"Text Domain: {SLUG}" in main, "Text domain must match the directory slug"
assert "Update URI:" not in main, "WordPress.org release must use WordPress.org updates"
assert headers.get("Plugin Name") == "Checkout Blocker by Talistech", "Use the submission-compliant display name"
assert f"=== {headers['Plugin Name']} ===" in readme, "Readme and plugin names differ"
files = [ROOT / f"{SLUG}.php", ROOT / "uninstall.php", ROOT / "readme.txt", ROOT / "LICENSE"]
for directory, extension in [("src", ".php"), ("assets", ".css"), ("languages", ".pot"), ("languages", ".po"), ("languages", ".mo")]:
    files.extend((ROOT / directory).glob(f"*{extension}"))
assert ROOT / "languages" / f"{SLUG}.pot" in files, "Regenerate translations before building"
destination = ROOT / "dist" / f"{SLUG}-{version}.zip"
destination.parent.mkdir(exist_ok=True)
with zipfile.ZipFile(destination, "w", compression=zipfile.ZIP_DEFLATED, compresslevel=9) as archive:
    for path in sorted(set(files)):
        entry = zipfile.ZipInfo(f"{SLUG}/{path.relative_to(ROOT).as_posix()}", (2026, 1, 1, 0, 0, 0))
        entry.compress_type = zipfile.ZIP_DEFLATED
        entry.external_attr = 0o100644 << 16
        archive.writestr(entry, path.read_bytes(), compresslevel=9)
with zipfile.ZipFile(destination) as archive:
    assert archive.testzip() is None, "ZIP integrity check failed"
print(f"Built {destination.relative_to(ROOT)} ({destination.stat().st_size} bytes)")
print(f"SHA256 {hashlib.sha256(destination.read_bytes()).hexdigest()}")
