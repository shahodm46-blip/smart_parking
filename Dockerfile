FROM php:8.1-apache

RUN docker-php-ext-install mysqli

COPY . /var/www/html/

ENV PORT=8080
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

EXPOSE 8080

CMD ["apache2-foreground"]
