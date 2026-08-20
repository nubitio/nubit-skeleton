# Platform rollout: privacy, telemetry, analytics and flags

The canonical design is
[`nubit-symfony/docs/platform/saas-platform-roadmap.md`](https://github.com/nubitio/nubit-symfony/blob/main/docs/platform/saas-platform-roadmap.md).
This file describes how the starter adopts it after the corresponding packages are
released.

## Adoption policy

- Do not reference unreleased workspace classes from the starter.
- Every external backend is optional and disabled by default.
- Local defaults require no vendor account or network access.
- Credentials belong in secrets/environment configuration, never committed `.env`.
- Production activation must pass sensitive-data canary tests.

## Package gates

1. Publish `nubitio/platform` with sensitive-data and telemetry contracts.
2. Publish `@nubitio/core` with browser flag/analytics contracts.
3. Upgrade the Skeleton lockfiles.
4. Add service wiring and examples only after container/build compatibility passes.

## Planned local stack

```text
app ──OTLP──► otel-collector (Compose profile: observability)
                  ├── debug exporter for tests
                  └── optional Grafana-compatible backend

app ──outbox──► worker
                  ├── null/test analytics
                  ├── local webhook receiver
                  └── notification test transport
```

The Collector profile must be opt-in so ordinary `docker compose up` remains small.

## Environment contract

Planned variables follow OpenTelemetry conventions where possible:

```dotenv
OTEL_SDK_DISABLED=true
OTEL_SERVICE_NAME=nubit-skeleton
OTEL_EXPORTER_OTLP_ENDPOINT=http://otel-collector:4318
OTEL_EXPORTER_OTLP_PROTOCOL=http/protobuf
OTEL_TRACES_SAMPLER=parentbased_traceidratio
OTEL_TRACES_SAMPLER_ARG=0.1

NUBIT_ANALYTICS_PROVIDER=null
NUBIT_ANALYTICS_ENABLED=false
NUBIT_FEATURE_FLAG_PROVIDER=static
```

Provider secrets and write keys are intentionally absent from committed defaults.

## Starter examples

The Skeleton will demonstrate:

- `#[SensitiveData]` on email, tax identifiers and tokens;
- structured safe logging instead of interpolated payloads;
- a custom domain span around invoice issuance;
- an outbox event emitted with the invoice transaction;
- a backend analytics fact delivered asynchronously;
- a presentation flag projected through `/api/me`;
- React consumption through `FeatureFlagsProvider`;
- a webhook delivery with signature, retry and replay;
- a background import with progress and row-level failures.

## CI gates

- Collector debug exporter receives expected trace IDs but no canary secrets;
- all log/analytics/webhook sinks pass the same leak corpus;
- transaction rollback writes no outbox event;
- committed transaction is delivered idempotently;
- frontend build contains no secret environment variables;
- feature provider outage uses safe defaults;
- compatibility checks pin supported Nubit package versions.

## Operational checklist before production

- choose telemetry/analytics/flag providers and data regions;
- document purposes, retention and subprocessors;
- configure sampling and cost budgets;
- configure tenant deletion/export propagation;
- establish SLOs, alert ownership and runbooks;
- rotate HMAC/tokenization/exporter credentials;
- run restore, DLQ replay and provider-outage drills;
- verify warehouse and dashboards enforce tenant isolation.

## Phase mapping

| Platform phase | Skeleton artifact |
| --- | --- |
| sensitive-data kernel | annotated example entity and leak tests |
| observability | Collector profile, env contract, starter dashboard |
| outbox | migration, worker config, replay command example |
| analytics | null provider plus optional PostHog example |
| feature flags | static provider plus optional OpenFeature/flagd example |
| delivery capabilities | webhook receiver, mail catcher and job progress UI |
| warehouse | documented external profile, never part of default stack |
| metering | demo usage event and reconciliation test |
