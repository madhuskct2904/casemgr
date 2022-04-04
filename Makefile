CUID=$(shell id -u)
CGID=$(shell id -g)
CUNAME=$(shell id -un)

start:
	CUID=${CUID} CGID=${CGID} CUNAME=${CUNAME} docker-compose up -d

start-dev:
	CUID=${CUID} CGID=${CGID} CUNAME=${CUNAME} docker-compose -f docker-compose.dev.yml up -d

stop:
	CUID=${CUID} CGID=${CGID} CUNAME=${CUNAME} docker-compose down

build:
	CUID=${CUID} CGID=${CGID} CUNAME=${CUNAME} docker-compose build

migrate:
	docker exec -it casemgr-php php bin/console d:m:m

bash:
	docker exec -it casemgr-php bash

doctrine-migration-generate:
	docker exec -it casemgr-php php bin/console doctrine:migrations:generate

doctrine-migration-status:
	docker exec -it casemgr-php php bin/console doctrine:migrations:status

doctrine-migration-migrate:
	docker exec -it casemgr-php php bin/console doctrine:migrations:migrate
