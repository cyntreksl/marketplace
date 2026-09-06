#!/usr/bin/env bash

set -Eeuo pipefail

readonly script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
readonly node_keyring="/usr/share/keyrings/nodesource.gpg"
readonly node_source="/etc/apt/sources.list.d/nodesource.list"

if [[ "${EUID}" -ne 0 ]]; then
    echo "Run this installer as root." >&2
    exit 1
fi

export DEBIAN_FRONTEND=noninteractive

apt-get update
apt-get install -y ca-certificates curl gnupg

if [[ ! -f "$node_keyring" ]]; then
    curl -fsSL https://deb.nodesource.com/gpgkey/nodesource-repo.gpg.key \
        | gpg --dearmor -o "$node_keyring"
fi

printf '%s\n' 'deb [signed-by=/usr/share/keyrings/nodesource.gpg] https://deb.nodesource.com/node_22.x nodistro main' \
    > "$node_source"
apt-get update
apt-get install -y nodejs

if [[ "$(node --version)" != v22.* ]]; then
    echo "Node.js 22 installation failed." >&2
    exit 1
fi

install -m 644 "${script_dir}/prodeals-ssr.service" /etc/systemd/system/prodeals-ssr.service
install -m 440 /dev/null /etc/sudoers.d/prodeals-deploy
printf '%s\n' \
    'deploy ALL=(root) NOPASSWD: /usr/bin/systemctl restart php8.4-fpm, /usr/bin/systemctl reload nginx, /usr/bin/systemctl restart prodeals-ssr, /usr/bin/systemctl status prodeals-ssr' \
    > /etc/sudoers.d/prodeals-deploy
visudo -cf /etc/sudoers.d/prodeals-deploy

systemctl daemon-reload
systemctl enable prodeals-ssr.service
