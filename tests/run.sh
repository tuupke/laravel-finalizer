#!/usr/bin/env bash
#
# Usage: tests/run.sh <cli|http|all> <php>:<laravel> [<php>:<laravel> ...]
#
# For every combination, installs the package with the given Laravel version
# (via Composer in Docker) and runs the selected suites in official PHP images.

set -uo pipefail

ROOT=$(cd "$(dirname "$0")/.." && pwd)
SUITE=$1
shift

failed=()

log_lines() {
    docker exec "$fpm" sh -c 'cat /tmp/finalizer-test.log 2>/dev/null' | grep -v '^$' || true
}

expect() {
    local name=$1 ok=$2
    if [ "$ok" = 1 ]; then
        echo "  ok   $name"
    else
        echo "  FAIL $name"
        return 1
    fi
}

install() {
    local php=$1 laravel=$2 dir=$3
    mkdir -p "$dir/public" "$ROOT/.build/composer-cache"
    cp "$ROOT/tests/bootstrap.php" "$ROOT/tests/cli.php" "$dir/"
    cp "$ROOT/tests/http/index.php" "$dir/public/"
    cat > "$dir/composer.json" <<EOF
{
    "repositories": [{"type": "path", "url": "/pkg", "options": {"symlink": true}}],
    "require": {
        "tuupke/laravel-finalizer": "*@dev",
        "illuminate/container": "^$laravel.0",
        "illuminate/support": "^$laravel.0"
    },
    "config": {"platform": {"php": "$php.99"}}
}
EOF
    docker run --rm -u "$(id -u):$(id -g)" \
        -e COMPOSER_HOME=/tmp/composer -e COMPOSER_CACHE_DIR=/cache \
        -v "$ROOT:/pkg:ro" -v "$dir:/app" -v "$ROOT/.build/composer-cache:/cache" \
        -w /app composer:2 composer update --quiet --no-interaction --no-progress
}

run_cli() {
    local php=$1 dir=$2
    echo "  -- CLI"
    docker run --rm -v "$ROOT:/pkg:ro" -v "$dir:/app:ro" -w /app "php:$php-cli" php cli.php
}

# Times a request from a curl container on the test network; prints "<seconds> <body>"
request() {
    docker run --rm --network "$net" curlimages/curl:8.11.1 -s -w ' %{time_total}' "http://nginx/?$1"
}

run_http() {
    local php=$1 dir=$2 status=0 out time lines
    net=finalizer-test-$$
    fpm=$net-fpm
    echo "  -- HTTP (nginx + php:$php-fpm)"

    docker network create "$net" > /dev/null
    docker run -d --rm --name "$fpm" --network "$net" --network-alias fpm \
        -v "$ROOT:/pkg:ro" -v "$dir:/app:ro" "php:$php-fpm" > /dev/null
    docker run -d --rm --name "$net-nginx" --network "$net" --network-alias nginx \
        -v "$ROOT/tests/http/nginx.conf:/etc/nginx/conf.d/default.conf:ro" nginx:1.27-alpine > /dev/null

    if ! docker run --rm --network "$net" curlimages/curl:8.11.1 -sf -o /dev/null \
        --retry 30 --retry-delay 1 --retry-all-errors http://nginx/; then
        echo "  FAIL stack did not become ready"
        status=1
    else
        # Async: the response must be sent before the 2s closure runs
        docker exec "$fpm" rm -f /tmp/finalizer-test.log
        out=$(request "id=async")
        time=${out##* }
        lines=$(log_lines)
        expect "async: response body received" "$([[ $out == "response for async"* ]] && echo 1)" || status=1
        expect "async: response took ${time}s (< 1.5s)" "$(awk "BEGIN{print ($time < 1.5)}")" || status=1
        expect "async: closures had not run yet at response time" "$([ "$lines" = "async:response" ] && echo 1)" || status=1
        sleep 3
        lines=$(log_lines | tr '\n' '|')
        expect "async: closures ran afterwards, by priority, throwable logged" \
            "$([ "$lines" = "async:response|async:10|log:Throwable caught in finalizer|async:200|" ] && echo 1)" || status=1

        # Sync: inSyncOnExit() must keep the connection open until the closures ran
        docker exec "$fpm" rm -f /tmp/finalizer-test.log
        out=$(request "id=sync&mode=sync")
        time=${out##* }
        lines=$(log_lines | tr '\n' '|')
        expect "sync: response took ${time}s (>= 2s)" "$(awk "BEGIN{print ($time >= 2)}")" || status=1
        expect "sync: closures ran before the response completed" \
            "$([ "$lines" = "sync:response|sync:10|log:Throwable caught in finalizer|sync:200|" ] && echo 1)" || status=1
    fi

    docker rm -f "$fpm" "$net-nginx" > /dev/null 2>&1
    docker network rm "$net" > /dev/null 2>&1
    return $status
}

for combo in "$@"; do
    php=${combo%%:*}
    laravel=${combo##*:}
    dir="$ROOT/.build/php$php-laravel$laravel"
    echo "=== PHP $php / Laravel $laravel"

    if ! install "$php" "$laravel" "$dir"; then
        echo "  FAIL composer install"
        failed+=("$combo")
        continue
    fi
    echo "  illuminate/support $(grep -A1 '"name": "illuminate/support"' "$dir/composer.lock" | sed -n 's/.*"version": "\(.*\)".*/\1/p')"

    ok=1
    if [ "$SUITE" = cli ] || [ "$SUITE" = all ]; then
        run_cli "$php" "$dir" || ok=0
    fi
    if [ "$SUITE" = http ] || [ "$SUITE" = all ]; then
        run_http "$php" "$dir" || ok=0
    fi
    [ $ok = 1 ] || failed+=("$combo")
done

echo
if [ ${#failed[@]} -eq 0 ]; then
    echo "All combinations passed."
else
    echo "Failed: ${failed[*]}"
    exit 1
fi
