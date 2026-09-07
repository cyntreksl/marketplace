#!/usr/bin/env bash

set -Eeuo pipefail

readonly script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
readonly artifact_path="${1:-artifact/prodeals-release.tar.gz}"
readonly remote_script="/tmp/prodeals-remote-release.sh"

for required_variable in WEB_HOST WORKER_HOST DEPLOY_USER RELEASE_ID; do
    if [[ -z "${!required_variable:-}" ]]; then
        echo "${required_variable} is required." >&2
        exit 1
    fi
done

if [[ ! "$RELEASE_ID" =~ ^[0-9a-f]{40}$ ]]; then
    echo "RELEASE_ID must be a full Git commit SHA." >&2
    exit 1
fi

if [[ ! -f "$artifact_path" ]]; then
    echo "Release artifact is missing at ${artifact_path}." >&2
    exit 1
fi

readonly remote_artifact="/tmp/prodeals-${RELEASE_ID}.tar.gz"
readonly deployment_started_at="$SECONDS"
readonly runtime_parent="${RUNNER_TEMP:-/tmp}"
readonly runtime_dir="$(mktemp -d "${runtime_parent}/prodeals-deploy.XXXXXX")"
readonly control_path="${runtime_dir}/ssh-%C"
readonly -a hosts=("$WEB_HOST" "$WORKER_HOST")
readonly -a ssh_options=(
    -o BatchMode=yes
    -o StrictHostKeyChecking=yes
    -o ControlMaster=auto
    -o ControlPersist=120
    -o ControlPath="$control_path"
)

previous_release_id=""

close_connections() {
    local host

    set +e
    for host in "${hosts[@]}"; do
        ssh "${ssh_options[@]}" -O exit "${DEPLOY_USER}@${host}" >/dev/null 2>&1
    done
    rm -rf -- "$runtime_dir"
}

rollback() {
    local exit_code="$?"

    trap - ERR
    set +e

    if [[ "$previous_release_id" =~ ^[0-9a-f]{40}$ ]]; then
        echo "Deployment failed; rolling both hosts back to ${previous_release_id}." >&2
        ssh "${ssh_options[@]}" "${DEPLOY_USER}@${WEB_HOST}" \
            "${remote_script} rollback-to ${RELEASE_ID} ${previous_release_id}" &
        local web_rollback_pid="$!"
        ssh "${ssh_options[@]}" "${DEPLOY_USER}@${WORKER_HOST}" \
            "${remote_script} rollback-to ${RELEASE_ID} ${previous_release_id}" &
        local worker_rollback_pid="$!"
        wait "$web_rollback_pid"
        wait "$worker_rollback_pid"

        ssh "${ssh_options[@]}" "${DEPLOY_USER}@${WEB_HOST}" \
            'sudo systemctl restart php8.4-fpm && sudo systemctl reload nginx && sudo systemctl restart prodeals-ssr' &
        local web_restart_pid="$!"
        ssh "${ssh_options[@]}" "${DEPLOY_USER}@${WORKER_HOST}" \
            'sudo supervisorctl restart prodeals-worker' &
        local worker_restart_pid="$!"
        wait "$web_restart_pid"
        wait "$worker_restart_pid"
    fi

    exit "$exit_code"
}

run_stage() {
    local name="$1"
    local started_at="$SECONDS"

    shift
    echo "::group::${name}"
    "$@"
    echo "${name} completed in $((SECONDS - started_at))s."
    echo '::endgroup::'
}

wait_for_processes() {
    local pid
    local failed=0

    for pid in "$@"; do
        if ! wait "$pid"; then
            failed=1
        fi
    done

    return "$failed"
}

run_on_hosts() {
    local operation="$1"
    local host
    local -a pids=()

    for host in "${hosts[@]}"; do
        (trap - ERR; "$operation" "$host") &
        pids+=("$!")
    done

    wait_for_processes "${pids[@]}"
}

open_connection() {
    local host="$1"

    ssh "${ssh_options[@]}" -MNf "${DEPLOY_USER}@${host}"
}

install_release_script() {
    local host="$1"

    scp "${ssh_options[@]}" "${script_dir}/remote-release.sh" "${DEPLOY_USER}@${host}:${remote_script}"
    ssh "${ssh_options[@]}" "${DEPLOY_USER}@${host}" "chmod 700 ${remote_script}"
}

read_current_releases() {
    ssh "${ssh_options[@]}" "${DEPLOY_USER}@${WEB_HOST}" \
        "${remote_script} current ${RELEASE_ID}" > "${runtime_dir}/web-release" &
    local web_current_pid="$!"
    ssh "${ssh_options[@]}" "${DEPLOY_USER}@${WORKER_HOST}" \
        "${remote_script} current ${RELEASE_ID}" > "${runtime_dir}/worker-release" &
    local worker_current_pid="$!"

    wait_for_processes "$web_current_pid" "$worker_current_pid"

    local web_release_id
    local worker_release_id
    web_release_id="$(<"${runtime_dir}/web-release")"
    worker_release_id="$(<"${runtime_dir}/worker-release")"

    if [[ ! "$web_release_id" =~ ^[0-9a-f]{40}$ ]] || [[ "$web_release_id" != "$worker_release_id" ]]; then
        echo "Web and worker must start on the same release before deployment." >&2
        return 1
    fi

    previous_release_id="$web_release_id"
}

prepare_release() {
    local host="$1"

    scp "${ssh_options[@]}" "$artifact_path" "${DEPLOY_USER}@${host}:${remote_artifact}"
    ssh "${ssh_options[@]}" "${DEPLOY_USER}@${host}" \
        "${remote_script} prepare ${RELEASE_ID} ${remote_artifact}"
}

