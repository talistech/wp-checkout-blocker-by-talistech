#!/usr/bin/env python3
"""Verify real directory migrations and duplicate guards in a disposable local shop."""
from pathlib import Path
import hashlib
import io
import json
import shutil
import subprocess
import tarfile

ROOT = Path(__file__).resolve().parent.parent
DIRECTORY = ROOT / "verification/resubmission-package"
SLUG = "talistech-checkout-blocker-for-woocommerce"
ENV = str(ROOT / "node_modules/.bin/wp-env")
release = ROOT / "dist" / f"{SLUG}.zip"
assert (DIRECTORY / ".wp-env.json").exists(), "Run full package verification first"
result = json.loads((DIRECTORY / "result.json").read_text())
assert result["passed"] and result["full_suite"]
assert hashlib.sha256(release.read_bytes()).hexdigest() == result["sha256"]
historical = [
    ("1.1.1", "6026cd1", "wp-checkout-blocker-by-talistech"),
    ("1.2.0", "a73f783", "checkout-blocker-by-talistech"),
]

def run(*args, rejected=False):
    proc = subprocess.run([ENV, "run", "cli", "wp", *args], cwd=DIRECTORY, text=True, capture_output=True)
    output = proc.stdout + proc.stderr
    if rejected:
        assert proc.returncode != 0 and "Deactivate the older" in output, output
    elif proc.returncode:
        raise RuntimeError(output)
    return output

def setting_check(expected_version):
    code = "$expected=json_decode(" + json.dumps(json.dumps(legacy)) + ",true);"
    code += "if(get_option('talistech_checkout_blocker_settings')!==$expected || Talistech\\CheckoutBlocker\\TCB_VERSION!==" + json.dumps(expected_version) + "){throw new Exception('Migration reset settings or loaded wrong version');}"
    run("eval", code)

config = json.loads((DIRECTORY / ".wp-env.json").read_text())
assert config["config"]["WP_ENVIRONMENT_TYPE"] == "local" and config["port"] == 8895
for version, commit, slug in historical:
    target = DIRECTORY / slug
    assert target.resolve().parent == DIRECTORY.resolve()
    if target.exists():
        shutil.rmtree(target)
    target.mkdir()
    archive = subprocess.run(["git", "archive", commit, f"{slug}.php", "uninstall.php", "src", "assets", "languages", "readme.txt", "LICENSE"], cwd=ROOT, capture_output=True, check=True).stdout
    with tarfile.open(fileobj=io.BytesIO(archive)) as bundle:
        bundle.extractall(target, filter="data")
    assert f"Version: {version}" in (target / f"{slug}.php").read_text()
    if str(target) not in config["plugins"]:
        config["plugins"].append(str(target))
(DIRECTORY / ".wp-env.json").write_text(json.dumps(config, indent=2) + "\n")
subprocess.run([ENV, "start"], cwd=DIRECTORY, check=True, stdout=subprocess.DEVNULL)
legacy = {"email_rules": "retained@example.com", "name_rules": "Иван Иванов\nJan Jansen", "address_rules": "street 1|1000|be", "notice_text": "Retained customer's notice", "notify_admins": 0, "force_logout": 0, "logout_redirect": "http://localhost:8895/help"}
results = []
all_files = [f"{SLUG}/{SLUG}.php"] + [f"{slug}/{slug}.php" for _, _, slug in historical]

for version, commit, slug in historical:
    run("eval", "if(wp_get_environment_type()!=='local'){throw new Exception('Local only');}deactivate_plugins(" + json.dumps(all_files) + ");")
    run("plugin", "activate", slug)
    run("eval", "$settings=json_decode(" + json.dumps(json.dumps(legacy)) + ",true);update_option('talistech_checkout_blocker_settings',$settings);")
    setting_check(version)
    run("plugin", "activate", SLUG, rejected=True)
    setting_check(version)
    files = sorted([f"{slug}/{slug}.php", f"{SLUG}/{SLUG}.php", "woocommerce/woocommerce.php"])
    run("eval", "update_option('active_plugins'," + json.dumps(files) + ");")
    setting_check(version)
    run("eval", "deactivate_plugins(" + json.dumps(all_files) + ");")
    run("plugin", "activate", SLUG)
    setting_check("1.2.1")
    assert (DIRECTORY / slug).resolve().parent == DIRECTORY.resolve()
    shutil.rmtree(DIRECTORY / slug)
    setting_check("1.2.1")
    results.append({"from": version, "to": "1.2.1", "old_slug": slug, "settings_retained": True, "duplicate_activation_rejected": True, "duplicate_load_safe": True, "passed": True})
    print(f"Migration {version} -> 1.2.1: settings retained; duplicate activation rejected; old folder removed without uninstall.", flush=True)
(DIRECTORY / "upgrade.json").write_text(json.dumps(results, indent=2) + "\n")

# Restore the package configuration after removing disposable historical copies.
config["plugins"] = [p for p in config["plugins"] if p not in [str(DIRECTORY / slug) for _, _, slug in historical]]
(DIRECTORY / ".wp-env.json").write_text(json.dumps(config, indent=2) + "\n")
