#!/bin/sh
set -e

# A named Docker volume is mounted at /app/.next/cache (Next.js ISR + fetch cache).
# On first creation that volume is owned by root and masks the image's nextjs-owned
# directory, so the unprivileged `nextjs` user gets EACCES when writing the cache.
# Fix ownership here on every start (idempotent + cheap), then drop to nextjs and run
# the server. The Node process therefore runs as nextjs, never as root.
if [ "$(id -u)" = "0" ]; then
  mkdir -p /app/.next/cache
  chown -R nextjs:nodejs /app/.next/cache
  exec su-exec nextjs:nodejs "$@"
fi

# Already unprivileged (container started with a forced user) — just exec.
exec "$@"
