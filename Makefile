UID=$(shell id -u)
GID=$(shell id -g)
DOCKER_COMPOSE=docker compose
DOCKER_PHP_SERVICE=php

.PHONY: start erase build cache-folders composer-install composer-update bash logs

start: erase cache-folders build composer-install bash

erase:
	$(DOCKER_COMPOSE) down -v

build:
	$(DOCKER_COMPOSE) build --pull

cache-folders:
	mkdir -p ~/.composer && chown ${UID}:${GID} ~/.composer

composer-install:
	$(DOCKER_COMPOSE) run --rm -u ${UID}:${GID} $(DOCKER_PHP_SERVICE) composer install

composer-update:
	$(DOCKER_COMPOSE) run --rm -u ${UID}:${GID} $(DOCKER_PHP_SERVICE) composer update

bash:
	$(DOCKER_COMPOSE) run --rm -u ${UID}:${GID} $(DOCKER_PHP_SERVICE) sh

logs:
	$(DOCKER_COMPOSE) logs -f $(DOCKER_PHP_SERVICE)
