#!/usr/bin/env bash

set -Eeuo pipefail

readonly script_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
role="${1:-}"

if [[ "$role" != "web" && "$role" != "worker" ]]; then
    echo "Usage: sudo $0 {web|worker}" >&2
    exit 1
fi

if [[ ! -f /var/www/prodeals/shared/.env ]]; then
    echo "Install /var/www/prodeals/shared/.env before configuring services." >&2
    exit 1
fi

if [[ "$role" == "web" ]]; then
    if [[ ! -f /etc/ssl/cloudflare/prodeals.pem || ! -f /etc/ssl/cloudflare/prodeals.key ]]; then
        echo "Cloudflare Origin CA certificate and key must be installed first." >&2
        exit 1
    fi

    install -m 644 "${script_dir}/nginx-prodeals.conf" /etc/nginx/sites-available/prodeals
    ln -sfn /etc/nginx/sites-available/prodeals /etc/nginx/sites-enabled/prodeals
    rm -f /etc/nginx/sites-enabled/default
    nginx -t
    systemctl enable --now nginx php8.4-fpm
    systemctl reload nginx
else
    install -m 644 "${script_dir}/supervisor-prodeals.conf" /etc/supervisor/conf.d/prodeals-worker.conf
    install -m 644 "${script_dir}/scheduler-prodeals.cron" /etc/cron.d/prodeals-scheduler
    systemctl enable --now supervisor cron
    supervisorctl reread
    supervisorctl update
fi
