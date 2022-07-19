<?php

/*
------------------------------------------------------
Automatisation des export des données de cartoradio.fr
------------------------------------------------------

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

Chaque demande d'export réussie (acquittée  par le site) est ajoutée dans la table export.

La table des export enregistre la séquence des imports réussis

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
require_once "../../composants/Email_reader.php";
require_once '../../composants/CurlDownloader.php';

import_donnees_cartoradio();

/*
Hiérarchie des fonctions:
import_donnees_cartoradio
    traite_mail
        Email_reader
        importe_fichier
            Importe_zip_dans_base
                decompresse
                importe_dans_base
                    supprime_supports_et_antennes_sas
                    importe_departement_dans_base
                    transfere_sas_dans_base
    demande_export_prochain_departement
*/

function import_donnees_cartoradio()
{
    header('Content-Type: text/html; charset=utf-8');

    traite_mail();

    demande_export_prochain_departement();
}

// Parcours les messages reçus de cartoradio.fr
// à la recherche des réponses aux demandes d'export.
// Pour chaque mail:
// 	Chaque mail contient un lien vers le fichier à télécharger.
//	Télécharge le fichier Zip dans:
//      "/monsite4g-anfr/donnees_cartoradio_fr/import_cartoradio"
//	Décompresse le fichier dans +++
//	L'import des fichiers CSV dans la base de données de monSite4g
//      sera faite en phase 3
function traite_mail()
{
    echo "Traite mails<br>";
    $emails = New Email_reader();
    $nombre_mails_traites = 0;
    // Pour tous les messages de la boite mail
    if (count($emails) > 0) {
        foreach ($emails->inbox as $mail) {
            $h = $mail['header'];
            //var_dump($h);
            //die();

            $emetteur = trim($h->fromaddress);
            echo "'$emetteur'<br>";
            $sujet = trim($h->subject);
            if (  //($emetteur == 'Cartoradio ')
                //&&
            ($sujet == "Votre demande d'export Cartoradio")) {
                $b = $mail['body'];
                // Decode Type Mime
                $texte = quoted_printable_decode($b);
                //echo $texte;


                // Début de l'url du lien vers le fichier à télécharger
                $debut = 'https://www.cartoradio.fr/telechargement/';
                $p = strpos($texte,
                    $debut);
                // Présence du lien?
                if ($p > 0) {
                    // Présence du lien de téléchargement dans le corps du mail.
                    // Extraire la clef identifiant le téléchargement sur 32 caractères.
                    $id = substr($texte,
                        $p + strlen($debut),
                        32);

                    // Ajouter le lien de téléchargement
                    $lien_import_fichier_zip = $debut . $id;
                    echo 'Fichier:', $lien_import_fichier_zip, '<br>';

                    importe_fichier($lien_import_fichier_zip);
                    $nombre_mails_traites++;
                    flush();
                }
                else {
                    echo "Le lien de téléchargement n'est pas présent: ne pas traiter ce message";
                    echo '<br>';
                }
            }
            else {
                echo "Mail hors sujet (sujet = '$sujet', emetteur =  '$emetteur' inconnus): ne pas traiter ce message";
                echo '<br>';
            }
            if ($nombre_mails_traites == 1) {
                // Traite un seul mail à chaque passage du Cron
                // Amélioration possible: traiter  plusieurs mails si il reste du temps dans les 30s
                $index_email = $mail['index'];
                $emails->delete_mail($index_email);
                break;
            }
        }
    }
    else
        {
              // Pas de mails à traiter
        }
    echo "nombre_mails_traites:	$nombre_mails_traites<br>";
}

