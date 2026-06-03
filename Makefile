.RECIPEPREFIX := >
GLPI_ROOT ?= /var/www/verdanadesk/glpi
PLUGIN_KEY = approvalbymail
GLPI_CLI_USER ?= glpi

.PHONY: build deploy release install activate clean logs help

help:
> @echo "Alvos: build | deploy | release | install | activate | clean | logs"

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
