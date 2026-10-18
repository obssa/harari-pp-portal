#!/usr/bin/env bash
# Runs on the CI runner against the live URL after a release.
# Usage: smoke.sh https://hararipp.orcamind.dev [expect-noindex]
set -euo pipefail
BASE="${1:?base URL required}"
EXPECT_NOINDEX="${2:-yes}"

fetch() { curl -sS -o /tmp/smoke.body -D /tmp/smoke.headers -w '%{http_code}' \
          -H 'Cache-Control: no-cache' "$BASE$1?smoke=$(date +%s%N)"; }

ok=0
for attempt in $(seq 1 10); do
    code="$(fetch /up || true)"
    if [ "$code" = "200" ]; then ok=1; break; fi
    echo "attempt $attempt: /up returned $code; retrying"
    sleep 6
done
[ "$ok" = 1 ] || { echo "FAIL: /up never returned 200"; exit 1; }
echo "PASS: /up 200"

code="$(fetch /)"
[ "$code" = "200" ] || { echo "FAIL: / returned $code"; head -c 2000 /tmp/smoke.body; exit 1; }
grep -q 'lang="am"' /tmp/smoke.body || { echo "FAIL: home page has no Amharic section"; exit 1; }
echo "PASS: / 200 with Amharic content"

code="$(fetch /admin/login)"
[ "$code" = "200" ] || { echo "FAIL: /admin/login returned $code"; head -c 2000 /tmp/smoke.body; exit 1; }
grep -q 'wire:snapshot' /tmp/smoke.body || { echo "FAIL: admin sign-in page did not render Livewire"; exit 1; }
echo "PASS: /admin/login 200 (admin panel renders)"

if [ "$EXPECT_NOINDEX" = "yes" ]; then
    grep -qi '^x-robots-tag: noindex' /tmp/smoke.headers || { echo "FAIL: staging is indexable (no X-Robots-Tag)"; exit 1; }
    echo "PASS: staging sends X-Robots-Tag noindex"
fi
