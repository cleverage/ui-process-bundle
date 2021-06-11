DOCKER_RUN_PHP = docker-compose run --rm php "bash" "-c"

env:
	cat .env.dist | sed  -e "s/{UID}/$(shell id --user)/" -e "s/{GID}/$(shell id --group)/" | tee .env

dependecies:
	$(DOCKER_RUN_PHP) "composer install --no-interaction"
	$(DOCKER_RUN_PHP) "composer sync-recipes --force"
	docker-compose run --rm -u $$(id -u $${USER}):$$(id -g $${USER}) node "bash" "-c" "yarn install"
	docker-compose run --rm -u $$(id -u $${USER}):$$(id -g $${USER}) node "bash" "-c" "yarn run webpack --watch"

upd: #[Docker] Start containers
	docker-compose up --detach

stop: #[Docker] Down containers
	docker-compose stop

down: #[Docker] Down containers
	docker-compose down

build: #[Docker] Build containers
	docker-compose build

php: #[Docker] Connect to php container with current host user
	docker-compose run --rm php bash

node: #[Docker] Connect to node container
	docker-compose run --rm -u $$(id -u $${USER}):$$(id -g $${USER}) node bash

yarn-watch: #[Docker] Connect to yarn container
	docker-compose run --rm -u $$(id -u $${USER}):$$(id -g $${USER}) node "bash" "-c" "yarn run webpack --watch"

logs: #[Docker] Show logs
	docker-compose logs -f

cache-clean: #[Symfony] Clean cache
	$(DOCKER_RUN_PHP) "bin/console c:c"