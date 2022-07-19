<?php
/*
Séquence d'Appel des scripts pour l'import des données de l'ANFR
================================================================

1) monsite4g_anfr/donnees_cartoradio_fr/cas/tache_cron/init_import.php
    Demarre un cycle d'import complet
2) monsite4g_anfr/donnees_cartoradio_fr/cas/tache_cron/cron_export_import_departement.php
    Demande d'import d'un département à l'ANFR
    Réception du mail de l'ANFR contenant le lien de téléchargement du departement n-1
    Téléchargement des données ANFR du département
    Import dans base de données
3) monsite4g_anfr/donnees_cartoradio_fr/cas/tache_cron/post_import.php
    Traitements de vérification des données importées
    Mise en form pour optimiser les requêtes
        Détection des antennes autorisées et non activées
        Mémorisation dans table supports_antennes
4) monsite4g_anfr/donnees_cartoradio_fr/cas/tache_cron/transfert_anfr_exploitation.php
    Transfert de la base Anfr vers la base de test-etude.monsite4g
*/

// Vérification finale du contenu de la base de données de 'test-etude.monsite4G.fr
// après un cycle d'import des antennes et supports de tous les département
// de Cartoradio.fr

require_once "../../composants/Base_donnees.class.php";


class Verification_finale
{
    // Ouvre et valide les base des données
    // - importées de l'ANFR
    // - Exploitation après transfert
    function __construct(string $nom_base )
    {
        // Connexion à la base de données à vérifier
        $this->base = new Base_donnees($nom_base );
        // Vérification sommaire du contenu de la base
        if ($this->nombre_supports_importes()
            &&
            $this->nombre_antennes_importees())
        {
            $this->base_valide = true;
        }
        else
        {
            $this->base_valide = false;
        }
    }

    function nombre_supports_importes()
    {
        $r=$this->base->execute_sql
        ( 'SELECT COUNT(*) as nbr_supports
                FROM supports');
        //print_r($r);
        $nbr_supports = $r[0]['nbr_supports'];
        echo "nbr_supports: $nbr_supports<br>";
        // Le nombre de supports doit être entre:
        if (   $nbr_supports >= 72000
            && $nbr_supports <= 180000
        )
        {
            echo "OK: Le nombre de supports est entre 72 000 et 180 000<br>";
            return true;
        }
        else
        {
            echo "***Erreur***: Le nombre de supports n'est pas entre 72 000 et 180 000<br>";
            return false;
        }
    }

    function nombre_antennes_importees()
    {
        $r=$this->base->execute_sql
        ( 'SELECT COUNT(*) as nbr_antennes
                FROM antennes');
        $nbr_antennes = $r[0]['nbr_antennes'];
        echo "nbr_antennes: $nbr_antennes<br>";
        // Le nombre d'antennes doit être entre 2.5 et 3 millions
        if (   $nbr_antennes >= 2400000
            && $nbr_antennes <= 5000000
        )
        {
            echo "OK: Le nombre d'antennes est entre 2.4 et 5 millions<br>";
            return true;
        }
        else
        {
            echo "***Erreur***: Le nombre d'antennes n'est pas entre 2.4 et 5 millions<br>";
            return false;
        }
    }
}



?>

