#!/usr/bin/env bash

set -Eeuo pipefail

readonly app_root="/var/www/prodeals"
readonly shared_dir="${app_root}/shared"
readonly environment_file="${shared_dir}/.env"
readonly current_release="${app_root}/current"

command_name="${1:-}"
transaction_id="${2:-}"

if [[ ! "$transaction_id" =~ ^[0-9]+-[0-9]+$ ]]; then
    echo "Configuration transaction ID is invalid." >&2
    exit 1
fi

readonly backup_file="${shared_dir}/.env.meta-${transaction_id}.backup"

rebuild_cache() {
    (
        cd "$current_release"
        php8.4 artisan config:cache --no-interaction >/dev/null
    )
}

rollback_configuration() {
    if [[ -f "$backup_file" ]]; then
        mv -f -- "$backup_file" "$environment_file"
        rebuild_cache
    fi
}

apply_configuration() {
    local access_token
    local access_token_encoded
    local api_version
    local api_version_encoded
    local enabled
    local enabled_encoded
    local pixel_id
    local pixel_id_encoded
    local temporary_file

    IFS= read -r enabled_encoded
    IFS= read -r pixel_id_encoded
    IFS= read -r access_token_encoded
    IFS= read -r api_version_encoded

    enabled="$(printf '%s' "$enabled_encoded" | base64 --decode)"
    pixel_id="$(printf '%s' "$pixel_id_encoded" | base64 --decode)"
    access_token="$(printf '%s' "$access_token_encoded" | base64 --decode)"
    api_version="$(printf '%s' "$api_version_encoded" | base64 --decode)"

    if [[ ! "$enabled" =~ ^(true|false)$ ]] || [[ ! "$pixel_id" =~ ^[0-9]+$ ]] || [[ -z "$access_token" ]] || [[ ! "$api_version" =~ ^v[0-9]+\.[0-9]+$ ]]; then
        echo "Meta configuration values are invalid." >&2
        exit 1
    fi

    if [[ ! -f "$environment_file" ]] || [[ ! -d "$current_release" ]]; then
        echo "The production environment or current release is missing." >&2
        exit 1
    fi

    cp --preserve=mode,ownership -- "$environment_file" "$backup_file"
    temporary_file="$(mktemp "${shared_dir}/.env.meta.XXXXXX")"
    trap 'rm -f -- "$temporary_file"; rollback_configuration' ERR
    cp --preserve=mode,ownership -- "$environment_file" "$temporary_file"

    sed -i \
        -e '/^META_CONVERSIONS_ENABLED=/d' \
        -e '/^META_CONVERSIONS_PIXEL_ID=/d' \
        -e '/^META_CONVERSIONS_ACCESS_TOKEN=/d' \
        -e '/^META_CONVERSIONS_API_VERSION=/d' \
        "$temporary_file"

    {
        printf '\nMETA_CONVERSIONS_ENABLED=%s\n' "$enabled"
        printf 'META_CONVERSIONS_PIXEL_ID=%s\n' "$pixel_id"
        printf 'META_CONVERSIONS_ACCESS_TOKEN=%s\n' "$access_token"
        printf 'META_CONVERSIONS_API_VERSION=%s\n' "$api_version"
    } >> "$temporary_file"

    mv -f -- "$temporary_file" "$environment_file"
    rebuild_cache
    trap - ERR
}

case "$command_name" in
    apply)
        apply_configuration
        ;;
    rollback)
        rollback_configuration
        ;;
    commit)
        rm -f -- "$backup_file"
        ;;
    *)
        echo "Usage: $0 {apply|rollback|commit} TRANSACTION_ID" >&2
        exit 1
        ;;
esac
