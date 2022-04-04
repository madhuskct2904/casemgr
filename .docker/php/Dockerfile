FROM php:7.4-fpm

ARG CGID
ARG CUID
ARG CUNAME

RUN apt-get update \
    && apt-get install -y \
       libtool \
       libzip-dev \
       libjpeg-dev \
       libpng-dev \
       libxml2-dev \
       libonig-dev \
       libfontconfig1 \
       libxrender1 \
       bash \
       openssh-client \
       supervisor \
       git \
    && docker-php-ext-install \
        mbstring \
        pdo \
        pdo_mysql \
        tokenizer \
        xml \
        gd \
        zip \
    && curl -s https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin/ --filename=composer

EXPOSE 9000

# install php-cs-fixer
#RUN composer global require hirak/prestissimo friendsofphp/php-cs-fixer

# clear cache and temporary files
RUN rm -rf /var/cache/apt/* && rm -rf /tmp/*

# install php-unit
#RUN curl https://phar.phpunit.de/phpunit-8.phar -L > phpunit.phar \
#  && chmod +x phpunit.phar \
#  && mv phpunit.phar /usr/local/bin/phpunit \
#  && phpunit --version

# copy php-fpm config
ADD ./.docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

# copy php.ini for CLI

ADD ./.docker/php/php.ini /usr/local/etc/php/php.ini

# supervisord for dtc-queue
RUN groupadd -g ${CGID} -o ${CUNAME}
RUN useradd -m -u ${CUID} -g ${CGID} -o -s /bin/bash ${CUNAME}
ADD ./.docker/php/supervisord.conf /etc/supervisor/supervisord.conf
RUN touch /var/log/supervisor/supervisord.log
RUN touch /var/log/supervisor/dtc-queue.log
RUN touch /var/log/supervisor/php-fpm.log
RUN chmod 777 /var/log/supervisor/*

WORKDIR /var/www/app

RUN mkdir -p /root/.ssh
RUN echo "Host bitbucket.org\n\tStrictHostKeyChecking no\n" >> /root/.ssh/config
RUN eval `ssh-agent -s`

#CMD /usr/bin/supervisord -n
