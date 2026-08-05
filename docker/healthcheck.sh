#!/usr/bin/env sh
set -eu

case "${CONTAINER_ROLE:-web}" in
  web)
    curl --fail --silent --show-error http://127.0.0.1:8080/up >/dev/null
    ;;
  worker)
    tr '\000' ' ' </proc/1/cmdline | grep -Fq 'artisan horizon'
    ;;
  scheduler)
    tr '\000' ' ' </proc/1/cmdline | grep -Fq 'artisan schedule:work'
    ;;
  *)
    echo "Unsupported CONTAINER_ROLE for healthcheck." >&2
    exit 1
    ;;
esac
