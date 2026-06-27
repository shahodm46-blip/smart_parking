FROM php:7.4-apache

# تعطيل موديول الـ mpm_event وتفعيل mpm_prefork عشان ميتعارضوش
RUN a2dismod mpm_event && a2enmod mpm_prefork

# نسخ ملفات الموقع للمسار الصحيح
COPY . /var/www/html/

# ضبط الصلاحيات عشان Apache يقدر يقرأ الملفات
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
