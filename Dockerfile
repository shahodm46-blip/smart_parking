FROM php:8.1-apache

# تثبيت امتداد mysqli
RUN docker-php-ext-install mysqli

# 1. إزالة أي تهيئة سابقة للـ MPM لضمان البدء من الصفر
RUN rm -f /etc/apache2/mods-enabled/mpm_*.conf /etc/apache2/mods-enabled/mpm_*.load

# 2. تفعيل mpm_prefork فقط
RUN a2enmod mpm_prefork

# 3. تعديل المنفذ ليكون 8080 في ملفات الإعداد
RUN sed -i 's/80/8080/g' /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf

# نسخ ملفات المشروع
COPY . /var/www/html/

# ضبط الصلاحيات
RUN chown -R www-data:www-data /var/www/html

EXPOSE 8080

CMD ["apache2-foreground"]
