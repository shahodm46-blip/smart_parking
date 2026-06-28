RUN apt-get update && apt-get install -y apache2
RUN a2dismod mpm_event mpm_worker || true
RUN a2enmod mpm_prefork
