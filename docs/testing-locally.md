# Testing locally before pushing

[← Back to README](../README.md)

The repository ships two CI workflows under `.github/workflows/`:

- `integration-tests.yml` — Magento integration tests via the [extdn action](https://github.com/extdn/github-actions-m2).
- `e2e-tests.yml` — Playwright end-to-end tests.

Both run automatically on GitHub. To validate them on your machine before pushing,
use [`act`](https://github.com/nektos/act). Always target a single workflow with
`-W`; a bare `act` would try to parse the support YAML files (e.g. the
`docker-compose*.yml`) as workflows and fail.

```shell
gh act push -W .github/workflows/e2e-tests.yml
```

## Integration tests with `act`

The integration workflow declares `mysql` and `es` as job `services:`. **`act`
cannot run those services the way GitHub does**: it does not expose a service's
hostname to a Docker container action (the extdn action is one), so the run fails
with `getaddrinfo for host "mysql" ... Temporary failure in name resolution`, and
it still starts the services and grabs ports `3306`/`9200`, causing conflicts.
See nektos/act [#944](https://github.com/nektos/act/issues/944),
[#835](https://github.com/nektos/act/issues/835) and
[#5885](https://github.com/nektos/act/issues/5885).

To work around this, run the integration tests locally with the dedicated,
**local-only** setup in `.github/workflows/integration-tests/`:

- `docker-compose.yml` — brings up the same MySQL 8.0 + Elasticsearch 8.11
  dependencies on a named network (`integration-local`), exposing the `mysql` and
  `es` hostnames the extdn action expects.
- `act-integration.yml` — covers the same two flows **without** the `services:`
  block (so `act` does not start/conflict on those services), and as **two
  discrete jobs** (`tfa_off`, `tfa_on`) rather than a matrix. It lives in a
  subfolder, so GitHub never runs it — it is strictly local.

The two flows are `tfa-off` and `tfa-on` (native `Magento_TwoFactorAuth` disabled
vs. enabled). Each flow first drops and recreates the `magento2`/`magento2test`
databases (via the `reset-integration-db.sh` pre-install script), so re-running
against the same local MySQL is self-healing — you do **not** need to recreate
the DB between runs.

**Run one job per invocation** with `-j tfa_off` / `-j tfa_on`, and tear the
dependencies down between flows so each starts from a clean DB. The two commands
below are the whole thing — the `tfa_off` block, then the `tfa_on` block:

```shell
# ── Flow 1: tfa-off (native Magento_TwoFactorAuth disabled) ──────────────────
# Start the deps on the shared network the extdn action resolves `mysql`/`es` on
docker compose -f .github/workflows/integration-tests/docker-compose.yml up -d --wait
# Run just this flow's job
gh act push -W .github/workflows/integration-tests/act-integration.yml \
  -j tfa_off --network integration-local
# Tear down: remove any leftover act runner FIRST, then the deps (see note below)
docker rm -f $(docker ps -aq --filter "name=act-Integration-Tests-local") 2>/dev/null
docker compose -f .github/workflows/integration-tests/docker-compose.yml down -v

# ── Flow 2: tfa-on (native Magento_TwoFactorAuth enabled) ────────────────────
docker compose -f .github/workflows/integration-tests/docker-compose.yml up -d --wait
gh act push -W .github/workflows/integration-tests/act-integration.yml \
  -j tfa_on --network integration-local
docker rm -f $(docker ps -aq --filter "name=act-Integration-Tests-local") 2>/dev/null
docker compose -f .github/workflows/integration-tests/docker-compose.yml down -v
```

**Do not** run both flows in one `act` call (no `-j`, or a matrix): `act`
mis-reports the overall run when one matrix combination fails
([nektos/act#1518](https://github.com/nektos/act/issues/1518)) — it runs the
sibling combo too, which races the other on the shared MySQL and flags the whole
run `Job failed` even though the flow you cared about passed. That is exactly why
the local workflow uses **two discrete jobs** instead of the matrix the CI
workflow keeps (on GitHub each combination gets its own runner and fresh
services, so the problem never arises there).

**Teardown order matters.** `act` attaches its job runner to the
`integration-local` network, and when a run ends on the job-level "Job failed"
bookkeeping (the tests themselves still pass) it can leave that runner container
up. A container with an active endpoint blocks network removal, so
`docker compose ... down -v` fails with:

```
network integration-local has active endpoints (act-Integration-Tests-local-...)
```

Removing the leftover `act` containers first clears the endpoints, then the
network (and the MySQL/ES dependencies) tear down cleanly:

```shell
docker rm -f $(docker ps -aq --filter "name=act-Integration-Tests-local") 2>/dev/null
docker compose -f .github/workflows/integration-tests/docker-compose.yml down -v
```

> Keep the two jobs' inputs in `act-integration.yml` in sync with the matrix in
> `integration-tests.yml` (module/composer names, `magento_version`,
> `phpunit_file`, the pre/post-install scripts). The local file intentionally
> differs in structure — it drops the `services:` block and splits the matrix
> into discrete jobs, both unusable/unreliable under `act`. The CI workflow on
> GitHub remains the source of truth.
