FROM php:8.1-apache

RUN docker-php-ext-install mysqli

RUN a2dismod mpm_event && a2enmod mpm_prefork

COPY . /var/www/html/

RUN sed -i 's/80/8080/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

EXPOSE 8080

CMD ["apache2-foreground"]
