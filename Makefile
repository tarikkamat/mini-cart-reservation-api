.PHONY: help up up-prod down restart build rebuild logs ps shell \
        install migrate seed fresh test test-concurrency pint pint-test \
        artisan tinker key cache-clear docs docs-export

# Compose files are explicitly named so a reviewer can tell at a glance which
# is which — there is no auto-merged `docker-compose.override.yml` magic. The
# dev stack is the prod stack with the dev overlay applied via `-f`.
COMPOSE_PROD := docker-compose.prod.yml
COMPOSE_DEV  := docker-compose.dev.yml

DC      := docker compose -f $(COMPOSE_PROD) -f $(COMPOSE_DEV)
DC_PROD := docker compose -f $(COMPOSE_PROD)
APP     := $(DC) exec app

# Run a command inside the app container if it's up, otherwise fall back to the host.
# Lets `make docs`, `make test`, etc. work whether or not docker compose is running.
define RUN
	@if $(DC) ps --services --status=running 2>/dev/null | grep -qx app; then \
		$(APP) $(1); \
	else \
		$(1); \
	fi
endef

help:
	@echo "Mini Cart Reservation API — common make targets"
	@echo ""
	@echo "  Stack lifecycle:"
	@echo "    up               Start dev stack (prod + dev overlay: bind-mounted source, seeded DB)"
	@echo "    up-prod          Start prod stack only (no dev overlay) — for smoke-testing the prod image"
	@echo "    down             Stop and remove containers"
	@echo "    restart          Restart all services"
	@echo "    build            Build images"
	@echo "    rebuild          Build images without cache"
	@echo "    logs             Tail logs from all services"
	@echo "    ps               Show service status"
	@echo "    shell            Open a shell inside the app container"
	@echo ""
	@echo "  Application:"
	@echo "    install          composer install inside the app container"
	@echo "    key              php artisan key:generate"
	@echo "    migrate          Run pending migrations"
	@echo "    seed             Run database seeders"
	@echo "    fresh            migrate:fresh --seed"
	@echo "    cache-clear      Clear config/cache/route/view caches"
	@echo "    artisan CMD=...  Run an arbitrary artisan command (e.g. make artisan CMD='route:list')"
	@echo "    tinker           Open Laravel Tinker"
	@echo ""
	@echo "  Quality:"
	@echo "    test             Run Pest test suite"
	@echo "    test-concurrency Run only the concurrency-tagged tests"
	@echo "    pint             Run Laravel Pint formatter"
	@echo "    pint-test        Run Pint in check-only mode"
	@echo ""
	@echo "  Docs:"
	@echo "    docs             Print URLs for live Scramble docs (UI + OpenAPI spec)"
	@echo "    docs-export      Snapshot the OpenAPI spec to docs/openapi.json"

# ----- Stack lifecycle -----
up:
	$(DC) up -d

up-prod:
	$(DC_PROD) up -d

down:
	$(DC) down

restart:
	$(DC) restart

build:
	$(DC) build

rebuild:
	$(DC) build --no-cache

logs:
	$(DC) logs -f --tail=200

ps:
	$(DC) ps

shell:
	$(APP) sh

# ----- Application -----
install:
	$(APP) composer install

key:
	$(APP) php artisan key:generate

migrate:
	$(APP) php artisan migrate

seed:
	$(APP) php artisan db:seed

fresh:
	$(APP) php artisan migrate:fresh --seed

cache-clear:
	$(APP) php artisan optimize:clear

artisan:
	$(APP) php artisan $(CMD)

tinker:
	$(APP) php artisan tinker

# ----- Quality -----
test:
	$(APP) php artisan test

test-concurrency:
	$(APP) php artisan test --group=concurrency

pint:
	$(APP) vendor/bin/pint

pint-test:
	$(APP) vendor/bin/pint --test

# ----- Docs -----
# Scramble renders OpenAPI documentation at request time — no generate step.
#   UI:   http://localhost/docs/api
#   Spec: http://localhost/docs/api.json
#
# `make docs-export` snapshots the spec to docs/openapi.json for committing
# alongside the codebase or attaching to a release.
docs:
	@echo "Scramble docs are served live at http://localhost/docs/api"
	@echo "OpenAPI spec available at  http://localhost/docs/api.json"

docs-export:
	@mkdir -p docs
	@curl -fsSL http://localhost/docs/api.json -o docs/openapi.json && \
		echo "Wrote docs/openapi.json ($$(wc -c < docs/openapi.json) bytes)"
