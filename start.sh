#!/bin/bash

# Установим переменные окружения
export PHP_VERSION=8.2

# Инициализируем базу данных
echo "Initializing database..."
php database/init.php

# Запустим веб-сервер на указанном порту
echo "Starting server on port $PORT"
php -S 0.0.0.0:$PORT -t . -f index.php