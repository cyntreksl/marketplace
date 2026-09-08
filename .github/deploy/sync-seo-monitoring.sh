#!/usr/bin/env bash
set -Eeuo pipefail

readonly shared_dir="/var/www/prodeals/shared"
readonly environment_file="${shared_dir}/.env"
readonly credentials_file="${shared_dir}/secrets/google-merchant.json"
readonly current_release="/var/www/prodeals/current"
command_name="${1:-}"
transaction_id="${2:-}"

if [[ ! "$transaction_id" =~ ^[0-9]+-[0-9]+$ ]]; then
    echo "Invalid SEO configuration transaction ID." >&2
    exit 1
fi

readonly backup_file="${shared_dir}/.env.seo-${transaction_id}.backup"
readonly key_backup="${shared_dir}/.seo-key-${transaction_id}.backup"
readonly absent_marker="${shared_dir}/.seo-key-${transaction_id}.absent"

rebuild_cache() {
    (cd "$current_release" && php8.4 artisan config:cache --no-interaction >/dev/null)
}

rollback_configuration() {
    if [[ -f "$backup_file" ]]; then
        mv -f -- "$backup_file" "$environment_file"
        if [[ -f "$key_backup" ]]; then
            mv -f -- "$key_backup" "$credentials_file"
        elif [[ -f "$absent_marker" ]]; then
            rm -f -- "$credentials_file" "$absent_marker"
        fi
        rebuild_cache
    fi
}

apply_configuration() {
    test -f "$environment_file"
    (cd "$current_release" && php8.4 artisan list --raw | grep '^seo:check-merchant' >/dev/null)
    install -m 700 -d "${shared_dir}/secrets"
    local temporary_key
    local temporary_environment
    temporary_key="$(mktemp "${shared_dir}/secrets/.merchant.XXXXXX")"
    temporary_environment="$(mktemp "${shared_dir}/.env.seo.XXXXXX")"
    trap 'rm -f -- "$temporary_key" "$temporary_environment"; rollback_configuration' ERR
    chmod 600 "$temporary_key"
    cat > "$temporary_key"
    python3 - "$temporary_key" <<'PY'
import json, sys
try:
    data = json.load(open(sys.argv[1]))
    assert data.get("type") == "service_account"
    assert data.get("project_id") == "prodeals-merchant-monitoring"
    assert data.get("private_key", "").startswith("-----BEGIN PRIVATE KEY-----")
    assert data.get("client_email", "").endswith("@prodeals-merchant-monitoring.iam.gserviceaccount.com")
except Exception:
    sys.exit("Invalid Merchant service account credential file.")
PY
    cp --preserve=mode,ownership -- "$environment_file" "$backup_file"
    if [[ -f "$credentials_file" ]]; then
        cp --preserve=mode,ownership -- "$credentials_file" "$key_backup"
    else
        touch "$absent_marker"
    fi
    cp --preserve=mode,ownership -- "$environment_file" "$temporary_environment"
    sed -i -e '/^SEO_MONITORING_ENABLED=/d' -e '/^SEO_MONITORING_ALERT_EMAIL=/d' \
        -e '/^GOOGLE_MERCHANT_ACCOUNT_ID=/d' -e '/^GOOGLE_MERCHANT_SOURCE_ID=/d' \
        -e '/^GOOGLE_MERCHANT_CREDENTIALS_PATH=/d' "$temporary_environment"
    {
        printf '\nSEO_MONITORING_ENABLED=true\n'
        printf 'SEO_MONITORING_ALERT_EMAIL=prodealslk@gmail.com\n'
        printf 'GOOGLE_MERCHANT_ACCOUNT_ID=5849184229\n'
        printf 'GOOGLE_MERCHANT_SOURCE_ID=10722990075\n'
        printf 'GOOGLE_MERCHANT_CREDENTIALS_PATH=%s\n' "$credentials_file"
    } >> "$temporary_environment"
    mv -f -- "$temporary_key" "$credentials_file"
    mv -f -- "$temporary_environment" "$environment_file"
    rebuild_cache
    trap - ERR
}

case "$command_name" in
    apply) apply_configuration ;;
    rollback) rollback_configuration ;;
    commit) rm -f -- "$backup_file" "$key_backup" "$absent_marker" ;;
    *) echo "Usage: $0 {apply|rollback|commit} TRANSACTION_ID" >&2; exit 1 ;;
esac
