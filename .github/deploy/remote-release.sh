#!/usr/bin/env bash

set -Eeuo pipefail

readonly app_root="/var/www/prodeals"
readonly releases_dir="${app_root}/releases"
readonly shared_dir="${app_root}/shared"
readonly current_link="${app_root}/current"

command_name="${1:-}"
release_id="${2:-}"

if [[ ! "$release_id" =~ ^[0-9a-f]{40}$ ]]; then
    echo "Release ID must be a full Git commit SHA." >&2
    exit 1
fi

release_dir="${releases_dir}/${release_id}"

prepare_release() {
    local artifact_path="${3:-}"
    local active_release

    if [[ ! "$artifact_path" =~ ^/tmp/prodeals-[0-9a-f]{40}\.tar\.gz$ ]] || [[ ! -f "$artifact_path" ]]; then
        echo "Release artifact is missing or invalid." >&2
        exit 1
    fi

    if [[ ! -f "${shared_dir}/.env" ]]; then
        echo "Production environment file is missing at ${shared_dir}/.env." >&2
        exit 1
    fi

    active_release="$(readlink -f "$current_link" 2>/dev/null || true)"
    if [[ "$active_release" == "$release_dir" ]]; then
        echo "Release ${release_id} is already active."
        return 0
    fi

    if [[ -d "$release_dir" ]]; then
        rm -rf -- "$release_dir"
    fi

    mkdir -p "$release_dir" "${shared_dir}/storage/app/private" "${shared_dir}/storage/framework/cache" \
        "${shared_dir}/storage/framework/sessions" "${shared_dir}/storage/framework/views" "${shared_dir}/storage/logs"
    tar -xzf "$artifact_path" -C "$release_dir"
    ln -s "${shared_dir}/.env" "${release_dir}/.env"
    ln -s "${shared_dir}/storage" "${release_dir}/storage"
    mkdir -p "${release_dir}/bootstrap/cache"
    chmod -R ug+rwX "${release_dir}/bootstrap/cache" "${shared_dir}/storage"

    (
        cd "$release_dir"
        php8.4 artisan optimize
    )
}

migrate_release() {
    (
        cd "$release_dir"
        php8.4 artisan migrate --force
    )
}

activate_release() {
    local previous_release
    local next_link="${app_root}/.current-${release_id}"

    previous_release="$(readlink -f "$current_link" 2>/dev/null || true)"
    if [[ -n "$previous_release" ]]; then
        printf '%s\n' "$previous_release" > "${shared_dir}/previous_release"
    fi

    ln -s "$release_dir" "$next_link"
    mv -Tf "$next_link" "$current_link"
}

rollback_release() {
    local previous_release
    local rollback_link="${app_root}/.rollback-${release_id}"

    previous_release="$(cat "${shared_dir}/previous_release" 2>/dev/null || true)"
    if [[ -z "$previous_release" ]] || [[ ! -d "$previous_release" ]] || [[ "$previous_release" != "${releases_dir}/"* ]]; then
        echo "No valid previous release is available for rollback." >&2
        return 0
    fi

    ln -s "$previous_release" "$rollback_link"
    mv -Tf "$rollback_link" "$current_link"
}

cleanup_releases() {
    local current_release
    local previous_release
    local index
    local release_path
    local -a release_paths

    current_release="$(readlink -f "$current_link" 2>/dev/null || true)"
    previous_release="$(cat "${shared_dir}/previous_release" 2>/dev/null || true)"
    mapfile -t release_paths < <(
        find "$releases_dir" -mindepth 1 -maxdepth 1 -type d -printf '%T@ %p\n' \
            | sort -nr \
            | cut -d' ' -f2-
    )

    for ((index = 5; index < ${#release_paths[@]}; index++)); do
        release_path="$(realpath "${release_paths[$index]}")"

        if [[ "$release_path" == "$current_release" ]] || [[ "$release_path" == "$previous_release" ]]; then
            continue
        fi

        if [[ "$release_path" != "${releases_dir}/"* ]]; then
            echo "Refusing to remove an invalid release path: ${release_path}" >&2
            exit 1
        fi

        rm -rf -- "$release_path"
    done
}

case "$command_name" in
    prepare)
        prepare_release "$@"
        ;;
    migrate)
        migrate_release
        ;;
    activate)
        activate_release
        ;;
    rollback)
        rollback_release
        ;;
    cleanup)
        cleanup_releases
        ;;
    *)
        echo "Usage: $0 {prepare|migrate|activate|rollback|cleanup} RELEASE_ID [ARTIFACT]" >&2
        exit 1
        ;;
esac
