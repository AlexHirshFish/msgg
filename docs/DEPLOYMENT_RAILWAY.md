# 🚀 Деплой мессенджера на Railway

Подробная инструкция по развертыванию мессенджера на платформе Railway.

## 📋 Предварительные требования

- Аккаунт на GitHub
- Аккаунт на Railway (бесплатный)
- База данных MySQL (можно использовать Railway или внешнюю)
- Доменное имя (опционально)

## 🔧 Шаг 1: Подготовка репозитория

### 1.1 Создание репозитория на GitHub

```bash
# Если вы еще не инициализировали git репозиторий
cd messenger
git init
git add .
git commit -m "Initial commit: Messenger application"

# Создайте репозиторий на GitHub и подключите его
git branch -M main
git remote add origin https://github.com/ваш_логин/messenger.git
git push -u origin main
```

### 1.2 Настройка .gitignore

Создайте файл `.gitignore` в корне проекта:

```gitignore
# Environment variables
.env
.env.local

# Dependencies
/vendor/
/node_modules/

# Logs
*.log
logs/

# Temporary files
tmp/
temp/

# IDE files
.vscode/
.idea/
*.swp
*.swo

# OS generated files
.DS_Store
Thumbs.db

# Storage files (в production эти файлы будут на внешнем хранилище)
/storage/
```

## ☁️ Шаг 2: Настройка Railway

### 2.1 Создание проекта

