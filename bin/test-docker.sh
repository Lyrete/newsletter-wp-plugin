#!/usr/bin/env bash
#
# Run the real-WordPress smoke test against the Docker Compose stack.
#
# Usage:
#   bin/test-docker.sh

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

docker compose up -d db wordpress
docker compose run --rm wp-setup
docker compose run --rm --entrypoint wp wp-setup \
	eval-file /var/www/html/wp-content/plugins/newsletter-wp-plugin/tests/nlwp-smoke.php
