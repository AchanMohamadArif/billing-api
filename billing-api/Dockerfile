# Use official PHP runtime with Apache
FROM php:8.2-apache

# Copy all files from repo root into Apache web root
COPY . /var/www/html/

# Enable mysqli extension
RUN docker-php-ext-install mysqli

# Expose port 80
EXPOSE 80
