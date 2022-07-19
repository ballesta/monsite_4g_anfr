<?php
/**
 * Created by PhpStorm.
 * User: bernard
 * Date: 26/02/19
 * Time: 14:41
 */

class Csv_reader
{
    function __construct(string $chemin_dossier)
    {
        $this->chemin_dossier = $chemin_dossier;
    }

    function ouvre_en_lecture(string $nom_fichier_csv)
    {
        $nom_fichier_csv_complet = $this->chemin_dossier . $nom_fichier_csv;
        if (($this->fichier_csv = fopen($nom_fichier_csv_complet, "r")) == FALSE)
        {
            die("Fichier $nom_fichier_csv non trouvé\n") ;
        }
        return;
    }

    function lis_ligne(&$ligne)
    {
        $resultat_lecture = fgetcsv($this->fichier_csv, 1000, ";", '"');
        if ($resultat_lecture !== FALSE)
        {
            // Ligne lue: la renvoyer
            $ligne = $resultat_lecture;
            return true;
        }
        else
        {
            return false;
        }
    }

    function ferme()
    {
        fclose($this->fichier_csv);
    }
}



//test_unitaire();

function test_unitaire()
{
    // /home/bernard/www/monsite4g/monsite4g_anfr/donnees_cartoradio_fr/import_cartoradio/temporaire/Supports_Cartoradio.csv

    $csv = new Csv_reader('../import_cartoradio/temporaire/');
    echo "Mémorisation chemin: $csv->chemin_dossier \n";
    $csv->ouvre_en_lecture("Supports_Cartoradio.csv");
    $ok = $csv->lis_ligne($ligne);
    print_r($ligne);
    echo "$ok \n";
    $n = 0;
    while ($csv->lis_ligne($ligne))
    {
        $n++;
        if ($n==2000)print_r($ligne);
    }
    echo "$n lignes lues ";
}
