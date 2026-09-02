<?php

use Illuminate\Support\Facades\Schedule;

/*
 * Les trois délais du cycle de commande.
 *
 * La commande « famfer:delais » existait, mais rien ne l'appelait : le cron de
 * production lançait « schedule:run » sur un planificateur vide. Les délais ne
 * se seraient donc jamais déclenchés en ligne — un panier abandonné aurait
 * bloqué le stock d'un vendeur indéfiniment, et un acheteur distrait aurait
 * retenu l'argent d'un vendeur pour toujours.
 *
 * Toutes les cinq minutes : c'est assez fin pour un délai de paiement de quinze
 * minutes, et assez espacé pour ne pas peser sur une base gratuite.
 *
 * « withoutOverlapping » parce que deux balayages simultanés annuleraient deux
 * fois la même commande. La transition serait refusée la seconde fois — la
 * machine à états y veille — mais autant ne pas déclencher l'erreur.
 *
 * Pas de « runInBackground » : la tâche dure une seconde, et un sous-processus
 * détaché serait tué avec le conteneur sur un hébergeur qui arrête la tâche dès
 * que la commande de premier plan rend la main. C'est aussi pourquoi le cron de
 * « render.yaml » appelle « famfer:delais » directement plutôt que de passer
 * par « schedule:run » — cette déclaration-ci sert aux environnements qui font
 * tourner le planificateur de Laravel de la manière habituelle.
 */
Schedule::command('famfer:delais')
    ->everyFiveMinutes()
    ->withoutOverlapping();
