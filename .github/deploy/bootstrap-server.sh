#!/usr/bin/env bash

set -Eeuo pipefail

role="${1:-}"
deploy_public_key="${2:-}"

if [[ "$role" != "web" && "$role" != "worker" ]]; then
    echo "Usage: sudo $0 {web|worker} 'SSH_PUBLIC_KEY'" >&2
    exit 1
fi

if [[ ! "$deploy_public_key" =~ ^ssh-ed25519[[:space:]]+[A-Za-z0-9+/=]+([[:space:]].*)?$ ]]; then
    echo "A valid Ed25519 deployment public key is required." >&2
    exit 1
fi

export DEBIAN_FRONTEND=noninteractive

apt-get update
apt-get install -y ca-certificates cron curl default-mysql-client fail2ban gnupg software-properties-common unzip
add-apt-repository -y ppa:ondrej/php
apt-get update

php_packages=(
    php8.4-bcmath
    php8.4-cli
    php8.4-common
    php8.4-curl
    php8.4-gd
    php8.4-intl
    php8.4-mbstring
    php8.4-mysql
    php8.4-opcache
    php8.4-xml
    php8.4-zip
)

if [[ "$role" == "web" ]]; then
    php_packages+=(nginx php8.4-fpm)
else
    php_packages+=(supervisor)
fi

apt-get install -y "${php_packages[@]}"

composer_installer="/tmp/composer-setup.php"
composer_signature="$(curl -fsSL https://composer.github.io/installer.sig)"
curl -fsSL https://getcomposer.org/installer -o "$composer_installer"

if [[ "$(php8.4 -r "echo hash_file('sha384', '${composer_installer}');")" != "$composer_signature" ]]; then
    echo "Composer installer signature verification failed." >&2
    exit 1
fi

php8.4 "$composer_installer" --quiet --install-dir=/usr/local/bin --filename=composer
rm -f "$composer_installer"

if ! id deploy >/dev/null 2>&1; then
    adduser --disabled-password --gecos '' deploy
fi

install -d -m 700 -o deploy -g deploy /home/deploy/.ssh
printf '%s\n' "$deploy_public_key" > /home/deploy/.ssh/authorized_keys
chown deploy:deploy /home/deploy/.ssh/authorized_keys
chmod 600 /home/deploy/.ssh/authorized_keys
usermod -aG www-data deploy

install -d -m 2775 -o deploy -g www-data /var/www/prodeals /var/www/prodeals/releases /var/www/prodeals/shared
install -d -m 2775 -o deploy -g www-data /var/www/prodeals/shared/storage/app/private \
    /var/www/prodeals/shared/storage/framework/cache /var/www/prodeals/shared/storage/framework/sessions \
    /var/www/prodeals/shared/storage/framework/views /var/www/prodeals/shared/storage/logs

curl -fsSL https://truststore.pki.rds.amazonaws.com/global/global-bundle.pem \
    -o /etc/ssl/certs/aws-rds-global-bundle.pem
chmod 644 /etc/ssl/certs/aws-rds-global-bundle.pem

install -m 644 /dev/null /etc/ssh/sshd_config.d/99-prodeals.conf
printf '%s\n' \
    'PasswordAuthentication no' \
    'KbdInteractiveAuthentication no' \
    'PermitRootLogin no' \
    > /etc/ssh/sshd_config.d/99-prodeals.conf

install -m 440 /dev/null /etc/sudoers.d/prodeals-deploy
if [[ "$role" == "web" ]]; then
    printf '%s\n' \
        'deploy ALL=(root) NOPASSWD: /usr/bin/systemctl restart php8.4-fpm, /usr/bin/systemctl reload nginx' \
        > /etc/sudoers.d/prodeals-deploy
else
    printf '%s\n' \
        'deploy ALL=(root) NOPASSWD: /usr/bin/supervisorctl stop prodeals-worker, /usr/bin/supervisorctl restart prodeals-worker, /usr/bin/supervisorctl reread, /usr/bin/supervisorctl update' \
        > /etc/sudoers.d/prodeals-deploy
fi

visudo -cf /etc/sudoers.d/prodeals-deploy
systemctl enable --now fail2ban
systemctl reload ssh

if [[ "$role" == "worker" ]] && ! swapon --show=NAME --noheadings | grep -qx '/swapfile'; then
    fallocate -l 1G /swapfile
    chmod 600 /swapfile
    mkswap /swapfile
    swapon /swapfile
    printf '%s\n' '/swapfile none swap sw 0 0' >> /etc/fstab
fi
