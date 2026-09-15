#!/usr/bin/env bash
set -euo pipefail

base_url="${BASE_URL:-http://localhost:8000}"
email="${ADMIN_EMAIL:-ci@example.com}"
password="${ADMIN_PASSWORD:-ci-only-password-16}"
work_dir="$(mktemp -d)"
product_id=""

cleanup() {
  docker compose start database mercure >/dev/null 2>&1 || true
  if [[ -n "$product_id" && -f "$work_dir/auth.header" ]]; then
    curl --silent --max-time 10 --request DELETE --header @"$work_dir/auth.header" \
      "$base_url/api/products/$product_id" >/dev/null 2>&1 || true
  fi
  rm -rf "$work_dir"
}
trap cleanup EXIT

request() {
  local output="$1"
  shift
  curl --silent --show-error --max-time 15 --output "$output" --write-out '%{http_code}' "$@"
}

assert_no_secrets() {
  local file="$1"
  shift
  for secret in "$@"; do
    if [[ -n "$secret" ]] && grep --fixed-strings --quiet "$secret" "$file"; then
      printf 'secret leaked in %s\n' "$file" >&2
      return 1
    fi
  done
}

status="$(request "$work_dir/ready.json" "$base_url/api/ready")"
[[ "$status" == 200 ]] || { printf 'readiness: expected 200, got %s\n' "$status" >&2; exit 1; }

jq --null-input --arg username "$email" --arg password "$password" \
  '{username: $username, password: $password, response_mode: "json"}' >"$work_dir/login-request.json"
status="$(request "$work_dir/login.json" --request POST "$base_url/api/auth/login" \
  --header 'Content-Type: application/json' --data-binary @"$work_dir/login-request.json")"
[[ "$status" == 200 ]] || { printf 'JSON login: expected 200, got %s\n' "$status" >&2; exit 1; }
access_token="$(jq --raw-output '.token' "$work_dir/login.json")"
refresh_token="$(jq --raw-output '.refreshToken' "$work_dir/login.json")"
[[ "$access_token" != null && "$refresh_token" != null ]] || { printf 'JSON login omitted tokens\n' >&2; exit 1; }
printf 'Authorization: Bearer %s\n' "$access_token" >"$work_dir/auth.header"

jq --null-input --arg token "$refresh_token" \
  '{refreshToken: $token, response_mode: "json"}' >"$work_dir/refresh-request.json"
status="$(request "$work_dir/rotated.json" --request POST "$base_url/api/auth/refresh" \
  --header 'Content-Type: application/json' --data-binary @"$work_dir/refresh-request.json")"
[[ "$status" == 200 ]] || { printf 'refresh rotation: expected 200, got %s\n' "$status" >&2; exit 1; }
rotated_token="$(jq --raw-output '.refreshToken' "$work_dir/rotated.json")"
rotated_access_token="$(jq --raw-output '.token' "$work_dir/rotated.json")"
[[ "$rotated_token" != null && "$rotated_access_token" != null ]] || { printf 'refresh rotation omitted tokens\n' >&2; exit 1; }

status="$(request "$work_dir/reused.json" --request POST "$base_url/api/auth/refresh" \
  --header 'Content-Type: application/json' --data-binary @"$work_dir/refresh-request.json")"
[[ "$status" == 401 ]] || { printf 'refresh reuse: expected 401, got %s\n' "$status" >&2; exit 1; }
[[ "$(jq --raw-output '.message' "$work_dir/reused.json")" == 'Invalid refresh token' ]]

jq --null-input --arg token "$rotated_token" \
  '{refreshToken: $token, response_mode: "json"}' >"$work_dir/rotated-request.json"
status="$(request "$work_dir/second-rotation.json" --request POST "$base_url/api/auth/refresh" \
  --header 'Content-Type: application/json' --data-binary @"$work_dir/rotated-request.json")"
[[ "$status" == 200 ]] || { printf 'rotated token: expected 200, got %s\n' "$status" >&2; exit 1; }
expiry_token="$(jq --raw-output '.refreshToken' "$work_dir/second-rotation.json")"
expiry_access_token="$(jq --raw-output '.token' "$work_dir/second-rotation.json")"
[[ "$expiry_token" != null && "$expiry_access_token" != null ]] || { printf 'second rotation omitted tokens\n' >&2; exit 1; }

