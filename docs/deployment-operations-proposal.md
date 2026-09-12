# Deployment & operations: a decision-ready proposal

Status: proposed — **not implemented**. Answers #5 (epic), #7, #8, #9.
Scope: `nubit-skeleton` only.

## Why this is a proposal and not a PR

#7, #8 and #9 each need a decision this repository cannot make on its own:
which host runs the container, which object store holds a backup, which
telemetry backend receives spans. Building any of these against a guessed
target (a specific cloud, a specific SaaS) would either lock every project
generated from this template into that guess, or be thrown away the moment a
real target is chosen. Neither is a good use of the "starter" contract this
repo has with everything downstream of it.

What follows is not aspirational — every option below is something this
template's existing dependencies (Docker, Flysystem, OpenTelemetry, GitHub
Actions) can already do. The only missing ingredient is which concrete
provider a team picks, which is exactly the part deliberately left blank.

---

## #7 — Deploy workflow, immutable artifact promotion

**Current state.** `.github/workflows/ci.yml` builds and tests; nothing
publishes or deploys anything. `compose.yaml` is dev-only (bind mount,
hardcoded dev secrets, no restart policy, no resource limits).

**Recommended shape**, platform-agnostic:

1. On a pushed tag (`v*`), build the `app` image from the existing
   `Dockerfile` and push it to GHCR, tagged with the tag *and* the commit
   SHA — the immutable artifact. (`Dockerfile` already installs its own
   Composer dependencies via `docker/entrypoint.sh`, added for #2, so the
   built image needs no separate "bake vendor/ in" step beyond what already
   works.)
2. A *separate*, manually triggered `workflow_dispatch` job promotes a
   specific image digest to a named environment (`staging`, `production`)
   by writing it wherever the target host reads its desired version from —
   this is the piece that's genuinely platform-specific and has to stay a
   fill-in-the-blank:
   - **A single VPS / bare Docker host**: SSH in, `docker compose pull &&
     docker compose up -d` against a `compose.prod.yaml` pinning the image
     by digest. Simplest, no new infra, but no orchestrated rollout and a
     human (or a cron) has to run it.
   - **Kubernetes** (self-hosted or managed — EKS/GKE/AKS/etc.): a
     Deployment manifest updated by digest, applied via `kubectl` or a
     GitOps controller (Argo CD/Flux). Gets rolling updates and readiness
     gating essentially for free; is the most infrastructure to own.
   - **A PaaS with an image-deploy API** (Fly.io, Railway, Render, AWS App
     Runner, Google Cloud Run): usually the least new code — the provider's
     CLI/API takes an image reference and handles rollout — at the cost of
     coupling the deploy job (not the app itself) to that provider.
3. Rollback is "promote the previous digest" through the same job — which is
   exactly why the artifact has to be immutable and content-addressed in the
   first place, not `latest`.

**Decision needed:** which of the three deploy targets above (or another),
before step 2 can be written as an actual workflow rather than a description
of one.

---

## #8 — Encrypted remote backups with a verified restore drill

**Current state.** `nubit_admin.backup` (the bundle module) is real and
already implemented — `PostgresTenantBackupRunner` (`pg_dump --format=custom`,
shelled out safely with an argument array, credentials via `PGPASSWORD` env
rather than argv) persists through the app's own Flysystem
`FilesystemOperator`. The skeleton just never configures it, so it defaults
to local-disk-only and off (`config/reference.php`:
`backup.enabled` → `false`, `backup.storage.local_directory` →
`%kernel.project_dir%/var/backups`).

**What "encrypted and remote" does *not* require deciding first:** the
runner already accepts *any* Flysystem adapter, so plugging in
`league/flysystem-aws-s3-v3`, `-google-cloud-storage`, `-azure-blob-storage`,
or any S3-compatible adapter (Backblaze B2, Cloudflare R2, MinIO for
self-hosting) is the same few lines of service wiring regardless of which
one is picked — this repo can ship that wiring generically, parameterized by
environment variables, the same way `nubit_admin.media.storage.filesystem`
already works.

