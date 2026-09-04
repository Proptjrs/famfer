<?php

use Illuminate\Support\Facades\Schedule;

/*
 * La veille quotidienne.
 *
 * Ce fichier a longtemps été vide, avec un commentaire expliquant que « rien ne
 * se périme tout seul » avec le paiement à la livraison. C'était vrai tant que
 * le vendeur seul déclarait la livraison : il n'y avait qu'une version des
 * faits, et rien à arbitrer.
 *
 * Depuis que le client peut confirmer et contester, le temps devient une source
 * de vérité à part entière — la seule qui ne coûte rien et n'exige aucun tiers.
 * Une commande expédiée que personne ne clôt pose une question ; une commande
 * close depuis dix jours n'en pose plus.
 *
 * Trois heures du matin : personne ne commande, et les relances arrivent avec
 * le courrier du matin plutôt qu'au milieu de la nuit.
 */
Schedule::command('famfer:veiller')->dailyAt('03:00')->withoutOverlapping();
