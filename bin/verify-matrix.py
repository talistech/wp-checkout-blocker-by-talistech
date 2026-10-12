#!/usr/bin/env python3
"""Run independent local WordPress environments for the supported version matrix."""
from pathlib import Path
import concurrent.futures
import json
import os
import subprocess

ROOT = Path(__file__).resolve().parent.parent
ENV = str(ROOT / "node_modules/.bin/wp-env")
PROFILES = [
    ("minimum", "7.0", "10.9.0", "8.1", 8896),
    ("stable", "7.1.3", "11.2.1", "8.3", 8897),
    ("php85", "7.1.3", "11.2.1", "8.5", 8898),
]

def verify(profile):
    name, wordpress, woo, php, port = profile
    directory = ROOT / "verification/environments" / ("resubmission-" + name)
    directory.mkdir(parents=True, exist_ok=True)
    config = {
        "core": f"https://wordpress.org/wordpress-{wordpress}.zip",
        "phpVersion": php,
        "plugins": [f"https://downloads.wordpress.org/plugin/woocommerce.{woo}.zip", ],
        "port": port,
        "testsEnvironment": False,
        "config": {"WP_DEBUG": True, "WP_DEBUG_LOG": True, "WP_ENVIRONMENT_TYPE": "local"},
        "mappings": {"wp-content/mu-plugins": str(ROOT / "tests/integration/mu-plugins"), "wp-content/plugins/talistech-checkout-blocker-for-woocommerce": str(ROOT)},
    }
    (directory / ".wp-env.json").write_text(json.dumps(config, indent=2) + "\n")
    env = {**os.environ, "TCB_ENV_DIRECTORY": str(directory), "TCB_TEST_URL": f"http://localhost:{port}"}
    log_path = directory / "verification.log"
    commands = [
        ("start", [ENV, "start", "--update"]),
        ("activate", [ENV, "run", "cli", "wp", "plugin", "activate", "talistech-checkout-blocker-for-woocommerce"]),
        ("unit", [ENV, "run", "cli", "php", "wp-content/plugins/talistech-checkout-blocker-for-woocommerce/vendor/bin/phpunit", "--configuration", "wp-content/plugins/talistech-checkout-blocker-for-woocommerce/phpunit.xml.dist", "--do-not-cache-result"]),
        ("safety", [ENV, "run", "cli", "wp", "eval-file", "wp-content/plugins/talistech-checkout-blocker-for-woocommerce/tests/integration/safety.php"]),
        ("checkout", ["python3", str(ROOT / "tests/integration/checkout.py")]),
    ]
    with log_path.open("w") as log:
        for step, command in commands:
            print(f"{name}: {step} (WordPress {wordpress}, WooCommerce {woo}, PHP {php})", flush=True)
            result = subprocess.run(command, cwd=directory, env=env, stdout=log, stderr=subprocess.STDOUT)
            log.flush()
            if result.returncode:
                raise RuntimeError(f"{name}: {step} failed\n{log_path.read_text()[-6000:]}")
    return {"profile": name, "wordpress": wordpress, "woocommerce": woo, "php": php, "passed": True}

if __name__ == "__main__":
    with concurrent.futures.ThreadPoolExecutor(max_workers=3) as pool:
        results = list(pool.map(verify, PROFILES))
    (ROOT / "verification/matrix.json").write_text(json.dumps(results, indent=2) + "\n")
    print("Compatibility matrix: all three profiles passed.", flush=True)
