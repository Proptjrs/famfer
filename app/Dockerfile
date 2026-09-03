# Image unique, construite en deux temps : on installe les dépendances dans un
# étage jetable, puis on ne recopie que le résultat. L'image finale ne contient
# ni Composer, ni les outils de compilation.
FROM composer:2 AS dependances
WORKDIR /app
COPY composer.json composer.lock ./
# « --no-scripts » : les scripts de Laravel ont besoin du code, qui n'est pas
# encore copié. Ils s'exécuteront à l'étage suivant.
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist
COPY . .
RUN composer dump-autoload --optimize --no-dev

FROM php:8.3-fpm-alpine

# pdo_pgsql pour la base, intl pour le formatage des montants et des dates,
# opcache parce qu'un serveur qui recompile le PHP à chaque requête gaspille.
# « gettext » fournit envsubst, qui pose le port dans la configuration nginx.
RUN apk add --no-cache postgresql-dev icu-dev nginx supervisor gettext \
        libpng-dev libjpeg-turbo-dev freetype-dev \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install pdo_pgsql intl opcache gd \
    && rm -rf /var/cache/apk/*

WORKDIR /var/www

COPY --from=dependances /app /var/www
COPY docker/nginx.conf.modele /etc/nginx/nginx.conf.modele
COPY docker/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

# « tr -d » avant « chmod » : un script écrit sous Windows porte des fins de
# ligne CRLF ; le shebang se termine alors par un retour chariot, et le noyau
# répond « no such file or directory » — en désignant l'interpréteur, pas le
# script, ce qui envoie chercher l'erreur au mauvais endroit.
RUN tr -d '\r' < /usr/local/bin/entrypoint.sh > /tmp/entrypoint \
    && mv /tmp/entrypoint /usr/local/bin/entrypoint.sh \
    && chmod +x /usr/local/bin/entrypoint.sh \
    && chown -R www-data:www-data storage bootstrap/cache

# Valeur de repli : l'hébergeur impose la sienne par PORT.
ENV PORT=8080
EXPOSE 8080
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
