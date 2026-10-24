#!/bin/sh
set -eu
ROOT="$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)"
for f in index.html 404.html admin/index.html assets/style.css assets/admin.js assets/public.js assets/config.js assets/supabase.js supabase/schema.sql supabase/functions/admin-users/index.ts README.md; do
  test -s "$ROOT/$f" || { echo "FAIL missing: $f"; exit 1; }
done
if grep -q "SUPABASE_SERVICE_ROLE_KEY" "$ROOT/assets/config.js"; then echo 'FAIL: service role key variable must not be in frontend config'; exit 1; fi
if ! grep -q "enable row level security" "$ROOT/supabase/schema.sql"; then echo 'FAIL: RLS not found'; exit 1; fi
if ! grep -q "profile?.role !== 'admin'" "$ROOT/supabase/functions/admin-users/index.ts"; then echo 'FAIL: admin check missing'; exit 1; fi
echo 'Static package smoke checks passed. This does not replace integration/security testing against a real Supabase project.'
