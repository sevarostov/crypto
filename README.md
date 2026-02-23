## User's crypto balance accounting module, built with Laravel and Mysql using Docker

## Technical Requirements 

[PHP 8.4](https://www.php.net/releases/8.4/en.php)
[Composer (System Requirements)](https://getcomposer.org/doc/00-intro.md#system-requirements)
[Laravel 12.11.2](https://laravel.com/docs/12.x)
[MySQL 9.1.0](https://hub.docker.com/r/mysql/mysql-server#!)
[Testing: PHPUnit](https://docs.phpunit.de/)
[Containerization: Docker 24.* + Docker Compose 2.*](https://www.docker.com)

## Installation

git clone https://github.com/sevarostov/crypto.git

#### Copy file `.env.example` to `.env`
```
cp .env.example .env
```

#### Make Composer install the project's dependencies into vendor/

```
composer install
```

## Generate key
```
php artisan key:generate
```

## Build the project

```
docker build -t php:latest --file ./docker/php/Dockerfile --target php ./docker
```

## Docker compose:
```
docker compose up -d
docker compose down
```

## Create database schema

```
docker exec -i php php artisan migrate
```

## Seed fixures data
````
docker exec php php artisan db:seed
````

## Seed fixures data
````
docker exec php php artisan balance:accrue 2 25.5000000000000000 bonus --description=test --reference_id=12345_id
docker exec php php artisan balance:write-off 1 1.0000000000000000 withdrawal --description="ATM withdrawal" --reference_id="TXN-54321"
docker exec php php artisan balance:transfer 1 4 1.0000000000000000 --description="Monthly transfer"
````



## Run tests

```
docker exec -i php vendor/bin/phpunit
```

