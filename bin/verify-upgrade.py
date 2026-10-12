#!/usr/bin/env python3
"""Verify historical settings retention after manually migrating to the new slug."""
from pathlib import Path
import io
import json
import os
import shutil
import subprocess
import tarfile
import zipfile

ROOT = Path(__file__).resolve().parent.parent
DIRECTORY = ROOT / "verification/package"
SLUG = "checkout-blocker-by-talistech"
ENV = str(ROOT / "node_modules/.bin/wp-env")
release = ROOT / "dist" / f"{SLUG}-1.2.0.zip"
assert (DIRECTORY / ".wp-env.json").exists(), "Run package verification first"
assert (DIRECTORY / SLUG).resolve().parent == DIRECTORY.resolve()

def run(*args):
    result = subprocess.run([ENV, *args], cwd=DIRECTORY, text=True, capture_output=True)
    if result.returncode:
        raise RuntimeError(result.stdout + result.stderr)
    return result.stdout

def clear():
    for path in (DIRECTORY / SLUG).iterdir():
        if path.is_dir():
            shutil.rmtree(path)
        else:
            path.unlink()

print("Upgrade: install historical version 1.1.1 in the package test environment", flush=True)
run("stop")
clear()
historical = subprocess.run(["git", "archive", "6026cd1", "wp-checkout-blocker-by-talistech.php", "uninstall.php", "src", "assets", "languages", "readme.txt", "LICENSE"], cwd=ROOT, check=True, capture_output=True).stdout
with tarfile.open(fileobj=io.BytesIO(historical)) as source:
    source.extractall(DIRECTORY / SLUG, filter="data")
(DIRECTORY / SLUG / "wp-checkout-blocker-by-talistech.php").rename(DIRECTORY / SLUG / f"{SLUG}.php")
assert "Version: 1.1.1" in (DIRECTORY / SLUG / f"{SLUG}.php").read_text()
run("start")
legacy = {"email_rules": "retained@example.com", "name_rules": "jan jansen", "address_rules": "street 1|1000|be", "notice_text": "Version 1.1.1 retained notice", "notify_admins": 0, "force_logout": 0, "logout_redirect": "http://localhost:8895/help"}
encoded = json.dumps(json.dumps(legacy))
run("run", "cli", "wp", "eval", "$settings=json_decode(" + encoded + ",true);update_option('talistech_checkout_blocker_settings',$settings);if(Talistech\\CheckoutBlocker\\TCB_VERSION!=='1.1.1'){throw new Exception('Historical plugin not loaded');}")
run("stop")
clear()
with zipfile.ZipFile(release) as bundle:
    bundle.extractall(DIRECTORY)
print("Upgrade: load the release ZIP over the same plugin folder and database", flush=True)
run("start")
run("run", "cli", "wp", "eval", "$expected=json_decode(" + encoded + ",true);if(get_option('talistech_checkout_blocker_settings')!==$expected || Talistech\\CheckoutBlocker\\TCB_VERSION!=='1.2.0'){throw new Exception('Upgrade did not preserve settings');}echo 'UPGRADE PASS: 1.1.1 to 1.2.0; all settings retained.';")
(DIRECTORY / "upgrade.json").write_text(json.dumps({"from": "1.1.1", "to": "1.2.0", "settings_retained": True, "passed": True}, indent=2) + "\n")
print("Upgrade verified: all existing settings retained.", flush=True)
