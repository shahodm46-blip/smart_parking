FROM php:8.1-apache

# تثبيت الحزم اللازمة وتعطيل الـ MPM الافتراضي
RUN apt-get update && apt-get install -y apache2

RUN a2dismod mpm_event mpm_worker || true
RUN a2enmod mpm_prefork

# تثبيت mysqli
RUN docker-php-ext-install mysqli

# إعداد المنفذ
RUN sed -i 's/80/8080/g' /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf

# نسخ الملفات
COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

EXPOSE 8080
CMD ["apache2-foreground"]
