# Analytics outbox

The skeleton enables Nubit analytics persistence by default. Tracking never
calls an external provider during the business request: sanitized events are
committed to `nubit_analytics_outbox`, scheduled as Messenger messages, and
delivered by a separate worker.

## Configure

Set these values through the deployment secret manager, not in committed env
files:

```dotenv
ANALYTICS_REDACTION_HMAC_KEY=<random-secret>
ANALYTICS_DELIVERY_ENDPOINT=https://analytics.example.com/events
ANALYTICS_DELIVERY_TOKEN=<bearer-token>
```

The endpoint must use HTTPS. An empty HMAC key makes the privacy policy drop
confidential values rather than emit correlatable hashes. Set
`ANALYTICS_ENABLED=false` to disable the module entirely.

## Run

Apply migrations, then start the scheduler and consumer:

```bash
docker compose exec app php bin/console doctrine:migrations:migrate --no-interaction
docker compose --profile analytics up -d analytics-scheduler analytics-worker
```

The scheduler periodically runs `nubit:analytics:dispatch-outbox`; the worker
consumes the `async` transport. Failed Messenger messages go to the `failed`
transport and can be inspected or retried with the standard
`messenger:failed:*` commands.

For production, run both commands under the platform process manager, scale
workers independently, and alert on pending/failed outbox age. The Compose
services are a local and single-host reference, not a cluster scheduler.

## Retention and recovery

Delivery is at-least-once, so downstream providers should deduplicate by event
ID. Purge delivered rows using `nubit:analytics:purge-outbox`; retention and
retry limits are configurable under `nubit_admin.analytics`.
