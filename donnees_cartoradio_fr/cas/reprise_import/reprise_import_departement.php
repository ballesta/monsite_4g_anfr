<?php

/*
-----------------------------------------------
Reprise des export des données de cartoradio.fr
-----------------------------------------------


//todo *** Commentaires à revoir ***

L'export-import des données de l'ANFR est faite par départements à partir du site "www.cartoradio.fr"
selon la procédure suivante:

	1. la demande d'export est faite site "www.cartoradio.fr" en simulant une session d'un internaute (ce script)
	1.1 Connexion au compte "import_cartoradio@ballesta.fr" avec le mot de passe '//11031049'
		=> fonction connexion_cartoradio()
	1.2 Exporter la prochaine tranche de départements
		=> Identifiants de 1 (Ain) à 102 (Mayotte) 

Les départements ont identifiées par un entier de 1 à 102 comme le fait 
le site "cartoradio.fr" en interne.

Cet identifiant correspond peu ou prou au numéro de département
	- 01 à 19 id = département
    - décallage de 1 du à la corse (2A, 2B remplaçant 20) 20 à 21
	- Département d'outremer  de 98 à 102

Pour accéder au département importé, nous devons consulter la table des supports 
qui contient les codes postaux des lieux.  

Chaque demande d'export réussie (acquittée  par le site) est ajoutée dans la table des departements.

La table des départements enregistre la séquence des imports réussis

========================
Tâche lancée par le CRON
========================

##Traitement tâche Cron pour importer les supports et antennes de Cartoradio##

Lancé par le Cron périodiquement (Toutes les 5 minutes de 20h à 8h:
* 12*12 = 144 traitement par jour => Tous les départements
* Réduire la fréquence par la suite

Principe
* D'abord traiter les fichiers en attente de téléchargement
* en fin demander l'export du prochain département

Détail du traitement
** Lire les mails de Cartoradio
** Télécharger les fichiers à l'aide du lien contenu dans le message de mise à disposition des données
** Décompresser l'archive *.zip
** Effacer les supports et antennes du département de la base
** Ajouter les données du département
** Demander l'export du prochain département qui sera traité à la prochaine exécution

* Les tâches cron sont limitées à  30s de CPU; les traitement doivent donc ne pas dépasser cette durée sous peine
  d'être interrompus brutalement.
** En cas d'interruption brutale, le compte rendu d'exécutionde l'import du département ne sera pas enregistrée,
 * ce qui donnera lieu à une tentative d'import ultérieure lors d'un passage du cron suivant.


Algorithme détaillé

*/


require_once "../../composants/Base_donnees.class.php";
require_once "../../composants/Exporte_departements.class.php";
require_once "../../composants/Importe_departements.php";
require_once "../../composants/Email_reader.php";
require_once '../../composants/CurlDownloader.php';

header('Content-Type: text/html; charset=utf-8');

reprise_donnees_cartoradio();

echo "<h2>Fin</h2>";

/*
Hiérarchie des fonctions:
Init import: RAZ base de réception
import_donnees_(fichier zip par departement reçus de cartoradio)
    Pour tous les fichiers zip recus de cartoradio
        importe_fichier
            Importe_zip_dans_base
                decompresse
                importe_dans_base
                    supprime_supports_et_antennes_sas
                    importe_departement_dans_base

*/

function reprise_donnees_cartoradio()
{
    header('Content-Type: text/html; charset=utf-8');

    echo "<h2>Importe les supports et antennes des départements à partir des *.zip</h2>";

    // Init import: RAZ base de réception
    init_import();

    // import_donnees_(fichier zip par departement reçus de cartoradio)
    //    Pour tous les fichiers zip recus de cartoradio
    $dossier = "../../import_cartoradio/2018-11-10";
    $export_zip = array_diff(scandir($dossier), array('.', '..'));
    //var_dump($export_zip); die();
    $i=1;
    foreach ($export_zip as $f)
    {
        echo $i++, ' ', $f, '<br>';
        set_time_limit(30);
        //if ($f == "EXPORT_CARTORADIO_10-11-2018_06h20m08.zip")
        //{
            Importe_zip_dans_base($dossier . '/' . $f);
        //}
        //break;
        //if ($i>=10)break;
    }
}

function init_import()
{
    $e = new Exporte_departements();
    $e->initialise_export_import_tous_departements();
}

