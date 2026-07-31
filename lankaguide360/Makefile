# =====================================================================
# LankaGuide 360 — Makefile
# Convenience targets for local dev, Docker, DB seeding, scraping,
# Selenium automation, Kubernetes and Git. Works with GNU Make.
# On Windows, run inside Git Bash / WSL, or use `mingw32-make`.
# =====================================================================

# ---- Config (override on the command line, e.g. `make up PORT=9000`) ----
APP_NAME      ?= lankaguide360
PORT          ?= 8080
PHP_PORT      ?= 8000
DB_NAME       ?= lankaguide360
DB_USER       ?= root
DB_PASS       ?=
DB_HOST       ?= 127.0.0.1
DOCKER_COMPOSE ?= docker compose -f docker/docker-compose.yml
PY            ?= python
K8S_DIR       ?= k8s

.DEFAULT_GOAL := help

# ---------------------------------------------------------------------
.PHONY: help
help: ## Show this help
	@echo "LankaGuide 360 — available targets:"
	@grep -E '^[a-zA-Z0-9_.-]+:.*?## .*$$' $(MAKEFILE_LIST) | \
		awk 'BEGIN {FS = ":.*?## "}; {printf "  \033[36m%-18s\033[0m %s\n", $$1, $$2}'

# ---- Local (PHP built-in server) ------------------------------------
.PHONY: serve
serve: ## Run the app with PHP's built-in server (needs XAMPP PHP + MySQL running)
	php -S localhost:$(PHP_PORT)

# ---- Python environment ---------------------------------------------
.PHONY: install
install: ## Install Python tooling (scraper + automation)
	$(PY) -m pip install -r requirements.txt

.PHONY: venv
venv: ## Create a local virtualenv in .venv
	$(PY) -m venv .venv
	@echo "Activate with: .venv\\Scripts\\activate (Windows) or source .venv/bin/activate"

# ---- Database --------------------------------------------------------
.PHONY: db-create
db-create: ## Import the full schema + seed data into MySQL
	mysql -h $(DB_HOST) -u $(DB_USER) $(if $(DB_PASS),-p$(DB_PASS),) < database/lankaguide360.sql

.PHONY: db-reset
db-reset: ## Drop and re-import the database (DESTRUCTIVE)
	mysql -h $(DB_HOST) -u $(DB_USER) $(if $(DB_PASS),-p$(DB_PASS),) -e "DROP DATABASE IF EXISTS $(DB_NAME);"
	$(MAKE) db-create

# ---- Photo scraper ---------------------------------------------------
.PHONY: scrape
scrape: ## Fetch destination/resource photos (see scraper/README)
	$(PY) scraper/fetch_photos.py

.PHONY: scrape-dry
scrape-dry: ## Dry-run the scraper (list what would be downloaded)
	$(PY) scraper/fetch_photos.py --dry-run

# ---- Selenium automation (dispatcher + performers) -------------------
.PHONY: test
test: ## Run the Selenium dispatcher/performer end-to-end suite
	$(PY) automation/dispatcher.py

.PHONY: test-headless
test-headless: ## Run the E2E suite headless
	$(PY) automation/dispatcher.py --headless

# ---- Docker ----------------------------------------------------------
.PHONY: build
build: ## Build the Docker image
	$(DOCKER_COMPOSE) build

.PHONY: up
up: ## Start app + MySQL + phpMyAdmin via docker compose
	$(DOCKER_COMPOSE) up -d
	@echo "App:        http://localhost:$(PORT)"
	@echo "phpMyAdmin: http://localhost:8081"

.PHONY: down
down: ## Stop and remove containers
	$(DOCKER_COMPOSE) down

.PHONY: logs
logs: ## Tail container logs
	$(DOCKER_COMPOSE) logs -f

# ---- Kubernetes ------------------------------------------------------
.PHONY: k8s-apply
k8s-apply: ## Apply all Kubernetes manifests
	kubectl apply -f $(K8S_DIR)/

.PHONY: k8s-delete
k8s-delete: ## Delete all Kubernetes resources
	kubectl delete -f $(K8S_DIR)/

# ---- Git helpers -----------------------------------------------------
.PHONY: git-init
git-init: ## Initialise a git repo and make the first commit
	git init
	git add .
	git commit -m "chore: initial LankaGuide 360 platform commit"

.PHONY: git-push
git-push: ## Push to origin main (set REMOTE=<url> first time)
	@if [ -n "$(REMOTE)" ]; then git remote add origin $(REMOTE) || true; fi
	git add .
	git commit -m "$(if $(MSG),$(MSG),chore: update)" || true
	git branch -M main
	git push -u origin main
