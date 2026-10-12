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
    ("stable", "7.1.2", "11.1.2", "8.3", 8897),
    ("php85", "7.1.2", "11.1.2", "8.5", 8898),
]

def verify(profile):
    name, wordpress, woo, php, port = profile
    directory = ROOT / "verification/environments" / name
    directory.mkdir(parents=True, exist_ok=True)
    config = {
        "core": f"https://wordpress.org/wordpress-{wordpress}.zip",
        "phpVersion": php,
        "plugins": [f"https://downloads.wordpress.org/plugin/woocommerce.{woo}.zip", ],
        "port": port,
        "testsEnvironment": False,
        "config": {"WP_DEBUG": True, "WP_DEBUG_LOG": True, "WP_ENVIRONMENT_TYPE": "local"},
        "mappings": {"wp-content/mu-plugins/tcb-local-mail.php": str(ROOT / "tests/integration/mu-plugins/tcb-local-mail.php")},
    }
    (directory / ".wp-env.json").write_text(json.dumps(config, indent=2) + "\n")
    env = {**os.environ, "TCB_ENV_DIRECTORY": str(directory), "TCB_TEST_URL": f"http://localhost:{port}"}
    log_path = directory / "verification.log"
    commands = [
        ("start", [ENV, "start", "--update"]),
        ("activate", [ENV, "run", "cli", "wp", "plugin", "activate", "checkout-blocker-by-talistech"]),
        ("unit", [ENV, "run", "cli", "php", "wp-content/plugins/checkout-blocker-by-talistech/vendor/bin/phpunit", "--configuration", "wp-content/plugins/checkout-blocker-by-talistech/phpunit.xml.dist", "--do-not-cache-result"]),
        ("safety", [ENV, "run", "cli", "wp", "eval-file", "wp-content/plugins/checkout-blocker-by-talistech/tests/integration/safety.php"]),
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