1. Перейдите на [railway.app](https://railway.app)
2. Авторизуйтесь через GitHub
3. Нажмите "New Project"
4. Выберите "Deploy from GitHub repo"
5. Выберите ваш репозиторий с мессенджером

### 2.2 Настройка переменных окружения

В Railway перейдите во вкладку "Variables" и добавьте следующие переменные:

```
APP_NAME=Messenger
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ваш_проект.up.railway.app

DB_CONNECTION=mysql
DB_HOST=containers-us-west-xxx.railway.app
DB_PORT=xxxx
DB_DATABASE=railway
DB_USERNAME=root
DB_PASSWORD=ваш_пароль

JWT_SECRET=ваш_очень_длинный_секретный_ключ
JWT_EXPIRES_IN=86400

TELEGRAM_BOT_TOKEN=ваш_токен_телеграм_бота
TELEGRAM_BOT_NAME=имя_вашего_бота

STORAGE_PATH=/app/storage
MAX_FILE_SIZE=10485760
ALLOWED_FILE_TYPES=jpg,jpeg,png,gif,mp3,wav,pdf,doc,docx,txt
```

### 2.3 Создание базы данных

#### Вариант A: Использование Railway Database

1. В вашем Railway проекте нажмите "Add Service"
2. Выберите "Database" → "MySQL"
3. Railway автоматически создаст базу данных
4. Скопируйте параметры подключения во вкладке "Connect"
5. Обновите переменные окружения в основном сервисе

#### Вариант B: Внешняя база данных

Используйте любой облачный провайдер (AWS RDS, DigitalOcean, etc.)

## 🛠 Шаг 3: Настройка деплоя

### 3.1 Файл railway.toml

Убедитесь, что файл `railway.toml` находится в корне проекта:

```toml
[build]
builder = "nixpacks"

[deploy]
startCommand = "php -S 0.0.0.0:$PORT -t ."

[variables]
PHP_VERSION = "8.1"
```

### 3.2 Nixpacks конфигурация

Создайте файл `nixpacks.toml` для точной настройки сборки:

```toml
[phases.setup]
nixPkgs = ["php81", "php81Extensions.pdo", "php81Extensions.pdo_mysql", "php81Extensions.mbstring"]

[phases.install]
cmds = ["composer install --no-dev --optimize-autoloader"]

[start]
cmd = "php -S 0.0.0.0:${PORT:-8000} -t ."
```

## 📁 Шаг 4: Настройка хранилища файлов

Для production среды рекомендуется использовать облачное хранилище:

### 4.1 AWS S3 (рекомендуется)

1. Создайте bucket в AWS S3
2. Настройте IAM пользователя с доступом к bucket
3. Установите AWS SDK:

```bash
composer require aws/aws-sdk-php
```

4. Создайте сервис для работы с S3 (`includes/Services/S3Service.php`):

```php
<?php

namespace App\Services;

use Aws\S3\S3Client;

class S3Service
{
    private static $client;
    
    public static function getClient()
    {
        if (!self::$client) {
            self::$client = new S3Client([
                'version' => 'latest',
                'region' => env('AWS_REGION'),
                'credentials' => [
                    'key' => env('AWS_ACCESS_KEY_ID'),
                    'secret' => env('AWS_SECRET_ACCESS_KEY'),
                ],
            ]);
        }
        
        return self::$client;
    }
    
    public static function uploadFile($filePath, $key, $bucket = null)
    {
        $bucket = $bucket ?: env('AWS_S3_BUCKET');
        
        return self::getClient()->putObject([
            'Bucket' => $bucket,
            'Key' => $key,
            'Body' => fopen($filePath, 'r'),
            'ACL' => 'public-read'
        ]);
    }
}
```

5. Обновите `.env`:

```env
AWS_ACCESS_KEY_ID=ваш_ключ
AWS_SECRET_ACCESS_KEY=ваш_секрет
AWS_REGION=us-east-1
AWS_S3_BUCKET=ваш_bucket
```

### 4.2 Railway Volumes (альтернатива)

Railway поддерживает volumes для постоянного хранения:

1. В Railway проекте добавьте Volume
2. Смонтируйте volume в `/app/storage`
3. Обновите путь в конфигурации

## 🔐 Шаг 5: Настройка безопасности

### 5.1 HTTPS

Railway автоматически предоставляет SSL сертификат.

### 5.2 CORS настройка

Обновите заголовки в API файлах:

```php
// Разрешаем только ваш домен
header('Access-Control-Allow-Origin: https://ваш_домен.com');
```

### 5.3 Rate limiting

Добавьте ограничение на количество запросов:

```php
// includes/Middleware/RateLimitMiddleware.php
class RateLimitMiddleware
{
    public static function check($identifier, $maxRequests = 100, $window = 3600)
    {
        // Реализация rate limiting
        // Можно использовать Redis или базу данных
    }
}
```

## 🔄 Шаг 6: CI/CD настройка

### 6.1 GitHub Actions для тестирования

Создайте `.github/workflows/test.yml`:

```yaml
name: Test

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest
    
    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: test_messenger
        ports:
          - 3306:3306
        options: --health-cmd="mysqladmin ping" --health-interval=10s --health-timeout=5s --health-retries=3
    
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
          extensions: mbstring, pdo, pdo_mysql
          
      - name: Install dependencies
        run: composer install
        
      - name: Run tests
        run: |
          # Здесь будут ваши тесты
          echo "Running tests..."
```

### 6.2 Автоматический деплой

Railway автоматически деплоит изменения из main ветки.

## 📊 Мониторинг и логирование

### 7.1 Логирование

Настройте централизованное логирование:

```php
// includes/Services/LoggerService.php
class LoggerService
{
    public static function log($level, $message, $context = [])
    {
        error_log("[$level] $message " . json_encode($context));
        
        // Отправка в внешний сервис (например, Sentry)
        if (env('SENTRY_DSN')) {
            // Интеграция с Sentry
        }
    }
}
```

### 7.2 Метрики

Интеграция с сервисами мониторинга:

```env
SENTRY_DSN=ваш_sentry_dsn
NEW_RELIC_LICENSE_KEY=ваш_ключ
```

## 🆘 Troubleshooting

### Распространенные проблемы:

1. **Ошибки базы данных**: Проверьте параметры подключения
2. **Проблемы с файлами**: Убедитесь, что директории storage доступны для записи
3. **Ошибки WebSocket**: Проверьте порты и CORS настройки
4. **Проблемы с производительностью**: Настройте кэширование и оптимизируйте запросы

### Полезные команды Railway:

```bash
# Установка Railway CLI
npm install -g @railway/cli

# Логин
railway login

# Просмотр логов
railway logs

# Переменные окружения
railway variables

# Рестарт приложения
railway restart
```

## 💰 Цены и масштабирование

### Бесплатный тариф Railway:
- 500 часов в месяц
- 1GB RAM
- 1GB дискового пространства
- Одновременно 1 сервис

### Рекомендации по масштабированию:
1. Используйте CDN для статических файлов
2. Настройте Redis для кэширования
3. Разделите API и WebSocket на отдельные сервисы
4. Используйте load balancer для горизонтального масштабирования

## 🎉 Готово!

После выполнения всех шагов ваш мессенджер будет доступен по адресу:
`https://ваш_проект.up.railway.app`

Не забудьте:
- Настроить доменное имя (опционально)
- Настроить мониторинг
- Настроить резервное копирование базы данных
- Провести нагрузочное тестирование