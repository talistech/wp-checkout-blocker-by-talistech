#!/usr/bin/env python3
"""Exercise network lifecycle in the disposable stable matrix environment."""
from pathlib import Path
import json
import subprocess

ROOT = Path(__file__).resolve().parent.parent
DIRECTORY = ROOT / "verification/environments/resubmission-stable"
ENV = str(ROOT / "node_modules/.bin/wp-env")
SLUG = "talistech-checkout-blocker-for-woocommerce"
assert (ROOT / "verification/matrix.json").exists(), "Run the compatibility matrix first"
assert all(p["passed"] for p in json.loads((ROOT / "verification/matrix.json").read_text()))
config = json.loads((DIRECTORY / ".wp-env.json").read_text())
assert config["config"]["WP_ENVIRONMENT_TYPE"] == "local" and config["port"] == 8897

def run(*args):
    result = subprocess.run([ENV, "run", "cli", "wp", *args], cwd=DIRECTORY, text=True, capture_output=True)
    if result.returncode:
        raise RuntimeError(result.stdout + result.stderr)
    return result.stdout

run("plugin", "deactivate", SLUG)
run("core", "multisite-convert", "--title=TCB local network")
run("eval", "define('TCB_MULTISITE_PHASE','prepare');require 'wp-content/plugins/" + SLUG + "/tests/integration/multisite.php';")
run("plugin", "activate", "woocommerce", "--network")
run("plugin", "activate", SLUG, "--network")
active = run("eval", "define('TCB_MULTISITE_PHASE','active');require 'wp-content/plugins/" + SLUG + "/tests/integration/multisite.php';")
run("plugin", "deactivate", SLUG, "--network")
retained = run("eval", "define('TCB_MULTISITE_PHASE','deactivated');require 'wp-content/plugins/" + SLUG + "/tests/integration/multisite.php';")
run("plugin", "uninstall", SLUG, "--deactivate", "--skip-delete")
cleaned = run("eval", "define('TCB_MULTISITE_PHASE','uninstalled');require 'wp-content/plugins/" + SLUG + "/tests/integration/multisite.php';")
log = active + retained + cleaned
(DIRECTORY / "multisite.log").write_text(log)
(DIRECTORY / "multisite.json").write_text(json.dumps({"network_activation": True, "new_sites": True, "cross_site_management_protected": True, "deactivation_retains_settings": True, "uninstall_cleans_plugin_only": True, "passed": True}, indent=2) + "\n")
print(log[-2500:])
print("Multisite lifecycle and cross-site management protection passed.")
