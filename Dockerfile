FROM php:8.2-cli

# Установка зависимостей
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    libicu-dev \
    && docker-php-ext-configure intl \
    && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd intl zip \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Установка Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Установка рабочей директории
WORKDIR /app

# Копирование composer.json и установка зависимостей
COPY composer.json ./
RUN composer install --no-dev --optimize-autoloader --ignore-platform-reqs

# Копирование всех файлов
COPY . .

# Установка прав доступа
RUN chmod +x start.sh

# Проверка наличия необходимых файлов
RUN ls -la

# Команда запуска
CMD ["sh", "-c", "php -S 0.0.0.0:$PORT -t ."]