// Importe le fichier dont le lien a été reçu par mail.
function importe_fichier($lien_import_fichier_zip)
{
    echo "lien_import_fichier_zip:$lien_import_fichier_zip<br>";
    $downLoader = new curlDownloader($lien_import_fichier_zip);
    $taille_fichier = $downLoader->download();
    echo "taille_fichier:$taille_fichier<br>";
    if ($taille_fichier > 0) {
        //echo "Taille fichier: $taille_fichier <br>";
        $zip_file_name = $downLoader->getFileName();
        echo "Downloaded $taille_fichier bytes to $zip_file_name <br>";
        // Voir si la taille du fichier est cohérente.
        // Si la taille est trop faible, c'est surement un message d'erreur.
        $taille = filesize($zip_file_name);
        echo 'Taille fichier téléchargé: ', $taille, '<br>';
        if ($taille <= 1000) {
            // Trop petit pour un fichier de données.
            // Le lien est surement périmé.
            $reponse = file_get_contents($zip_file_name);
            if (strpos($reponse,
                    'Le fichier demandé n\'existe plus ou a déjà été téléchargé.<br>')
                > 0
            ) {
                echo 'Le fichier a été dèja téléchargé<br>';
                $resultat_import = false;
            }
            else {
                echo 'Le fichier est trop petit mais aucune indication comme dèja téléchargé?<br>';
                $resultat_import = false;
            }
        }
        else {
            // Taille supérieure à 1000.
            Importe_zip_dans_base($zip_file_name);
            $resultat_import = true;
        }
    }
    else {
        echo 'Pas de fichier téléchargé<br>';
        $resultat_import = false;
    }
    return $resultat_import;
}

// Importe le fichier téléchargé dans la base de données monsite4G.
function Importe_zip_dans_base($zip_file_name)
{
    $dossier = "../../import_cartoradio";
    $dossier_decompression = "$dossier/temporaire";
    decompresse($zip_file_name, $dossier_decompression);
    importe_dans_base();
}

function decompresse($fichier_zip, $dans_dossier)
{
    echo "----Décompresse: $fichier_zip dans $dans_dossier<br>";
    $zip = new ZipArchive;
    $resultat = $zip->open($fichier_zip);
    if ($resultat === TRUE) {
        // Extraction
        $zip->extractTo($dans_dossier);
        $zip->close();
        echo '---- ----OK<br>';
    }
    else {
        echo '---- ----*** erreur ***<br>';
    }
}

function demande_export_prochain_departement()
{
    echo "<h2>Demande d'export de départements à partir de 'cartoradio.fr'</h2>";
    // Prochain département dont il faut demander l'export.
    $departement_a_importer = prochain_departement_a_exporter();
    echo "<hr>", $departement_a_importer['dp_numero'], " ", $departement_a_importer['dp_id'], "<hr>";
    $dp_id = $departement_a_importer['dp_id'];
    $e = new Exporte_departements();
    $e->exporte_departement($dp_id);
    $e->enregistre_demande_export_departement($dp_id);
    echo "<h2>Fin de la demande d'Export départements</h2>";
}

// Renvoie le prochain département importer de Cartoradio
function prochain_departement_a_exporter()
{
    echo "prochain_departement_a_importer<br>";
    $base = new Base_donnees();
    $sql = 'SELECT * 
            FROM departements
            WHERE dp_date_demande_export IS NULL 
            ORDER BY dp_numero 
            LIMIT 1';
    $departement = $base->execute_sql($sql);
    print_r($departement);
    return $departement[0];
}

//--------------------------------------------------------------

// Entrée: 	Fichiers zip (1 par département) importés par lien mail de cartoradio.fr
//         	Stockés dans      : /monsite4g/donnees_cartoradio_fr/import_cartoradio
//          Décompression dans: /monsite4g/donnees_cartoradio_fr/import_cartoradio/temporaire
// Sortie: Tables supports et antennes de la base de données.


function importe_dans_base()
{
    echo "Importe les supports et antennes des départements<br>";

    supprime_supports_et_antennes_sas();

    $departement = importe_departement_dans_base();

    transfere_sas_dans_base($departement);
}

// Raz de la base d'importation  monsite4g_anfr.
// Supprime tous les supports du département importé
// ainsi que les antennes qui sont liées par le N° de support
// (Les antennes sont liées aux support donc sont supprimées par intégritté de référence)
function supprime_supports_et_antennes_sas()
{
    echo "Supprime les antennes et supports du departement";
    $base = new Base_donnees('monsite4g_anfr');
    // Supprime les supports et les antennes acrochées aux supports
    $sql = 'DELETE FROM supports';
    $base->execute_sql($sql);
}