**What does need a decision:**

| Decision | Options | Notes |
| --- | --- | --- |
| Object store | S3 / GCS / Azure Blob / R2 / B2 / self-hosted MinIO | Any Flysystem adapter works identically from the app's side. |
| Encryption | (a) provider-side encryption at rest (SSE-S3/SSE-KMS or equivalent) (b) client-side, encrypt the dump before upload (age or gpg, piped between `pg_dump` and the Flysystem write) | (b) is provider-independent and survives a compromised bucket; (a) is zero extra code but trusts the provider's key management. Can do both. |
| Retention / RPO / RTO | e.g. daily dumps, 30-day retention, RPO ≤ 24h, RTO ≤ 1h | Drives the cron schedule and the restore drill's own pass/fail threshold. |

**Restore drill (can ship regardless of the above):** a documented,
periodically-run procedure — `pg_restore` the most recent dump into a
throwaway database, run `doctrine:schema:validate` and a row-count sanity
check against a couple of tables, tear it down — is provider-agnostic and
should be written and scheduled (e.g. a weekly CI job) as its own piece of
work once the object store is picked, since the drill needs somewhere real
to restore *from*.

---

## #9 — SLOs, alerts, ownership and incident runbooks

**Current state.** Real, but dev-only: `docs/observability.md` + the
`otel-collector` Compose profile export spans/metrics to a local debug
exporter for smoke-testing the instrumentation — not a production sink.
Nothing defines an SLO, an alert threshold, an owner, or an incident
procedure; `docs/platform-rollout.md`'s "Operational checklist before
production" still lists all of this as pending.

**Recommended shape:**

1. **Telemetry backend** — the app already speaks standard OTLP
   (`OTEL_EXPORTER_OTLP_ENDPOINT`/`_PROTOCOL`), so *this* repo's job is
   done once that env var points somewhere real; which somewhere is the
   decision:
   - Self-hosted Prometheus + Grafana + Tempo/Loki (full control, most to
     operate).
   - A managed OTLP-native backend (Grafana Cloud, Honeycomb, Datadog,
     New Relic, etc. — most take OTLP directly, no code change, just the
     endpoint + an auth header).
2. **SLOs** — propose starting with three, all directly measurable from
   existing spans/logs with no new instrumentation:
   - Availability: successful (`2xx`/`3xx`/expected `401`) responses ÷ total,
     target e.g. 99.5%.
   - Latency: p95 request duration under a target (e.g. 500ms) for the API.
   - Error budget: 5xx rate under a target (e.g. 0.1%) over a rolling window.
3. **Alerting** — thresholds on the above, routed to whatever the team
   already pages through (PagerDuty/Opsgenie/Slack) — deliberately not
   picked here since it's usually already decided by a team's *other*
   services, not this one.
4. **Runbooks** — write once the above exist, covering at minimum: database
   unreachable, failed deploy (→ rollback per #7), backup/restore failure
   (→ #8's drill), and a generic "error budget burn" page. Each needs an
   owner, not just a procedure — this repo can supply the template, not the
   name.

---

## What this repo can ship without an external decision

Everything in the "does not require deciding first" callouts above, plus:

- A `compose.prod.yaml` (or equivalent) that removes the dev bind mount,
  hardcoded secrets, and published-by-default ports from `compose.yaml`,
  demonstrating a production-shaped Compose file without committing to a
  specific host to run it on.
- The Flysystem backup wiring, parameterized by environment variables, so
  picking a provider later is configuration, not code.
- The restore-drill script and SLO/runbook templates, as fill-in-the-blank
  documents.

These are natural, decision-free follow-ups once this proposal is reviewed;
deliberately not included here to keep this document a proposal, not a
sneak-in implementation.