// Importe le fichier téléchargé dans la base de données monsite4G.
function Importe_zip_dans_base($zip_file_name)
{
    $dossier = "../../import_cartoradio";
    $dossier_decompression = "$dossier/temporaire";
    // Les fichier decompresses ecrasent ceux du departement précédentsdans le dossier ./temporaire
    decompresse($zip_file_name,
                $dossier_decompression);
    // Les fichiers CSV sont disponibles, reste à les traiter et les importer dans la base
    importe_dans_base();
}

function decompresse($fichier_zip, $dans_dossier)
{
    //echo "----Décompresse: $fichier_zip dans $dans_dossier<br>";
    $zip = new ZipArchive;
    $resultat = $zip->open($fichier_zip);
    if ($resultat === TRUE) {
        // Extraction
        $zip->extractTo($dans_dossier);
        $zip->close();
        //echo '---- ----OK<br>';
    }
    else {
        echo '---- ----*** erreur ***<br>';
        echo "decompresse($fichier_zip, $dans_dossier)";
        exit();
    }
}

//--------------------------------------------------------------

// Entrée: 	Fichiers zip (1 par département) importés par lien mail de cartoradio.fr
//         	Stockés dans      : /monsite4g/donnees_cartoradio_fr/import_cartoradio
//          Décompression dans: /monsite4g/donnees_cartoradio_fr/import_cartoradio/temporaire
// Sortie: Tables supports et antennes de la base de données.


function importe_dans_base()
{
    //echo "Importe les supports et antennes des départements<br>";
    $departement = importe_departement_dans_base();

}

// Importe les antennes et supports du département
// à partir des fichiers CSV contenus dans "donnees_cartoradio_fr/import_cartoradio/temporaire"
function importe_departement_dans_base()
{
    // Importe supports
    $sql_insert_into = "INSERT IGNORE INTO `supports` (`support_id`, 
												`longitude`, 
												`latitude`, 
												`position`, 
												`insee`, 
												`lieu_dit`, 
												`adresse`, 
												`code_postal`, 
												`commune`, 
												`nature_support`, 
												`hauteur`, 
												`proprietaire`)";
    $fichier_csv = "Supports_Cartoradio.csv";
    importe_table('supports', $fichier_csv, $sql_insert_into);

    $departement = ajoute_departement_aux_supports();

    // Importe antennes
    $sql_insert_into = "INSERT IGNORE INTO `antennes` (
											   `support_id` ,
											   `numero_cartoradio` ,
											   `exploitant` ,
											   `type_antenne` ,
											   `numero_antenne` ,
											   `dimension` ,
											   `directivite` ,
											   `azimut` ,
											   `hauteur_sol` ,
											   `systeme` ,
											   `debut` ,
											   `fin` ,
											   `unite`
											   )";
    $fichier_csv = "Antennes_Emetteurs_Bandes_Cartoradio.csv";
    importe_table(
        'antennes',
        $fichier_csv,
        $sql_insert_into);
    return $departement;
}

