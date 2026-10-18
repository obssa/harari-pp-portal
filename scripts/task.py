#!/usr/bin/env python3
"""Cross-platform task runner (Windows has no `make`).

Every PHP command runs inside the php:8.3 test container from compose.test.yml.
The website itself is never served locally; it runs on hararipp.orcamind.dev.

Usage:
    python scripts/task.py doctor
    python scripts/task.py up | down
    python scripts/task.py install            # composer install inside the container
    python scripts/task.py test [pest args]   # Pest against MySQL 8 (harari_test)
    python scripts/task.py coverage           # Pest with coverage and the CI minimum
    python scripts/task.py lint               # Pint --test + Larastan
    python scripts/task.py fix                # Pint (writes)
    python scripts/task.py composer <args>
    python scripts/task.py artisan <args>
    python scripts/task.py php <args>
    python scripts/task.py ci                 # everything CI runs, in CI order
"""
from __future__ import annotations

import shutil
import subprocess
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parent.parent
COMPOSE = ["docker", "compose", "-f", str(ROOT / "compose.test.yml")]
COVERAGE_MIN = "80"


def run(cmd: list[str], check: bool = True) -> int:
    print("+ " + " ".join(cmd), flush=True)
    code = subprocess.call(cmd, cwd=ROOT)
    if check and code != 0:
        sys.exit(code)
    return code


def app(*args: str, check: bool = True) -> int:
    return run([*COMPOSE, "exec", "-T", "app", *args], check=check)


def stack_running() -> bool:
    out = subprocess.run(
        [*COMPOSE, "ps", "--status", "running", "--services"],
        cwd=ROOT, capture_output=True, text=True,
    ).stdout.split()
    return "app" in out and "mysql" in out


def ensure_up() -> None:
    if not stack_running():
        up()


def up() -> None:
    run([*COMPOSE, "up", "-d", "--wait"])
    if app("test", "-f", "vendor/autoload.php", check=False) != 0:
        install()


def down() -> None:
    run([*COMPOSE, "down"])


def install() -> None:
    app("composer", "install", "--no-interaction", "--no-progress")


def doctor() -> None:
    problems: list[str] = []
    if not shutil.which("docker"):
        problems.append("docker is not on PATH")
    elif subprocess.call(["docker", "info"], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL) != 0:
        problems.append("Docker daemon is not running (start Docker Desktop)")
    autocrlf = subprocess.run(
        ["git", "config", "core.autocrlf"], cwd=ROOT, capture_output=True, text=True
    ).stdout.strip()
    if autocrlf not in ("", "false", "input"):
        problems.append(f"git core.autocrlf={autocrlf}; run `git config core.autocrlf false`")
    if not shutil.which("node"):
        problems.append("node is not on PATH (needed for Vite builds)")
    host_php = shutil.which("php")
    if host_php:
        print(f"note: host PHP at {host_php} is ignored; all PHP runs in the container")
    if problems:
        for p in problems:
            print("FAIL:", p)
        sys.exit(1)
    ensure_up()
    app("php", "-r", "echo 'container PHP ', PHP_VERSION, PHP_EOL;")
    print("doctor: OK")


def test(args: list[str]) -> None:
    ensure_up()
    app("php", "artisan", "config:clear", "--env=testing", check=False)
    app("vendor/bin/pest", "--colors=never", *args)


def coverage() -> None:
    ensure_up()
    app("vendor/bin/pest", "--colors=never", "--coverage", f"--min={COVERAGE_MIN}")


def lint() -> None:
    ensure_up()
    app("vendor/bin/pint", "--test")
    app("vendor/bin/phpstan", "analyse", "--memory-limit=1G", "--no-progress")


def fix() -> None:
    ensure_up()
    app("vendor/bin/pint")


def ci() -> None:
    lint()
    app("php", "artisan", "lang:missing", check=True) if lang_missing_exists() else None
    test([])


def lang_missing_exists() -> bool:
    return (ROOT / "app" / "Console" / "Commands" / "LangMissing.php").exists()


def main() -> None:
    if len(sys.argv) < 2:
        print(__doc__)
        sys.exit(1)
    task, rest = sys.argv[1], sys.argv[2:]
    if task == "doctor":
        doctor()
    elif task == "up":
        up()
    elif task == "down":
        down()
    elif task == "install":
        ensure_up()
        install()
    elif task == "test":
        test(rest)
    elif task == "coverage":
        coverage()
    elif task == "lint":
        lint()
    elif task == "fix":
        fix()
    elif task == "ci":
        ci()
    elif task in ("composer", "php"):
        ensure_up()
        app(task, *rest)
    elif task == "artisan":
        ensure_up()
        app("php", "artisan", *rest)
    else:
        print(f"unknown task: {task}")
        print(__doc__)
        sys.exit(1)


if __name__ == "__main__":
    main()
