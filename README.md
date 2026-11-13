# WoodStone RMS

Restaurant management system for Woodstone Restaurant & Cafe, Butwal (Softechpark).

- Build plan and quality gates: [`docs/BUILD-PLAN.md`](docs/BUILD-PLAN.md)
- Decisions: [`docs/DECISIONS.md`](docs/DECISIONS.md) · Requirements: [`docs/requirements.md`](docs/requirements.md)

## Run locally
```bash
composer setup   # install, .env, key, migrate, build
composer dev     # app + vite + queue
composer ci:check  # the same checks CI runs
```