// Ajoute le département importé aux supports
function ajoute_departement_aux_supports()
{
    $base = new Base_donnees('monsite4g_anfr');
    // Pour les requêtes longues
    $r = $base->execute_sql("SELECT DISTINCT substr(code_postal,1,2) AS d,
                                        COUNT(*) as n   
                                 FROM supports
                                 WHERE departement IS NULL
                                 GROUP BY d
                                 ORDER BY n DESC;");
    // Département ayant le plus de supports = département exporté
    //print_r($r);
    $departement = $r[0]['d'];
    echo "<br>departement importé:$departement<br>";
    $base->execute_sql("UPDATE supports 
                               SET departement = substr(code_postal,1,2)
                             WHERE departement IS NULL;");
    return $departement;
}


// Importe les donnéees d'origine ANFR dans une table
function importe_table($nom_table, $fichier_csv, $sql_insert_into)
{
//----
    $sql_debut = <<<FIN
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

$sql_insert_into
VALUES

FIN;

//----
    $sql_valeurs = transforme_csv("../../import_cartoradio/temporaire/$fichier_csv");
//----
    $sql_fin = <<<FIN
;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
FIN;

    $sql = $sql_debut . $sql_valeurs . $sql_fin;

    $fichier_sortie = "import_sql/$nom_table.sql";
    file_put_contents($fichier_sortie,
                      $sql);

    // Execute le script d'importation d'un departement sur la base de données
    importe($sql);
}

// Transcode le fichier exporté par le site de l'ANFR
// Codage entrée:
//		- Code caratères : ANSI
//		- "Chaines entre doubles quotes"
// Codage sortie:
//		- Code caratères : UTF-8
//		- 'Chaines entre simples quotes'
//		-		Echapement des simples quotes (') par doublement ('')
function transforme_csv($nom_fichier_csv)
{
    // Fichier SQL contenant la requete correspondant au CSV
    // -- Requête SQL trop grande pour les antennes du 75
    // -- Stocke dans fichier xxxx.csv => xxxx.sql
    //$nom_fichier_sql = substr($nom_fichier_csv, 0 , strlen($nom_fichier_csv)-3) . 'sql';
    //$fichier_sql = fopen("testfile.txt", "w");
    $numero_ligne_fichier_csv = 0;
    if (($fichier_csv = fopen($nom_fichier_csv, "r")) !== FALSE) {
        $valeurs_insert_sql = "";

        // Lis la prochaine ligne du fichier csv à importer dans un tableau de valeurs
        while (($ligne_csv = fgetcsv($fichier_csv, 1000, ";", '"')) !== FALSE) {
            // Première ligne = 1
            $numero_ligne_fichier_csv++;
            // Saute la première ligne qui contient le nom des champs
            if ($numero_ligne_fichier_csv > 1) {
                if ($numero_ligne_fichier_csv == 2)
                {
                    // Première ligne: pas de séparateur
                    $valeurs = '';
                }
                else
                {
                    // Après la première ligne: séparateur
                    $valeurs = ',';
                }

                $nombre_valeurs = count($ligne_csv);
                // Début des valeurs de la ligne
                $valeurs .= '(';
                // Pour chaque champ de la ligne csv: transférer valeur et séparer par une virgule.
                for ($c = 0; $c < $nombre_valeurs; $c++) {
                    // Entre quotes en les doublant: "l'adresse" ==> "l''adresse"
                    $v = protege_quotes($ligne_csv[$c]);
                    // Met entre quotes
                    $valeurs .= "'" . $v . "'";
                    if ($c < $nombre_valeurs - 1) {
                        // Pas la dernière valeur:séparer par une virgule
                        $valeurs .= ',';
                    }
                }
                $valeurs .= ')';
                $valeurs .= "\n";
                // Transcode les valeurs en UTF8
                $valeurs = utf8_encode($valeurs);
                // Ajoute au sql
                $valeurs_insert_sql .= $valeurs;
                //todo remove
                //if ($numero_ligne_fichier_csv >=10) break;
            }
        }
        //echo $valeurs_insert_sql, '<hr>';
        fclose($fichier_csv);
        return $valeurs_insert_sql;
    }
    else {
        echo "Fichier $nom_fichier_csv non trouvé\n";
        exit();
    }
}

function protege_quotes($chaine)
{
    $chaine_protegee = str_replace("'",
        "''",
        $chaine);
    return $chaine_protegee;
}

// Exécute le script d'importation d'un département
function importe($sql)
{
    //echo "importe($sql)",'<br>';
    $base = new Base_donnees('monsite4g_anfr');
    // Pour les requêtes longues
    $base->execute_sql("SET wait_timeout=5000;");
    $r = $base->execute_sql($sql);
    //echo $r, '\n';
    //print_r($base->connecteur_bd->errorInfo());
}

// Transfère les supports et antennes d'un département
// Du sas vers la base opérationnelle
function transfere_sas_dans_base($departement)
{
    echo "transfere_sas_dans_base($departement)<br>";
    $base = new Base_donnees('monsite4g');

    echo 'Rempli le departement sur les supports<br>';
    $r = $base->execute_sql("UPDATE supports 
                                    SET departement = SUBSTR(CODE_POSTAL,1,2)
                                  WHERE departement = ''; 
                                ");

    echo 'Supprime les supports et les antennes du département dans la base<br>';
    $r = $base->execute_sql("DELETE FROM supports 
                                  WHERE DEPARTEMENT = '$departement'");

    echo 'Transfère les supports<br>';
    $r = $base->execute_sql("INSERT INTO supports
                                SELECT * FROM  monsite4g_anfr.supports;");

    echo 'Transfère les antennes <br>';
    $r = $base->execute_sql("INSERT INTO antennes
                                SELECT * FROM  monsite4g_anfr.antennes;");

    echo "Note l'import du departement <br>";
    $r = $base->execute_sql("UPDATE monsite4g_anfr.departements
			                        SET dp_date_import = NOW()
				                   WHERE DP_id = $departement");

    echo "Vide l'import<br>";
    $r = $base->execute_sql("DELETE FROM monsite4g_anfr.antennes");
    $r = $base->execute_sql("DELETE FROM monsite4g_anfr.supports");

}

?>