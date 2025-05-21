FROM php:8.1-apache

RUN a2enmod rewrite

COPY . /var/www/html/
RUN chmod -R 777 /var/www/html


EXPOSE 80
