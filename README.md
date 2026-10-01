# Команды проекта

Все команды выполняются из корня проекта.

## Установка зависимостей

При первом подключении пакетов:

```bash
composer require vlucas/phpdotenv robmorgan/phinx
```

Если зависимости уже указаны в composer.json:

```bash
composer install
```

Укажите параметры существующей базы MySQL в `.env`. Конфиг Phinx расположен в `kernel/Database/phinx.php`.

## Миграции

Выполнить все новые миграции:

```bash
vendor/bin/phinx migrate -c kernel/Database/phinx.php -e development
```

Проверить статус миграций:

```bash
vendor/bin/phinx status -c kernel/Database/phinx.php -e development
```

Создать новую миграцию (замените имя своим):

```bash
vendor/bin/phinx create AddStatusToApplications -c kernel/Database/phinx.php
```

После создания заполните файл в `database/migrations/`, затем выполните команду `migrate`.

Текущая миграция создания таблицы заявок уже создана:

```text
database/migrations/20261001084520_create_applications_table.php
```

Повторно создавать `CreateApplicationsTable` не нужно. Если таблица `applications` уже создана вручную, миграция её создания завершится ошибкой; сначала согласуйте состояние базы и миграций без удаления нужных данных.

## Автозагрузка

После изменения раздела autoload в composer.json:

```bash
composer dump-autoload
```

## Локальный запуск

```bash
php -S localhost:8000 -t public
```

В другом терминале создайте тестовую заявку:

```bash
curl -i http://localhost:8000/ \
    --data-urlencode "name=Виктор" \
    --data-urlencode "email=viktor@example.com"
```

Ожидаемый результат: HTTP 201 и JSON с ID новой заявки. Каждый успешный POST создаёт отдельную запись.
