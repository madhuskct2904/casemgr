CaseMGR-api
===========

API/backend for Case Manager application.

### Tech stack, dependencies

* Symfony 3, PHP 7.4
* MySQL 5.7

#### Required PHP Extensions
* Ctype
* iconv
* JSON
* PCRE
* Session
* SimpleXML
* Tokenizer
* mbstring
* PDO
* pdo_mysql
* gd
* zip

Additional info: this project needs two mysql databases, see `app/config/parameteres.yml.dist`

### 3rdParty integrations

* Twilio for SMS messaging
* Sentry for errors logging
* AWS S3 - files storage

See example configuration: `app/config/parameteres.yml.dist`

### Extra directories

* .docker - docker configuration for quick development environment setup
* .ebextensions - production and dev settings for AWS ElasticBeanstalk 

## Running production version

This application is hosted on AWS, using ElasticBeanstalk for autoscaling and deploying application.

However, it can be hosted on standard HTTP + PHP server.

1. Make sure that your server environment meets all requirements mentioned in `Tech stack` section
2. Clone git repository
3. Setup http server and make sure that all request are handled by `app.php`. See example configuration in `.docker/php/www.conf` 
4. Setup environment variables (eg. by settings `.env` file, all required values can be found on `app/config/parameters.yml.dist`) 
5. Setup two databases, one for application, second for reports cache
6. Install composer dependencies: 
   ```$ composer install```
7. Import database dump from `.database/casemgr-dev.sql` into application database. Leave reports cache database empty.
8. Run 
   ```$ php bin/console d:m:migrate``` to migrate database schema
9. Setup periodic run of dtc:queue
   ```$ php bin/console dtc:queue:run -m 100 --disable-gc``` 
   Example config can be found in `.docker/php/supervisord.conf`
10. Setup cron for periodic delete of old files and dtc queue entries:

```
* * * * * root php bin/console app:delete-old-activity-feed-entries
* * * * * root php bin/console app:delete-old-shared-files
* 1 * * * root php bin/console dtc:queue:prune old --older=1d

```
11. Try to login from the frontend. Default access: `p.szklarski@asper.net.pl` : `Asper!2345` 

## CI/CD

CI/CD is managed by bitbucket pipelines (`bitbucket-pipelines.yml` file) and AWS ElasticBeanstalk (look at `.ebextensions` directory).

## Deployment procedure for production

Merge changes to `master` branch. That's all.

## Deployment procedure for testing (dev) server

Merge changes to `develop` branch. That's all.


## Running development environment

Preferred way is to use Docker.  This will run this project on `http://api.casemgr.local:8080` by default.  The frontend is configured to run on `http://casemgr.local` when running in dev mode.

## Quick development start guide

1. Clone repository
2. Start docker

To prevent potential permissions issues start using ```.docker/start.sh``` script or use following command:

```
$ CUID=$(id -u) CGID=$(id -g) CUNAME=$(id -un) docker-compose up
```

If you use default docker-compose up command (without CURRENT_UID variable) you may have permissions issues for cache and logs directories.

Docker will create all containers and mysql database (if not exists).

3. Create `.env` file

`.env` is the config file for the Symfony Framework. You can just copy it from `.env.example` in the project root.

4. Install project PHP packages

Login into casemgr-php container:

```
$ docker exec -it casemgr-php /bin/bash
```

[inside container]

```
# composer install
```

5. Import database from dump form `.docker/dump.sql`. To do this, you can use included script:

```
$ .docker/import-mysql-dump.sh
```

Optional:

[before performing actions below you need VPN connection]

VPN connection is required for access to sensitive data and possibility to connect by standard SSH connection was blocked.

Prepare VPN connection
- ask about your personal VPN credentials person responsible for devops cases
- put your credentials in secure storage such as Bitwarden
- download and install 'Open VPN Connect'
- enter your credentials and try to connect
- done

Fresh data-dump you can download from our development server (host and credentials to you can find on Bitwarden CaseMGR group).
Once you found credentials you can prepare your dump (the latest version was from 2021-07-01) and import it by command described above.

```
$ mysqldump --column-statistics=0 --ssl-mode=disabled --no-tablespaces -h <dev-host> -u CMApp -p <dev-database> > dump.sql
```

6. PhpMyAdmin is available at http://localhost:81

Optional:

If you want to use bitbucket from inside of php container - add your ssh keys to ```.docker/keys/```

## Docker notes

Docker configuration (docker-compose.yml) contains 5 containers:

- mysql - mysql 5.7 database runs on port 3306 - based on official mysql docker image,
- nginx - nginx web server runs on port 80 - based on Linux-Alpha 3.8 image
- php - php-cli and php-fpm (rund on port 9000) - based on php:7.2-fpm-alpine image (caution: 7.2 is the newest version which works with CasemMgr API, php 7.3 WILL NOT work), contains also some tools for php development
- phpmyadmin - based on official phpmyadmin image, sometimes can be helpful

All docker-related files are stored in .docker directory:

- .docker/logs - logs from containers
- .docker/keys - keys for bitbucket
- .docker/mysql-data - mysql-data volume for persis mysql database
- .docker/nginx - Dockerfile and config files for nginx container
- .docker/php - Dockerfile and config file for php container
- .docker/import-mysql-dump.sh - Script to import dump.sql into mysql casemgr database


#### Readme author: p.szklarski@asper.net.pl. Tested on Linux Mint 20 and Windows 10 WSL2 + Ubuntu 20.04.

## Makefile commands

- `make start` - start environment
- `make stop` - stop environment
- `make migrate` - run migrations
- `make bash` - run bash console
# casemgr
