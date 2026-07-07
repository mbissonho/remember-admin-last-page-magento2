#!/bin/bash
set -e

# The Playwright image ships without a Docker client. global-setup.js needs one to
# `docker exec` into the Magento container (via the mounted /var/run/docker.sock) and
# verify the store scenario. Install just the static docker CLI binary if missing.
if ! command -v docker >/dev/null 2>&1; then
  DOCKER_CLI_VERSION="${DOCKER_CLI_VERSION:-27.3.1}"
  curl -fsSL "https://download.docker.com/linux/static/stable/x86_64/docker-${DOCKER_CLI_VERSION}.tgz" \
    | tar -xz -C /usr/local/bin --strip-components=1 docker/docker
fi

cd /tmp/module/e2e
rm -rf package-lock.json
npm i
npx playwright install --with-deps
npx playwright test -c playwright-ci.config.js