expiry_hash="$(printf '%s' "$expiry_token" | shasum -a 256 | cut -d ' ' -f 1)"
updated="$(docker compose exec -T database psql --username app --dbname app --tuples-only --no-align \
  --command "UPDATE nubit_refresh_token SET expires_at = CURRENT_TIMESTAMP - INTERVAL '1 second' WHERE token_hash = '$expiry_hash' RETURNING id;")"
[[ -n "$updated" ]] || { printf 'refresh expiry fixture did not update a token\n' >&2; exit 1; }
jq --null-input --arg token "$expiry_token" \
  '{refreshToken: $token, response_mode: "json"}' >"$work_dir/expiry-request.json"
status="$(request "$work_dir/expired.json" --request POST "$base_url/api/auth/refresh" \
  --header 'Content-Type: application/json' --data-binary @"$work_dir/expiry-request.json")"
[[ "$status" == 401 ]] || { printf 'refresh expiry: expected 401, got %s\n' "$status" >&2; exit 1; }

mercure_since="$(date -u +'%Y-%m-%dT%H:%M:%SZ')"
docker compose stop mercure >/dev/null
status="$(request "$work_dir/product.json" --request POST "$base_url/api/products" \
  --header 'Content-Type: application/json' --header @"$work_dir/auth.header" \
  --data '{"name":"Mercure outage","sku":"FAULT-MERCURE","price":"9.99","active":true}')"
[[ "$status" == 201 ]] || { printf 'CRUD with Mercure down: expected 201, got %s\n' "$status" >&2; exit 1; }
product_id="$(jq --raw-output '.id' "$work_dir/product.json")"
status="$(request "$work_dir/product-read.json" --header @"$work_dir/auth.header" \
  "$base_url/api/products/$product_id")"
[[ "$status" == 200 ]] || { printf 'persisted product read: expected 200, got %s\n' "$status" >&2; exit 1; }

docker compose logs --no-color --since "$mercure_since" app >"$work_dir/mercure.log"
grep --fixed-strings --quiet 'Mercure publish failed' "$work_dir/mercure.log"
assert_no_secrets "$work_dir/mercure.log" "$password" "$access_token" "$refresh_token" "$rotated_token" \
  "$rotated_access_token" "$expiry_token" "$expiry_access_token" \
  '!ChangeMe!' '!ChangeThisMercureHubJWTSecretKey!' 'change-me-to-a-random-32-byte-secret!!'

status="$(request "$work_dir/product-delete.json" --request DELETE \
  --header @"$work_dir/auth.header" "$base_url/api/products/$product_id")"
[[ "$status" == 204 ]] || { printf 'cleanup: expected 204, got %s\n' "$status" >&2; exit 1; }
product_id=""
docker compose start mercure >/dev/null

docker compose stop database >/dev/null
status="$(request "$work_dir/database.json" --max-time 10 "$base_url/api/ready" || true)"
[[ "$status" == 503 ]] || { printf 'DB outage readiness: expected 503, got %s\n' "$status" >&2; exit 1; }
[[ "$(jq --raw-output '.status' "$work_dir/database.json")" == unavailable ]]
assert_no_secrets "$work_dir/database.json" '!ChangeMe!' 'change-me-to-a-random-32-byte-secret!!'
docker compose logs --no-color app >"$work_dir/database.log"
grep --fixed-strings --quiet 'Database readiness check failed' "$work_dir/database.log"
assert_no_secrets "$work_dir/database.log" "$password" "$access_token" "$refresh_token" "$rotated_token" \
  "$rotated_access_token" "$expiry_token" "$expiry_access_token" '!ChangeMe!'

docker compose start database >/dev/null
for _ in $(seq 1 30); do
  status="$(request "$work_dir/recovered.json" "$base_url/api/ready" || true)"
  [[ "$status" == 200 ]] && break
  sleep 1
done
[[ "$status" == 200 ]] || { printf 'readiness did not recover, got %s\n' "$status" >&2; exit 1; }

printf 'dependency failure paths OK\n'
