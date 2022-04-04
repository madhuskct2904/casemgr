#!/bin/bash
# Copy EB environment variables into `.env` for use by Symfony
grep = /opt/elasticbeanstalk/deployment/env > .env
chown webapp:webapp .env && chmod 644 .env
