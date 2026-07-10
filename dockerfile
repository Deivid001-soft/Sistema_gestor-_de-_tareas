FROM php:8.2-apache
# Instalar la extensión PDO MySQL que requiere tu clase Database
RUN docker-php-ext-install pdo pdo_mysql
# Copiar todos los archivos del proyecto al contenedor
COPY . /var/www/html/
# Exponer el puerto estándar
EXPOSE 80
