# Starter profiles

The repository keeps one executable codebase and exposes two startup profiles:

- `minimal`: authentication plus the schema-driven Product CRUD golden path.
- `showcase`: minimal plus Sales, invoices, embedded lines, workflow, sequence,
  audit and tenant examples.

The default is `showcase` so a fresh clone demonstrates the complete stack.
Start the smaller navigation and route surface with:

```bash
NUBIT_PROFILE=minimal docker compose up -d --build
```

Or set `VITE_NUBIT_PROFILE=minimal` when running Vite directly. Showcase pages
are lazy route chunks and are not requested by a minimal browser session.

The backend keeps showcase resources available in both profiles. This is
intentional: switching profiles never mutates migrations or deletes example
code. Applications generated from the template should delete unused entities
and generate a migration once their product scope is known.
