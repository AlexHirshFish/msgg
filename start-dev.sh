#!/bin/bash

# Скрипт для запуска мессенджера в режиме разработки

echo "🚀 Starting Messenger Development Server..."

# Проверяем наличие PHP
if ! command -v php &> /dev/null; then
    echo "❌ PHP is not installed or not in PATH"
    exit 1
fi

# Проверяем наличие Composer
if ! command -v composer &> /dev/null; then
    echo "❌ Composer is not installed"
    exit 1
fi

# Устанавливаем зависимости
echo "📦 Installing dependencies..."
composer install

# Копируем .env файл если он не существует
if [ ! -f ".env" ]; then
    echo "📋 Creating .env file..."
    cp .env.example .env
    echo "⚠️  Please configure your .env file with database credentials and other settings"
fi

# Создаем директории для хранения файлов
echo "📁 Creating storage directories..."
mkdir -p storage/voices
mkdir -p storage/attachments
chmod -R 755 storage

# Запускаем встроенный сервер PHP
echo "🌐 Starting PHP development server on http://localhost:8000"
echo "📝 Access the messenger at:"
echo "   Login: http://localhost:8000/public/login.html"
echo "   Messenger: http://localhost:8000/public/messenger.html"
echo "   API: http://localhost:8000/api/"
echo ""
echo "🔧 Press Ctrl+C to stop the server"
echo ""

php -S localhost:8000 -t .