FROM php:8.1-apache

# تثبيت امتداد mysqli
RUN docker-php-ext-install mysqli

# 1. إزالة أي إعدادات MPM افتراضية تماماً
RUN rm -rf /etc/apache2/mods-enabled/mpm_*.load /etc/apache2/mods-enabled/mpm_*.conf

# 2. إنشاء ملف إعداد جديد لـ MPM Prefork فقط
RUN echo "LoadModule mpm_prefork_module /usr/lib/apache2/modules/mod_mpm_prefork.so" > /etc/apache2/mods-enabled/mpm_prefork.load && \
    echo "<IfModule mpm_prefork_module>\n\tStartServers 5\n\tMinSpareServers 5\n\tMaxSpareServers 10\n\tMaxRequestWorkers 150\n</IfModule>" > /etc/apache2/mods-enabled/mpm_prefork.conf

# 3. إعداد المنفذ 8080 في كل الملفات الضرورية
RUN sed -i 's/80/8080/g' /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf

# نسخ الملفات
COPY . /var/www/html/

# ضبط الصلاحيات
RUN chown -R www-data:www-data /var/www/html

EXPOSE 8080

CMD ["apache2-foreground"]
