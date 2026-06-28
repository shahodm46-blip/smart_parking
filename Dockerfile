FROM php:7.4-apache

# تعطيل كل إعدادات الـ mpm المسبقة عشان نضمن مفيش تعارض
RUN a2dismod mpm_event mpm_worker mpm_prefork && \
    apt-get update && apt-get install -y apache2 && \
    a2enmod mpm_prefork

# نسخ ملفات الموقع
COPY . /var/www/html/

# ضبط الصلاحيات
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
