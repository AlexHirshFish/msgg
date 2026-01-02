#!/bin/bash

# Установим переменные окружения
export PHP_VERSION=8.2

# Создаем директории хранения
echo "Creating storage directories..."
mkdir -p /tmp/storage/voices
mkdir -p /tmp/storage/attachments

# Инициализируем базу данных
echo "Initializing database..."
php database/init.php

# Запустим веб-сервер на указанном порту
echo "Starting server on port $PORT"
php -S 0.0.0.0:$PORT -t . -f index.php