run_migrations() {
    ssh "${ssh_options[@]}" "${DEPLOY_USER}@${WEB_HOST}" \
        "${remote_script} migrate ${RELEASE_ID}"
    ssh "${ssh_options[@]}" "${DEPLOY_USER}@${WEB_HOST}" \
        "${remote_script} migrate-media ${RELEASE_ID}"
}

activate_release() {
    local host="$1"

    ssh "${ssh_options[@]}" "${DEPLOY_USER}@${host}" \
        "${remote_script} activate ${RELEASE_ID}"
}

restart_services() {
    ssh "${ssh_options[@]}" "${DEPLOY_USER}@${WEB_HOST}" \
        'sudo systemctl restart php8.4-fpm && sudo systemctl reload nginx && sudo systemctl restart prodeals-ssr' &
    local web_restart_pid="$!"
    ssh "${ssh_options[@]}" "${DEPLOY_USER}@${WORKER_HOST}" \
        'sudo supervisorctl reread && sudo supervisorctl update && sudo supervisorctl restart prodeals-worker' &
    local worker_restart_pid="$!"

    wait_for_processes "$web_restart_pid" "$worker_restart_pid"
}

verify_hosts() {
    ssh "${ssh_options[@]}" "${DEPLOY_USER}@${WEB_HOST}" \
        "test \"\$(${remote_script} current ${RELEASE_ID})\" = \"${RELEASE_ID}\" && cd /var/www/prodeals/current && php8.4 artisan inertia:check-ssr && ss -ltn | grep -q \"127.0.0.1:13714\"" &
    local web_health_pid="$!"
    ssh "${ssh_options[@]}" "${DEPLOY_USER}@${WORKER_HOST}" \
        "test \"\$(${remote_script} current ${RELEASE_ID})\" = \"${RELEASE_ID}\" && sudo supervisorctl status prodeals-worker | grep -q RUNNING && ! ss -ltn | grep -q \"127.0.0.1:13714\"" &
    local worker_health_pid="$!"

    wait_for_processes "$web_health_pid" "$worker_health_pid"
}

smoke_homepage() {
    local homepage_html="${runtime_dir}/homepage.html"

    curl --fail --silent --show-error --max-time 30 https://prodeals.lk/ > "$homepage_html"
    grep -q 'Online Shopping &amp; Auctions in Sri Lanka' "$homepage_html"
    grep -qE 'href="[^"]*/(listings|auctions)' "$homepage_html"
}

smoke_product() {
    local product_html="${runtime_dir}/product.html"
    local product_sitemap_url
    local product_url

    product_sitemap_url="$(curl --fail --silent --show-error --max-time 30 https://prodeals.lk/sitemap.xml \
        | grep -oE '<loc>[^<]*sitemaps/products-[^<]+' | head -1 | cut -c6-)"
    product_url="$(curl --fail --silent --show-error --max-time 30 "$product_sitemap_url" \
        | grep -oE '<loc>[^<]+' | head -1 | cut -c6-)"
    curl --fail --silent --show-error --max-time 30 "$product_url" > "$product_html"
    grep -q '<h1' "$product_html"
    grep -qE 'Rs\.|LKR' "$product_html"
    grep -q 'application/ld+json' "$product_html"
}

smoke_mcp() {
    local response_path="${runtime_dir}/mcp-response.json"

    curl --fail --silent --show-error --max-time 30 \
        --header 'Accept: application/json, text/event-stream' \
        --header 'Content-Type: application/json' \
        --data '{"jsonrpc":"2.0","id":1,"method":"initialize","params":{"protocolVersion":"2025-06-18","capabilities":{},"clientInfo":{"name":"deployment-smoke-test","version":"1.0.0"}}}' \
        https://prodeals.lk/mcp/marketplace > "$response_path"
    grep -q '"protocolVersion":"2025-06-18"' "$response_path"
}

run_external_smoke_tests() {
    curl --fail --silent --show-error --output /dev/null --retry 20 --retry-delay 3 --retry-max-time 90 \
        --retry-connrefused --retry-all-errors https://prodeals.lk/up

    smoke_homepage &
    local homepage_pid="$!"
    smoke_product &
    local product_pid="$!"
    smoke_mcp &
    local mcp_pid="$!"
    wait_for_processes "$homepage_pid" "$product_pid" "$mcp_pid"
}

cleanup_release() {
    local host="$1"

    ssh "${ssh_options[@]}" "${DEPLOY_USER}@${host}" \
        "${remote_script} cleanup ${RELEASE_ID}"
}

remove_remote_files() {
    local host="$1"

    ssh "${ssh_options[@]}" "${DEPLOY_USER}@${host}" \
        "rm -f ${remote_artifact} ${remote_script}"
}

trap close_connections EXIT

run_stage 'Open SSH connections' run_on_hosts open_connection
run_stage 'Install release tooling' run_on_hosts install_release_script
run_stage 'Verify current releases' read_current_releases

trap rollback ERR

run_stage 'Upload and prepare releases' run_on_hosts prepare_release
run_stage 'Run database and media migrations' run_migrations
run_stage 'Stop worker' ssh "${ssh_options[@]}" "${DEPLOY_USER}@${WORKER_HOST}" 'sudo supervisorctl stop prodeals-worker || true'
run_stage 'Activate releases' run_on_hosts activate_release
run_stage 'Restart services' restart_services
run_stage 'Verify hosts' verify_hosts
run_stage 'Run production smoke tests' run_external_smoke_tests
run_stage 'Clean old releases' run_on_hosts cleanup_release

trap - ERR
run_stage 'Remove deployment files' run_on_hosts remove_remote_files
echo "Deployment completed in $((SECONDS - deployment_started_at))s."
