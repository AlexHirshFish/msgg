#!/bin/bash

# Скрипт для запуска WebSocket сервера

echo "🚀 Starting WebSocket Server..."

# Проверяем наличие PHP
if ! command -v php &> /dev/null; then
    echo "❌ PHP is not installed"
    exit 1
fi

# Проверяем наличие Composer зависимостей
if [ ! -d "vendor" ]; then
    echo "📦 Installing dependencies..."
    composer install
fi

# Запускаем WebSocket сервер
echo "🌐 WebSocket server starting on ws://localhost:8080"
echo "🔧 Press Ctrl+C to stop the server"
echo ""

php websocket/server.php