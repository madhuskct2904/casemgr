#!/bin/sh

mkdir -p logs/nginx
mkdir -p logs/php-fpm
mkdir -p logs/symfony
mkdir -p mysql-data

chmod o+rw logs/* -R

CUID=$(id -u) CGID=$(id -g) CUNAME=$(id -un) docker-compose up

