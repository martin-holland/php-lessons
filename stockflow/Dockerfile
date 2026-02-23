FROM php:8.2-apache

# Install curl extension
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    && docker-php-ext-install curl \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite and mod_headers
RUN a2enmod rewrite headers env

# Set document root
ENV APACHE_DOCUMENT_ROOT /var/www/html

# Update Apache config
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Allow .htaccess overrides
RUN sed -ri -e 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Pass environment variables to PHP
RUN echo 'PassEnv SUPABASE_URL' >> /etc/apache2/conf-available/environment.conf && \
    echo 'PassEnv SUPABASE_ANON_KEY' >> /etc/apache2/conf-available/environment.conf && \
    a2enconf environment

WORKDIR /var/www/html
