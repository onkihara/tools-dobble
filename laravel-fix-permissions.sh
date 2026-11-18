#!/bin/sh
set -eu

BASE=/apps/www

if [ "$#" -ne 3 ]; then
    echo "Usage: $0 tools/<app> <uid> <gid>" >&2
    exit 64
fi

APP_REL=$1
OWNER_UID=$2
OWNER_GID=$3

case "$APP_REL" in
    tools/*) APP_NAME=${APP_REL#tools/} ;;
    *) echo "App path must be tools/<app>" >&2; exit 64 ;;
esac

case "$APP_NAME" in
    ""|.|..|*/*) echo "App must be a direct child of tools" >&2; exit 64 ;;
esac

case "$OWNER_UID" in
    82|33) ;;
    *) echo "UID is not allowed: $OWNER_UID" >&2; exit 64 ;;
esac

case "$OWNER_GID" in
    82|tape) ;;
    *) echo "GID is not allowed: $OWNER_GID" >&2; exit 64 ;;
esac

BASE_REAL=$(realpath -e -- "$BASE")
APP_REAL=$(realpath -e -- "$BASE_REAL/$APP_REL")

case "$APP_REAL" in
    "$BASE_REAL/tools/"*) ;;
    *) echo "Resolved app path must be under $BASE/tools" >&2; exit 64 ;;
esac

APP_NAME_REAL=${APP_REAL#"$BASE_REAL/tools/"}
case "$APP_NAME_REAL" in
    ""|*/*) echo "Resolved app must be a direct child of tools" >&2; exit 64 ;;
esac

if [ -L "$APP_REAL/storage" ] ||
   [ -L "$APP_REAL/bootstrap" ] ||
   [ -L "$APP_REAL/bootstrap/cache" ]; then
    echo "Writable paths must not be symlinks" >&2
    exit 1
fi

#mkdir -p "$APP_REAL/storage" "$APP_REAL/bootstrap/cache"
chown -R "$OWNER_UID:$OWNER_GID" "$APP_REAL/storage" "$APP_REAL/bootstrap/cache"
find "$APP_REAL/storage" "$APP_REAL/bootstrap/cache" -type d -exec chmod 775 {} +
find "$APP_REAL/storage" "$APP_REAL/bootstrap/cache" -type f -exec chmod 664 {} +