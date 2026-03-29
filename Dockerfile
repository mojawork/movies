# Dockerfile for a PHP server with Apache
# Author: [Your Name]
# Date: $(date +%F)

FROM php:7.4-apache

# Install MySQL extensions
RUN docker-php-ext-install pdo pdo_mysql

# Working directory in the container
copy src/ /var/www/html