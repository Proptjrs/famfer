<?php

return [

    /*
     * L'opérateur par défaut : Wave, Orange Money, ou un agrégateur.
     *
     * Le nom est enregistré sur chaque paiement. Il ne sert pas qu'à
     * l'affichage : la réconciliation compare notre journal au relevé d'un
     * opérateur précis, et deux opérateurs ne numérotent pas leurs transactions
     * de la même façon.
     */
    'operateur' => env('PAIEMENT_OPERATEUR', 'wave'),

    /*
     * La clé d'appel de l'interface de l'opérateur, pour lancer un paiement.
     * Jamais dans le dépôt : elle est posée dans la configuration du service.
     */
    'cle_api' => env('PAIEMENT_CLE_API'),

    /*
     * Le secret partagé qui signe les rappels.
     *
     * C'est la seule chose qui distingue un rappel de l'opérateur d'une requête
     * fabriquée par n'importe qui. L'adresse du rappel est publique — elle doit
     * l'être, l'opérateur n'ouvre pas de session — donc sans cette signature,
     * un inconnu pourrait déclarer payées toutes les commandes de la place de
     * marché et faire virer l'argent du séquestre à des vendeurs complices.
     *
     * Vide, les rappels sont refusés. On ne dégrade pas en « pas de
     * vérification » : une porte sans serrure vaut mieux fermée.
     */
    'secret_rappel' => env('PAIEMENT_SECRET_RAPPEL'),

    /*
     * L'âge maximal d'un rappel, en secondes.
     *
     * La signature couvre un horodatage. Passé ce délai, un rappel rejoué —
     * capturé sur le réseau puis renvoyé plus tard — est écarté avant même
     * d'atteindre la logique métier. L'idempotence protège déjà l'argent ; ceci
     * réduit la fenêtre pendant laquelle un enregistrement volé reste utile.
     */
    'tolerance_horodatage' => (int) env('PAIEMENT_TOLERANCE_S', 300),

];
