# Observability

The skeleton includes an opt-in OpenTelemetry SDK and a local Collector. It is
disabled by default so normal development does not depend on telemetry
infrastructure.

## Local smoke test

Start the Collector profile and enable SDK autoloading for the command:

```bash
docker compose --profile observability up -d --build otel-collector app
docker compose exec -e OTEL_PHP_AUTOLOAD_ENABLED=true -e OTEL_METRIC_EXPORT_INTERVAL=1000 app php bin/otel-smoke.php
docker compose logs otel-collector
```

The Collector debug exporter should print a span and a metric named
`nubit.observability.smoke`. Collector readiness is exposed at
`http://localhost:13133/`; override `OTEL_HEALTH_PORT` if needed.

## Configuration

The app exports traces with OTLP over HTTP/protobuf to
`http://otel-collector:4318`. Override these environment variables for a hosted
Collector or vendor gateway:

- `OTEL_PHP_AUTOLOAD_ENABLED`: keep `false` unless telemetry is configured.
- `OTEL_SERVICE_NAME`: stable application/service identifier.
- `OTEL_EXPORTER_OTLP_ENDPOINT`: Collector base endpoint; do not embed secrets.
- `OTEL_EXPORTER_OTLP_HEADERS`: authentication headers, supplied through runtime
  secrets rather than committed files.
- `OTEL_RESOURCE_ATTRIBUTES`: low-cardinality deployment attributes such as
  `deployment.environment.name=production`.

Do not attach request bodies, authorization headers, cookies, raw database
parameters, email addresses, document numbers, or free-form exception messages
to telemetry. Nubit package instrumentation must pass attributes through its
sensitive-data policy before export.

## Production shape

Run a Collector or vendor gateway separately from the application, use TLS,
batch export, bounded retries, tail sampling where justified, and backend
retention controls. Telemetry failure must never make an ERP request fail.

Nubit emits privacy-safe HTTP, Messenger and DBAL spans plus RED metrics. Metric
dimensions intentionally exclude tenant identifiers, request identifiers,
URLs, SQL and message payloads. Configure backend aggregation and alerts by
route, operation and status rather than by customer.
