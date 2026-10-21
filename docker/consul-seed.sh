#!/bin/sh
# Loads runtime configuration into the local config agent (consul) once it is
# ready. Values come from the container environment; the app itself reads them
# from its own env, this just mirrors them into the shared config store the way
# a service-discovery setup would. In-memory dev agent, so this runs on every
# container start.
set -u

ADDR="127.0.0.1:8500"

# Wait for the agent's HTTP API to come up.
i=0
while [ "$i" -lt 60 ]; do
    if consul kv put -http-addr="$ADDR" taintedport/meta/seeded 1 >/dev/null 2>&1; then
        break
    fi
    i=$((i + 1))
    sleep 0.5
done

consul kv put -http-addr="$ADDR" taintedport/config/app_env     "production" >/dev/null 2>&1
consul kv put -http-addr="$ADDR" taintedport/config/log_level   "info" >/dev/null 2>&1
consul kv put -http-addr="$ADDR" taintedport/config/db_path     "/var/www/backend/database.db" >/dev/null 2>&1
consul kv put -http-addr="$ADDR" taintedport/secret/jwt_signing_secret "${JWT_SIGNING_SECRET:-}" >/dev/null 2>&1

echo "[init] config agent seeded"
