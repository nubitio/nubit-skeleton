# Dependency failure behavior

The default skeleton depends on PostgreSQL, configures a Doctrine-backed
Messenger transport, and uses Mercure for optional live refresh. No enabled
module produces user-facing queued work, and all storage-backed product modules
are disabled. Those capability paths must be tested in an opt-in profile rather
than enabled in every generated app.

| Failure | User-visible behavior | Retry guidance |
| --- | --- | --- |
| PostgreSQL unavailable | `/api/ready` returns `503` with a generic dependency code | Retry readiness and safe reads with backoff after connectivity is restored; do not blindly retry mutations |
| Mercure unavailable | The committed CRUD response is preserved; live refresh is lost and a warning is logged | Do not repeat a successful mutation; refresh the grid after the hub recovers |
| Expired refresh token | Refresh returns `401`; the user must sign in again | The same token is not retryable |
| Reused refresh token | Refresh returns `401` because tokens are single-use | Use the rotated token or sign in again |
| Queue unavailable in an opt-in module | Module-specific; workers use transport retries and the failed transport | Restore the transport and inspect `messenger:failed:*`; test with the module profile |
| Storage unavailable in an opt-in module | The operation must fail without exposing paths or credentials | Retry only when the operation documents idempotency; test with the module profile |

`scripts/check-failure-paths.sh` exercises the default composed application,
including secret canaries in responses and logs. Queue and storage resilience
belongs to a separate opt-in capability fixture because neither dependency is
used by a user-facing operation in the default application profile.

Restrict `/api/ready` to infrastructure probes at the ingress. It is
unauthenticated so an orchestrator can remove an unhealthy instance from
service even when authentication persistence is unavailable.
