#!/usr/bin/env bash
# TryPost — one-time setup for a fresh Ubuntu 24.04 Vultr server (ADR 0005).
# Safe to run again.
#
#   scp docker/vultr-setup.sh root@<server>:
#   ssh root@<server> bash vultr-setup.sh "<public key the Deploy workflow uses>"
#
# Installs Docker Engine with the compose plugin, adds a 2 GB swap file, lets
# only SSH, HTTP and HTTPS through ufw, turns on unattended security upgrades,
# turns off SSH password logins (when root has a key), and creates the
# `deploy` user that owns /opt/trypost.

set -euo pipefail

DEPLOY_USER=deploy
APP_DIR=/opt/trypost
DEPLOY_KEY="${1:-}"

if [ "$(id -u)" -ne 0 ]; then
    echo "Run this as root." >&2
    exit 1
fi

if [ -z "${DEPLOY_KEY}" ]; then
    echo "Usage: bash vultr-setup.sh \"<deploy public key>\"" >&2
    exit 1
fi

export DEBIAN_FRONTEND=noninteractive

echo "[setup] installing base packages"
apt-get update -q
apt-get install -y -q ca-certificates curl ufw unattended-upgrades

if ! command -v docker >/dev/null 2>&1; then
    echo "[setup] installing Docker Engine"
    install -m 0755 -d /etc/apt/keyrings
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
    chmod a+r /etc/apt/keyrings/docker.asc
    echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "${VERSION_CODENAME}") stable" \
        > /etc/apt/sources.list.d/docker.list
    apt-get update -q
    apt-get install -y -q docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
fi
systemctl enable --now docker

if ! grep -q '^/swapfile ' /proc/swaps; then
    echo "[setup] adding a 2 GB swap file"
    [ -f /swapfile ] || fallocate -l 2G /swapfile
    chmod 600 /swapfile
    mkswap /swapfile
    swapon /swapfile
fi
grep -q '^/swapfile ' /etc/fstab || echo '/swapfile none swap sw 0 0' >> /etc/fstab

# Docker publishes ports through its own iptables rules, outside ufw; only
# Caddy publishes any (80 and 443), and those are meant to be open.
echo "[setup] enabling the firewall (22, 80, 443)"
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw allow 443/udp
ufw --force enable

echo "[setup] turning on unattended security upgrades"
cat > /etc/apt/apt.conf.d/20auto-upgrades <<'CONF'
APT::Periodic::Update-Package-Lists "1";
APT::Periodic::Unattended-Upgrade "1";
CONF
systemctl enable --now unattended-upgrades

# Key-only SSH. Ubuntu reads sshd_config.d in name order and the first value
# wins, so 10- beats the 50-cloud-init.conf that Vultr may write. Skipped when
# root has no key yet, so a password-only server is not locked out.
if [ -s /root/.ssh/authorized_keys ]; then
    echo "[setup] turning off SSH password logins"
    cat > /etc/ssh/sshd_config.d/10-trypost.conf <<'CONF'
PasswordAuthentication no
KbdInteractiveAuthentication no
PermitRootLogin prohibit-password
CONF
    sshd -t
    systemctl reload ssh
fi

# The docker group can do anything root can, so the deploy key is as
# powerful as root's; keep it only in the Deploy workflow's secrets.
echo "[setup] creating the ${DEPLOY_USER} user"
id -u "${DEPLOY_USER}" >/dev/null 2>&1 || useradd --create-home --shell /bin/bash "${DEPLOY_USER}"
usermod -aG docker "${DEPLOY_USER}"
install -d -m 700 -o "${DEPLOY_USER}" -g "${DEPLOY_USER}" "/home/${DEPLOY_USER}/.ssh"
touch "/home/${DEPLOY_USER}/.ssh/authorized_keys"
grep -qxF "${DEPLOY_KEY}" "/home/${DEPLOY_USER}/.ssh/authorized_keys" || echo "${DEPLOY_KEY}" >> "/home/${DEPLOY_USER}/.ssh/authorized_keys"
chown "${DEPLOY_USER}:${DEPLOY_USER}" "/home/${DEPLOY_USER}/.ssh/authorized_keys"
chmod 600 "/home/${DEPLOY_USER}/.ssh/authorized_keys"

install -d -m 750 -o "${DEPLOY_USER}" -g "${DEPLOY_USER}" "${APP_DIR}"

echo "[setup] done. Next: put the production settings in ${APP_DIR}/.env (owned by ${DEPLOY_USER}, mode 600), then run the Deploy workflow."
