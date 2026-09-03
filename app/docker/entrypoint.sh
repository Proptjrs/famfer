#!/bin/sh
set -e

# La clé de chiffrement d'abord, et sans elle on ne démarre pas.
#
# Laravel en fabriquerait une au vol si on le laissait faire. Sur un service qui
# redémarre, une clé neuve à chaque démarrage rendrait illisible tout ce que la
# précédente avait chiffré — et ici, ce sont les données des vendeurs et des
# acheteurs. Mieux vaut un conteneur qui refuse de partir qu'un conteneur qui
# perd silencieusement les données de la veille.
if [ -z "${APP_KEY}" ]; then
    echo "ERREUR : APP_KEY n'est pas défini." >&2
    echo "  Générez-la une fois — php artisan key:generate --show — puis posez-la" >&2
    echo "  dans la configuration du service. Jamais dans le dépôt." >&2
    exit 1
fi

# Le port d'écoute vient de l'hébergeur. On substitue la seule variable qui nous
# intéresse : « envsubst » sans liste remplacerait aussi les « $uri » et
# « $document_root » de nginx, et le serveur ne démarrerait plus.
envsubst '${PORT}' < /etc/nginx/nginx.conf.modele > /etc/nginx/http.d/default.conf
echo "nginx écoute sur le port ${PORT}."

# Les migrations passent au démarrage : le conteneur qui se lance porte toujours
# le schéma que son code attend. « --force » parce qu'on n'est pas devant un
# terminal qui peut confirmer.
php artisan migrate --force

# Le catalogue est le socle commun : sans lui, aucun vendeur ne peut publier une
# offre, puisqu'une offre porte sur un article du référentiel.
#
# Ce seeder s'appelait ici « ReferentielSeeder », qui n'existe pas — l'échec
# était avalé par le « || echo » et le service démarrait sur une base vide, sans
# un seul article. Le nom est maintenant celui du fichier, et l'échec arrête le
# démarrage : mieux vaut un conteneur qui refuse de partir qu'une place de
# marché en ligne sans marchandise.
php artisan db:seed --class=CatalogueSeeder --force

# Les clients de démonstration et leurs commandes livrées ne s'allument que si
# on le demande. En production la variable reste à « false » : les vrais
# clients s'inscrivent eux-mêmes.
if [ "${DONNEES_DEMO}" = "true" ]; then
    echo "DONNEES_DEMO=true — semis des clients et des commandes de démonstration."
    php artisan db:seed --class=ClientsSeeder --force
fi

# Ces caches se reconstruisent à chaque déploiement, jamais à la main : un cache
# figé sur l'ancienne configuration est une panne difficile à voir.
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Rendre la main à www-data avant de démarrer les serveurs.
#
# Le Dockerfile a bien donné « storage » à www-data, mais à la construction.
# Tout ce que ce script vient d'écrire — le journal, les caches de
# configuration et de vues — l'a été par root, qui exécute l'entrypoint. PHP-FPM
# tourne ensuite sous www-data et ne peut plus rien y ajouter : la première
# ligne de journal déclenche une erreur 500, et l'erreur qui tente de se
# journaliser en déclenche une autre.
chown -R www-data:www-data storage bootstrap/cache

exec supervisord -c /etc/supervisord.conf
