FROM php:7.4-fpm-alpine

# تثبيت خادم ويب خفيف جداً ومستقر
RUN apk add --no-cache apache2
RUN sed -i 's/Listen 80/Listen 8080/' /etc/apache2/httpd.conf

COPY . /var/www/localhost/htdocs/

EXPOSE 8080
CMD ["sh", "-c", "httpd && php-fpm"]
