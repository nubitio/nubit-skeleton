# Security policy

## Reporting a vulnerability

Report privately through GitHub: open the **Security** tab of this repository
and choose **Report a vulnerability**. That opens a private advisory visible
only to you and the maintainers.

Please do **not** open a public issue, pull request, or discussion for a
suspected vulnerability.

### What to expect

- **Acknowledgement within 5 business days.** If you have not heard back,
  assume the report was missed and escalate by opening a public issue that says
  only that you filed an advisory and got no reply — no details.
- An assessment and a fix timeline once the report is confirmed.
- Credit in the advisory unless you ask otherwise.
- We ask that you hold public disclosure until a patched release exists, or 90
  days from the report, whichever comes first.

There is no bug bounty.

## What this repository is

This is a **template**. You copy it once and it becomes your application; there
is no upgrade path back to it and no version of it installed in your project.
That changes what a vulnerability means here:

- A flaw in the template is not a flaw in a running system — it is a flaw that
  gets **copied into every project scaffolded after it**, and stays there.
  Those projects never get a patch, because they no longer track this
  repository. So an insecure default here is more durable than an insecure
  default in a library, not less.
- The reverse also holds: a fix here does nothing for projects already
  scaffolded. If a report affects existing users, say so, and we will publish
  the remediation steps in the advisory rather than only the commit.

If the flaw is in `nubitio/*` or `@nubitio/*` rather than in the template's own
code or configuration, report it against
[`nubit-symfony`](https://github.com/nubitio/nubit-symfony) or
[`nubit-react`](https://github.com/nubitio/nubit-react) — that is where a patch
reaches deployed applications.

## Supported versions

The template is supported at `main` only. There are no tagged releases to
backport to; the fix for any report is a commit on `main`.

The package lines this template is verified against are declared in
`nubit-compatibility.json` and checked in CI. Today that is backend `0.15` and
frontend `0.11`.

## Scope

In scope, and the most valuable kind of report here:

- **A default that is unsafe in production** — permissive CORS, a security
  header that is missing, a Docker or Compose setting that exposes something it
  should not, a migration that grants more than it needs.
- **A documented pattern that produces an insecure result** — if following the
  README or `docs/` leads to a vulnerable application, that is a template bug
  even when every individual line is correct.
- **A gap in `App\Security\ProductionReadiness`** — the detector that refuses
  template values for `APP_SECRET`, `DATABASE_URL` and `MERCURE_JWT_SECRET`. A
  template placeholder that reaches production *without* the detector catching
  it is in scope, and is exactly the class of bug it exists to prevent.

Explicitly **not** vulnerabilities:

- The placeholder secrets in `.env` (`change-me-to-a-random-32-byte-secret!!`,
  `!ChangeMe!`, `!ChangeThisMercureHubJWTSecretKey!`). They are deliberately
  invalid, they are checked in on purpose so the template runs locally, and
  `ProductionReadiness` fails the boot when they survive into a production
  build. Reporting them as leaked credentials is a false positive; reporting a
  path where they are *accepted* in production is a real finding.
- `APP_ENV=dev` in `.env`, and the development-only settings that follow from
  it.
- The seeded demo user created by `bin/console app:seed`.