// Importe les antennes et supports du département
// à partir des fichiers CSV contenus dans "donnees_cartoradio_fr/import_cartoradio/temporaire"
function importe_departement_dans_base()
{
    // Importe supports
    $sql_insert_into = "INSERT INTO `supports` (`support_id`, 
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
    importe_table('supports',
        $fichier_csv,
        $sql_insert_into);

    $departement = ajoute_departement_aux_supports();

    // Importe antennes
    $sql_insert_into = "INSERT INTO `antennes` (
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
    importe_table('antennes',
        $fichier_csv,
        $sql_insert_into);
    flush();
    return $departement;
}

// Ajoute le département importé aux supports
function ajoute_departement_aux_supports()
{
    $base = new Base_donnees(monsite4g_anfr);
    // Pour les requêtes longues
    $r = $base->execute_sql("SELECT DISTINCT substr(code_postal,1,2) AS departement  FROM supports;");
    if (count($r) == 1) {
        // Un seul département: correspond à la demande d'export d'un département à la fois
        $departement = $r[0]['departement'];
        echo "<br>departement importé:$departement<br>";
        $base->execute_sql("UPDATE supports SET departement = '$departement';");
    }
    else
    {
        echo "Plusieurs départements importés<br>";
        print_r($r);
        die();
    }
    return $departement;
}


// Importe les donnéees d'origine ANFR dans une table
function importe_table(
    $nom_table,
    $fichier_csv,
    $sql_insert_into
)
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
    //$sql = utf8_encode($sql);
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
    $numero_ligne_fichier_csv = 0;
    if (($fichier_csv = fopen($nom_fichier_csv,
            "r")) !== FALSE) {
        $valeurs_insert_sql = "";
        while (($ligne_csv = fgetcsv($fichier_csv,
                1000,
                ";",
                '"')) !== FALSE) {
            $numero_ligne_fichier_csv++;
            // Saute la première ligne qui contient le nom des champs
            if ($numero_ligne_fichier_csv > 1) {
                $nombre_valeurs = count($ligne_csv);
                $valeurs = '(';
                for ($c = 0; $c < $nombre_valeurs; $c++) {
                    // Entre quotes
                    $v = protege_quotes($ligne_csv[$c]);
                    $valeurs .= "'" . $v . "'";
                    if ($c < $nombre_valeurs - 1) {
                        $valeurs .= ',';
                    }
                }
                $valeurs .= ')';
                $valeurs .= ',';
                $valeurs .= "\n";
                $valeurs_insert_sql .= $valeurs;
            }
        }
        // Enlève la dernière virgule
        $valeurs_insert_sql[strlen($valeurs_insert_sql) - 2] = ' ';
        fclose($fichier_csv);
        return $valeurs_insert_sql;
    }
    else {
        echo "Fichier $nom_fichier_csv non trouvé\n";
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
    //echo "$sql",'\n';
    $base = new Base_donnees(monsite4g_anfr);
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
    $base = new Base_donnees(monsite4g);

    echo 'Rempli le departement sur les supports<br>';
    $r = $base->execute_sql("UPDATE supports 
                                    SET departement = SUBSTR(CODE_POSTAL,1,2)
                                  WHERE departement = ''; 
                                ");

    echo 'Supprime les supports du département dans la base<br>';
    $r = $base->execute_sql("DELETE FROM supports 
                                  WHERE DEPARTEMENT = '$departement'");

    echo 'Transfère les supports<br>';
    $r = $base->execute_sql("INSERT INTO supports
                                SELECT * FROM  monsite4g_anfr.supports;");

    echo 'Transfère les antennes <br>';
    $r = $base->execute_sql("INSERT INTO antennes
                                SELECT * FROM  monsite4g_anfr.antennes;");

    echo 'Note l import du departement <br>';
    $r = $base->execute_sql("INSERT INTO antennes
                                SELECT * FROM  monsite4g_anfr.antennes;");

    $r = $base->execute_sql("UPDATE departements
			                        SET dp_date_import = NOW()
				                   WHERE DP_id = $departement");
}

?>