FROM alpine:3.8

RUN apk add --update nginx
RUN rm -rf /var/cache/apk/* && rm -rf /tmp/*

RUN mv /etc/nginx/nginx.conf /etc/nginx/nginx.conf.orig
RUN mv /etc/nginx/conf.d/default.conf /etc/nginx/default.conf.orig
ADD /.docker/nginx/nginx.conf /etc/nginx/
ADD /.docker/nginx/vhost.conf /etc/nginx/conf.d/

RUN adduser -D -g '' -G www-data www-data

RUN chmod 777 -R /var/tmp/nginx/

EXPOSE 80
EXPOSE 443

CMD ["nginx"]
