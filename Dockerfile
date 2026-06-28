FROM php:8.1-apache

# تثبيت امتداد mysqli للتعامل مع الداتابيز
RUN docker-php-ext-install mysqli

# تعطيل mpm_event و mpm_worker وتفعيل mpm_prefork لضمان التوافق مع PHP
RUN a2dismod mpm_event mpm_worker && a2enmod mpm_prefork

# إعداد المنفذ (Port)
RUN echo "Listen 8080" > /etc/apache2/ports.conf
RUN sed -i 's/80/8080/g' /etc/apache2/sites-available/000-default.conf

# نسخ ملفات المشروع
COPY . /var/www/html/

# ضبط الصلاحيات (اختياري لكن مفضل)
RUN chown -R www-data:www-data /var/www/html

EXPOSE 8080

CMD ["apache2-foreground"]
