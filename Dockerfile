FROM php:7.4-apache

# دي أهم خطوة: بنمسح ملفات الـ mpm القديمة اللي بتعمل تعارض
RUN rm -f /etc/apache2/mods-enabled/mpm_event.conf /etc/apache2/mods-enabled/mpm_event.load
RUN a2enmod mpm_prefork

# بننسخ ملفاتك
COPY . /var/www/html/

# بنظبط الصلاحيات
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
