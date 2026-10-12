#!/usr/bin/env python3
"""Prepare SVN trunk and release tag from the verified runtime ZIP."""
from pathlib import Path
import re
import shutil
import zipfile

ROOT = Path(__file__).resolve().parent.parent
SLUG = "checkout-blocker-by-talistech"
version = re.search(r"\* Version: ([\d.]+)", (ROOT / f"{SLUG}.php").read_text()).group(1)
release = ROOT / "dist" / f"{SLUG}-{version}.zip"
stage = ROOT / "dist/wordpress-org"
if stage.exists():
    shutil.rmtree(stage)
for destination in [stage / "trunk", stage / "tags" / version]:
    with zipfile.ZipFile(release) as bundle:
        for entry in bundle.infolist():
            relative = Path(entry.filename).relative_to(SLUG)
            target = destination / relative
            target.parent.mkdir(parents=True, exist_ok=True)
            target.write_bytes(bundle.read(entry))
media = ROOT / "media/wordpress-org"
if media.exists():
    shutil.copytree(media, stage / "assets")
print(f"Prepared {stage.relative_to(ROOT)}: trunk/, tags/{version}/ and available directory assets/")
