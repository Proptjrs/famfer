<?php

/*
 * Aucune tâche planifiée.
 *
 * Le modèle précédent en avait besoin : les commandes y expiraient faute de
 * paiement, et un panier abandonné bloquait le stock d'un vendeur. Avec le
 * paiement à la livraison, rien ne se périme tout seul — une commande attend
 * que le vendeur l'expédie, et le stock n'est retenu qu'une fois la commande
 * réellement passée.
 *
 * Ce fichier reste, vide, plutôt que d'être supprimé : « bootstrap/app.php »
 * le charge, et son absence ferait échouer le démarrage.
 */
