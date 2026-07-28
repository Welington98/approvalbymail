.RECIPEPREFIX := >
GLPI_ROOT ?= /var/www/verdanadesk/glpi
PLUGIN_KEY = approvalbymail
GLPI_CLI_USER ?= glpi

.PHONY: build deploy release install activate clean logs help dc-up dc-down dc-build dc-shell dc-logs

help:
> @echo "Alvos: build | deploy | release | install | activate | clean | logs"
> @echo "Docker: dc-up | dc-down | dc-build | dc-shell | dc-logs"

build:
> @bash deploy/build.sh

deploy: build
> @bash deploy/deploy.sh

release: deploy
> @echo ">> Lembrete: atualizar CHANGELOG e criar a git tag desta versao."

install:
> @sudo -u www-data php $(GLPI_ROOT)/bin/console plugin:install -u $(GLPI_CLI_USER) $(PLUGIN_KEY)

activate:
> @sudo -u www-data php $(GLPI_ROOT)/bin/console plugin:activate $(PLUGIN_KEY)

clean:
> @rm -rf staging

logs:
> @sudo tail -n 80 -f $(GLPI_ROOT)/files/_log/php-errors.log

## Docker targets
dc-up:
> @cp -n .env.example .env 2>/dev/null || true
> @docker compose up -d --build

dc-down:
> @docker compose down

dc-build:
> @docker compose build

dc-shell:
> @docker compose exec glpi bash

dc-logs:
> @docker compose logs -f

dc-rebuild:
> @docker compose down -v
> @docker compose up -d --build
