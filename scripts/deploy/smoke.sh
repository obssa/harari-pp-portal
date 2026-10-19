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

root="$(curl -sS -o /dev/null -w '%{http_code} %{redirect_url}' -H 'Accept-Language: om-ET,om;q=0.9' "$BASE/?smoke=$(date +%s%N)")"
case "$root" in
    "302 $BASE/om?smoke="*) echo "PASS: / redirects to the browser language (/om)";;
    *) echo "FAIL: / answered '$root' instead of a redirect to /om"; exit 1;;
esac

for locale in en am om har; do
    code="$(fetch "/$locale")"
    [ "$code" = "200" ] || { echo "FAIL: /$locale returned $code"; head -c 2000 /tmp/smoke.body; exit 1; }
    grep -q "<html lang=\"$locale\">" /tmp/smoke.body || { echo "FAIL: /$locale is not marked lang=$locale"; exit 1; }
    grep -q 'hreflang="x-default"' /tmp/smoke.body || { echo "FAIL: /$locale has no hreflang links"; exit 1; }
    if grep -qE '>(holding|site|nav)\.[a-z_]+<' /tmp/smoke.body; then echo "FAIL: /$locale shows raw translation keys"; exit 1; fi
done
echo "PASS: /en /am /om /har render in their language with hreflang and no raw keys"

code="$(fetch /storage/smoke.txt)"
[ "$code" = "200" ] || { echo "FAIL: public uploads are not served (/storage/smoke.txt returned $code)"; exit 1; }
echo "PASS: public uploads are served (/storage)"

code="$(fetch /admin/login)"
[ "$code" = "200" ] || { echo "FAIL: /admin/login returned $code"; head -c 2000 /tmp/smoke.body; exit 1; }
grep -q 'wire:snapshot' /tmp/smoke.body || { echo "FAIL: admin sign-in page did not render Livewire"; exit 1; }
echo "PASS: /admin/login 200 (admin panel renders)"

if [ "$EXPECT_NOINDEX" = "yes" ]; then
    grep -qi '^x-robots-tag: noindex' /tmp/smoke.headers || { echo "FAIL: staging is indexable (no X-Robots-Tag)"; exit 1; }
    echo "PASS: staging sends X-Robots-Tag noindex"
fi
