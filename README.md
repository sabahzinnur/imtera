# Laravel Vue Starter Kit

Это современное приложение на базе Laravel 12, Vue.js 3 и Inertia.js.

## Основной стек технологий

- **Backend:** Laravel 12, PHP 8.4
- **Frontend:** Vue.js 3, Inertia.js, Vite, Tailwind CSS 4
- **Auth:** Laravel Fortify
- **Database:** PostgreSQL

---

## Быстрый старт (Разработка)

### Требования
- PHP 8.2+
- Node.js 20+
- PostgreSQL

### Установка
1. Клонируйте репозиторий.
2. Выполните команду автоматической настройки:
   ```bash
   composer run setup
   ```
   *Эта команда установит зависимости (Composer & NPM), создаст `.env`, сгенерирует ключ и выполнит миграции.*

### Запуск
Вы можете запустить среду разработки локально или через Docker.

#### Вариант 1: Локально
```bash
composer run dev
```
Приложение будет доступно по адресу [http://localhost:8000](http://localhost:8000).

#### Вариант 2: Через Docker (рекомендуется)
```bash
HOST_PORT=8000 docker compose up -d
```
*Контейнер сам установит зависимости при первом запуске.*

---

## Запуск в Production (Docker)

Проект готов к развертыванию с помощью Docker. Мы используем Nginx в качестве веб-сервера и PHP-FPM для приложения.

### Шаги для запуска:
1. Подготовьте файл настроек:
   ```bash
   cp .env.example .env
   ```
   *Обязательно настройте `DB_PASSWORD` и другие важные переменные.*

2. Соберите и запустите контейнеры:
   ```bash
   HOST_PORT=80 docker compose -f docker-compose.prod.yml up -d --build
   ```
   *Вы можете изменить `HOST_PORT`, если хотите запустить приложение на другом порту хоста.*

### Используемые образы:
- **app**: Сборка на основе `Dockerfile.prod` (PHP-FPM + оптимизированный автозагрузчик).
- **nginx**: Сборка на основе `docker/nginx/Dockerfile` (содержит скомпилированные ассеты фронтенда).
- **postgres**: Стандартный образ PostgreSQL 15.

---

## Тестирование и линтинг

Запуск всех тестов и проверка стиля кода:
```bash
composer test
```

Исправление стиля кода (Laravel Pint):
```bash
composer lint
```

Проверка фронтенда:
```bash
npm run lint
```
