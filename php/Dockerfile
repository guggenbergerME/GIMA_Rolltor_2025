FROM php:8.2-apache

# -----------------------------------------------------------
# 🧩 Systempakete und PHP-Extensions installieren
# -----------------------------------------------------------
RUN apt-get update && apt-get install -y \
    libicu-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install \
        pdo \
        pdo_mysql \
        mysqli \
        calendar \
        intl \
        sockets \
    && rm -rf /var/lib/apt/lists/*

# -----------------------------------------------------------
# 🕓 Zeitzone setzen
# -----------------------------------------------------------
ENV TZ=Europe/Berlin
RUN echo "Europe/Berlin" > /etc/timezone && dpkg-reconfigure -f noninteractive tzdata

# -----------------------------------------------------------
# 🔁 Apache Rewrite aktivieren
# -----------------------------------------------------------
RUN a2enmod rewrite

# -----------------------------------------------------------
# 📂 Standard-DocumentRoot bleibt /var/www/html
# -----------------------------------------------------------
WORKDIR /var/www/html
