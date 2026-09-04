<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Le canal téléphonique.
 *
 * Le code de remise avait une faiblesse que le courriel ne corrige pas : il
 * s'affiche sur un écran. Un client qui commande de la quincaillerie depuis un
 * téléphone simple, ou qui n'ouvre pas ses courriels, n'aura pas son code sous
 * les yeux quand le livreur sonne — et la preuve ne prouve rien si l'acheteur
 * ne peut pas la produire.
 *
 * Le téléphone est la seule adresse que tout le monde possède ici. C'est aussi
 * une source distincte : un client injoignable par courriel reste joignable par
 * SMS, et l'inverse est vrai.
 *
 * L'envoi réel exige un contrat avec un opérateur — Orange, Free, Expresso — et
 * un projet académique n'en a pas. Le service est donc complet, et son seul
 * pilote écrit dans le journal, comme « MAIL_MAILER=log » le fait pour les
 * courriels. Brancher une passerelle revient à ajouter un pilote ici : rien
 * d'autre dans l'application ne change.
 */
class Sms
{
    public function __construct(private ?string $canal = null) {}

    /**
     * Envoie un message court.
     *
     * Ne lève jamais : un opérateur en panne ne doit pas faire échouer une
     * vente, exactement comme pour le courriel. L'échec est journalisé.
     */
    public function envoyer(?string $telephone, string $texte): bool
    {
        $numero = $this->normaliser($telephone);

        if ($numero === null) {
            Log::warning('SMS non envoyé : numéro inexploitable', ['brut' => $telephone]);

            return false;
        }

        try {
            return match ($this->canal()) {
                'log' => $this->journaliser($numero, $texte),
                'null' => true,
                default => $this->journaliser($numero, $texte),
            };
        } catch (Throwable $e) {
            Log::warning('SMS non envoyé', ['erreur' => $e->getMessage()]);

            return false;
        }
    }

    private function canal(): string
    {
        return $this->canal ?? config('services.sms.canal', 'log');
    }

    /**
     * Le journal ne garde que la fin du numéro.
     *
     * Un fichier de journal se recopie, se partage et finit par traîner. Il n'a
     * pas besoin du numéro entier pour être utile au débogage.
     */
    private function journaliser(string $numero, string $texte): bool
    {
        Log::info('SMS', [
            'vers' => str_repeat('•', max(0, strlen($numero) - 4)) . substr($numero, -4),
            'texte' => $texte,
        ]);

        return true;
    }

    /**
     * Remet un numéro sénégalais au format international.
     *
     * Les carnets d'adresses contiennent « 77 123 45 67 », « 221771234567 » et
     * « +221 77 123 45 67 » pour le même abonné. Sans normalisation, deux
     * clients sur trois ne recevraient rien.
     */
    private function normaliser(?string $telephone): ?string
    {
        if ($telephone === null) {
            return null;
        }

        $chiffres = preg_replace('/\D+/', '', $telephone);

        // Neuf chiffres : un numéro local, il lui manque l'indicatif.
        if (strlen($chiffres) === 9) {
            $chiffres = '221' . $chiffres;
        }

        // Douze chiffres commençant par 221 : le format attendu.
        return strlen($chiffres) === 12 && str_starts_with($chiffres, '221')
            ? '+' . $chiffres
            : null;
    }
}
