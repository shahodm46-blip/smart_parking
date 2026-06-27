FROM php:7.4-apache
RUN a2dismod mpm_event && a2enmod mpm_prefork
COPY . /var/www/html/
EXPOSE 80
