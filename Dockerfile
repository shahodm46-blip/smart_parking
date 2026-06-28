FROM php:8.1-apache

RUN docker-php-ext-install mysqli

COPY . /var/www/html/

RUN echo "Listen 8080" > /etc/apache2/ports.conf && \
    echo '<VirtualHost *:8080>\n\tDocumentRoot /var/www/html\n</VirtualHost>' > /etc/apache2/sites-enabled/000-default.conf && \
    a2dismod mpm_event || true && \
    a2enmod mpm_prefork || true

EXPOSE 8080

CMD ["apache2-foreground"]
