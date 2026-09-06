<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    /**
     * Le disque public est isolé pour chaque essai.
     *
     * Sans cela, les essais écrivent dans le vrai « storage/app/public » : la
     * configuration de « phpunit.xml » redirige la base de données et la
     * messagerie, mais pas le système de fichiers, et personne ne s'en aperçoit
     * tant qu'aucun semis ne pose d'image.
     *
     * Le jour où le semeur s'est mis à poser cent vingt-deux photos, chaque
     * classe d'essai en a déposé autant sur le disque de développement — plus de
     * quarante mille fichiers orphelins et quatre cents mégaoctets en une heure,
     * sans la moindre erreur puisque tout fonctionnait comme demandé.
     *
     * « Storage::fake » range chaque essai dans son propre dossier temporaire et
     * le vide ensuite. C'est aussi ce qui rend les assertions du genre
     * « assertExists » fiables : elles portent alors sur ce que l'essai vient
     * d'écrire, et non sur ce qu'un essai précédent avait laissé.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }
}
