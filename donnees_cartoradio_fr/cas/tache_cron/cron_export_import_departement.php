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
require_once '../../composants/Trace_import_departements.php';
require_once '../../composants/Anomalie.class.php';
require_once '../../composants/Csv_reader.php';
require_once 'execute.php';

$anomalie = new Anomalie();

import_donnees_cartoradio();

die("fin import_donnees_cartoradio");

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
    demande_export_prochain_departement
*/

function import_donnees_cartoradio()
{
    header('Content-Type: text/html; charset=utf-8');
    
    execute(connexion_cartoradio(), "connexion_cartoradio");

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
    global $anomalie;
    $anomalie->titre("Traite mails") ;
    echo "traite_mail()==><br>";
    //--execute(connexion_cartoradio(), "connexion_cartoradio");
    $emails = New Email_reader();
    $nombre_mails_traites = 0;
    // Pour tous les messages de la boite mail
    $messages_a_traiter = count($emails->inbox);
    if ($messages_a_traiter > 0) {
        foreach ($emails->inbox as $mail) {
            $h = $mail['header'];
            $emetteur = trim($h->fromaddress);
            echo $emetteur, "<br>";
            $sujet = trim($h->subject);
            if ($sujet == "Votre demande d'export Cartoradio") {
                $b = $mail['body'];
                // Decode Type Mime
                $texte = quoted_printable_decode($b);
                trace ("Texte mail: $texte");

                // Début de l'url du lien vers le fichier à télécharger
                $debut = 'https://www.cartoradio.fr/#/telechargement';
                $p = strpos($texte, $debut);
                // Présence du lien de téléchargement?
                if ($p > 0) {
                    // Présence du lien de téléchargement dans le corps du mail.
                    // Extraire la clef identifiant le téléchargement sur 32 caractères.
                    $id = substr($texte,
                                 $p + strlen($debut),
                                 32);
                    // Ajouter le lien de téléchargement
                    $lien_import_fichier_zip = $debut . $id;
                    echo 'Fichier:', $lien_import_fichier_zip, '<br>';
                    Trace_import_departements::memorise_attribut('dp_date_demande_export', date("Y-m-d H:i:s"));

                    $resultat_import = importe_fichier($lien_import_fichier_zip);
                    if ($resultat_import){
                        Trace_import_departements::maj_attributs();
                        flush();
                    }
                    $nombre_mails_traites++;
                }
                else {
                    echo "Le lien de téléchargement n'est pas présent: ne pas traiter ce message", '<br>';
                }
            }
            else {
                echo "Mail hors sujet (sujet = '$sujet', emetteur =  '$emetteur' inconnus): ne pas traiter ce message",'<br>';
            }
            $index_email = $mail['index'];
            $emails->delete_mail($index_email);
            if ($nombre_mails_traites == 1) {
                break;
            }
        } // foreach mail
    }
    else
    {
        // Pas de mails à traiter
        trace("Pas de mails à traiter!: $messages_a_traiter");
    }
    trace("nombre_mails_traites: $nombre_mails_traites");
}

function trace($texte)
{
    echo "Trace: $texte <br>";
}

/*
// Connexion au site "www.cartoradio.fr"
// La connexion est mémorisée dans le navigateur (cookies).
function connexion_cartoradio()
{
    trace("Connexion à cartoradio v01");

    // Create temp file to store cookies
    $ckfile = tempnam ("/tmp", "CURL_Cookie_");

    // L'initialisation du connecteur curl en php se fait 
    // en utilisant la fonction curl_init().
    // cette fonction retourne un connecteur curl.
    $url = 'https://cartoradio.fr/api/v1/utilisateurs/signin';
    $curl = curl_init($url);
    trace("curl_init($url)");
    // Prépare les données à envoyer pour la connection.
    // Compte sur Cartoradio
    $identifiant_compte = [
        'login' => 'import_cartoradio@ballesta.fr',
        'pwd'   => '//11031049'
    ];
    $identifiant_compte_json = json_encode($identifiant_compte);
    trace("Identifiant connexion: $identifiant_compte_json");

    // Précise les Options de connection

    // CURLOPT_POST : la requête doit utiliser le protocole POST 
    // pour sa résolution.
    execute(curl_setopt($curl, 
                        CURLOPT_POST, 
                        true), 
            'POST');

    // CURLOPT_CUSTOMREQUEST : pour forcer le format 
    // de la commande HTTP POST
    //execute(curl_setopt($curl, 
    //                    CURLOPT_CUSTOMREQUEST, 
    //                    'POST'), 
    //'CUSTOMREQUEST');

    // CURLOPT_HTTPHEADER : tableau non associatif pour modifier 
    // des paramètres du header envoyé par la requête.
    execute(curl_setopt($curl,
        CURLOPT_HTTPHEADER,
        [
            'Cache-Control: no-cache',
            'accept: application/json, text/plain, */*',
            'content-type: application/json',
            'origin: https://cartoradio.fr',
            'login:import_cartoradio@ballesta.fr',
            'pwd://11031049'
            ]),
        'HEADER');


    execute(
        curl_setopt(
            $curl,
            CURLOPT_COOKIEJAR,
            $ckfile),
        'COOKIE');

    // CURLOPT_RETURNTRANSFER : 
    // si nous voulons ou non récupérer le contenu 
    // de la requête appelée
    execute(
        curl_setopt(
            $curl,
            CURLOPT_RETURNTRANSFER,
            true),
        'RETURN');

    $reponse_json = execute(curl_exec($curl), 'curl_exec');

    echo "Réponse curl_exec: $reponse_json <br>";

    return true;
}
*/
// Importe le fichier dont le lien a été reçu par mail.
function importe_fichier($lien_import_fichier_zip)
{
    Trace_import_departements::memorise_attribut('dp_erreurs',"lien_import_fichier_zip:$lien_import_fichier_zip");
    $downLoader = new curlDownloader($lien_import_fichier_zip);
    $taille_fichier = $downLoader->download();
    if ($taille_fichier > 0) {
        $zip_file_name = $downLoader->getFileName();
        Trace_import_departements::memorise_attribut('dp_erreurs',"Downloaded $taille_fichier bytes to $zip_file_name");
        // Voir si la taille du fichier est cohérente.
        // Si la taille est trop faible, c'est surement un message d'erreur.
        $taille = filesize($zip_file_name);
        Trace_import_departements::memorise_attribut('dp_erreurs',"Taille fichier téléchargé: $taille");
        if ($taille <= 1000) {
            // Trop petit pour un fichier de données.
            // Le lien est surement périmé.
            $reponse = file_get_contents($zip_file_name);
            if (strpos($reponse,
                    'Le fichier demandé n\'existe plus ou a déjà été téléchargé.<br>')
                > 0
            ) {
                Trace_import_departements::memorise_attribut('dp_erreurs','Le fichier a été dèja téléchargé');
                $resultat_import = false;
            }
            else {
                Trace_import_departements::memorise_attribut('dp_erreurs', 'Le fichier est trop petit mais aucune indication comme dèja téléchargé');
                $resultat_import = false;
            }
        }
        else {
            // Taille supérieure à 1000.
            Importe_zip_dans_base($zip_file_name);
            Trace_import_departements::memorise_attribut('dp_nom_fichier_zip', $zip_file_name);
            $resultat_import = true;
        }
    }
    else {
        echo "Taille_fichier:$taille_fichier<br> pas d'importation!<br>";
        Trace_import_departements::memorise_attribut('dp_erreurs', "Pas de fichier téléchargé");
        $resultat_import = false;
    }
    return $resultat_import;
}

// Importe le fichier téléchargé dans la base de données monsite4G.
function Importe_zip_dans_base($zip_file_name)
{
    $dossier = "../../import_cartoradio";
    $dossier_decompression = "$dossier/temporaire";
    decompresse($zip_file_name,
                $dossier_decompression);
    // Les fichiers CSV sont disponibles, reste à les traiter et les importer dans la base
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
    //print_r($departement_a_importer); die();
    if ($departement_a_importer !== null)
    {
        echo "<hr>";
        echo "Département: ", $departement_a_importer['dp_numero'], 
             " id: ", $departement_a_importer['dp_id'];
        echo "<br>";
        $dp_id = $departement_a_importer['dp_id'];
        $e = new Exporte_departements();
        trace("Département à exporter: $dp_id")
        $e->exporte_departement($dp_id);
        $e->enregistre_demande_export_departement($dp_id);
    }
    else
    {
        echo "<h2>Fin de la demande d'Export des départements</h2>";
        // enchainer sur le transfert
        // de la base anfr
        // vers la base exploitation
    }
}

// Renvoie le prochain département importer de Cartoradio
function prochain_departement_a_exporter()
{
    echo "prochain_departement_a_importer<br>";
    $base = new Base_donnees('monsite4g_anfr');
    echo 'dbName:' .$base->dbName, '<br>';
    $sql = 'SELECT * 
            FROM departements
            WHERE dp_date_demande_export IS NULL 
            ORDER BY dp_numero 
            LIMIT 1';
    $departement = $base->execute_sql($sql);
    echo "Départemnt à exporter;";
    print_r($departement);
    if (isset($departement[0]))
        return $departement[0];
    else
        return null;
}

//--------------------------------------------------------------

// Entrée: 	Fichiers zip (1 par département) importés par lien mail de cartoradio.fr
//         	Stockés dans      : /monsite4g/donnees_cartoradio_fr/import_cartoradio
//          Décompression dans: /monsite4g/donnees_cartoradio_fr/import_cartoradio/temporaire
// Sortie: Tables supports et antennes de la base de données.


function importe_dans_base()
{
    echo "Importe les supports et antennes des départements<br>";
    $departement = importe_departement_dans_base();
    Trace_import_departements::memorise_attribut('dp_departement', $departement);
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
												`proprietaire`)
						VALUES";
    $nom_fichier_csv_supports = "Supports_Cartoradio.csv";
    importe_table($nom_fichier_csv_supports, $sql_insert_into);

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
											   )
					    VALUES";
    $nom_fichier_csv_antennes = "Antennes_Emetteurs_Bandes_Cartoradio.csv";
    importe_table($nom_fichier_csv_antennes, $sql_insert_into);
    Trace_import_departements::memorise_attribut('dp_date_import', date("Y-m-d H:i:s") );
    return $departement;
}

// Ajoute le département importé aux supports
function ajoute_departement_aux_supports()
{
    $base = new Base_donnees('monsite4g_anfr');
    // Pour les requêtes longues
    $r = $base->execute_sql("SELECT DISTINCT substr(code_postal,1,2) AS departement_importe,
                                        COUNT(*) as n   
                                 FROM supports
                                 WHERE departement IS NULL
                                 GROUP BY departement_importe
                                 ORDER BY n DESC;");
    $departement = $r[0]['departement_importe'];
    echo "<br>departement importé:$departement<br>";
    $base->execute_sql("UPDATE supports 
                               SET departement = substr(code_postal,1,2)
                             WHERE departement IS NULL;");

    // Département ayant le plus de supports
    //$departement = $r[0]['departement'];
    return $departement;
}


// Importe les donnéees d'origine ANFR dans une table
function importe_table($nom_fichier_csv, $sql_insert_into)
{
    $sql_debut = <<<DEBUT
/* Debut */
SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;

DEBUT;

    $sql_fin = <<<FIN
/* Fin */
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

FIN;

    // Transforme de CSV en SQL
    echo "Transforme: $nom_fichier_csv en INSERT SQL par tranche de VALUES<br>";
    transforme_csv_vers_inserts_sql
    (                               //  Occurences: contenu
                                    // ----------- --------------------------------------------------
        $sql_debut,                 //           1:Paramètres de la requête
        $sql_insert_into,           //       *Lots:Début de l'INSERT à inclure en début de chaque lot
        $nom_fichier_csv,           // *Lot*lignes:Nom fichier CSV à transformer en VALUES
        $sql_fin                   //           1:Fin de la requête à inclure
                                    // ----------- --------------------------------------------------
    );

}

// Transcode le fichier exporté par le site de l'ANFR
// Codage entrée:
//		- Code caratères : ANSI
//		- "Chaines entre doubles quotes"
// Codage sortie:
//		- Code caratères : UTF-8
//		- 'Chaines entre simples quotes'
//		-		Echapement des simples quotes (') par doublement ('')
// Entree: Fichier CSV
// Sortie: Fichier de Values SQL "('v1', 'v2', ... 'vn'), (...), ..."
//         Production d'un fichier car trop volumineux pour tenir en mémoire
function transforme_csv_vers_inserts_sql($sql_debut, $sql_insert_into, $nom_fichier_csv, $sql_fin)
{
    // Début transformation
    echo "<h2>Début transformation</h2>";

    // Ouvre les fichiers en entrée et sortie
    $csv = new Csv_reader('../../import_cartoradio/temporaire/');
    $csv->ouvre_en_lecture($nom_fichier_csv);
    // Saute la premiere ligne contennat les noms des colonnes
    $csv->lis_ligne($ligne);

    // Ouvre le fichier destiné à recevoir l'ordre SQL d'INSERT
    // Le fichier SQL commence par le même nom que le fichier CSV
    $nom_fichier_sql = "../../import_cartoradio/temporaire/{$nom_fichier_csv}.sql";
    // Le fichier sql contient la requête sql généré à partir du fichier CSV
    if (($fichier_sql = fopen($nom_fichier_sql, "w")) == FALSE)
        die("Erreur: Fichier $nom_fichier_sql Impossible à ouvrir ");
    echo "<br>Fichier SQL Cree: $nom_fichier_sql<br><br>";

    $numero_ligne_fichier_csv = 0;

    // 10 = test    10.000 = normal expoitation
    $nombre_values_dans_insert = 1000;

    // => 1.000.000 de lignes pour un département
    //    Le plus peuplé est la région parisienne avec 122.000 antennes
    $nombre_maximum_lots_importes = 1000;

    // Pour interrompre après les premiers lots pour les tests
    $numero_lot = 1;

    fwrite($fichier_sql, $sql_debut);
    $fin_csv = false;
    while(!$fin_csv)
    {
        // -- Début lot
        echo "<br>Importe lot numéro: $numero_lot <br>";
        // Colonnes dans lesquells les valeurs seront insérées
        $valeurs_SQL = $sql_insert_into;
        // 'INSERT INTO table (...) VALUES
        $premiere_ligne_du_lot = true;
        // Pour chaque ligne d'une tranche (lot) à mettre dans un INSERT
        for ($i = 1; $i <= $nombre_values_dans_insert; $i++) {
            // Tentative de Lecture de la prochaine ligne
            if ($csv->lis_ligne($ligne_csv))
            {
                // Ligne csv lue
                $numero_ligne_fichier_csv++;
                // Saute la première ligne CSV qui contient le nom des champs
                // Ligne de données CSV
                if ($premiere_ligne_du_lot) {
                    // Pas de séparateur car pas de ligne précédente
                    $valeurs_SQL .= "";
                    // Après la première, ce n'est plus la première
                    $premiere_ligne_du_lot = false;
                } else {
                    // Fin de la ligne précédente
                    $valeurs_SQL .= ",<br>";
                }
                //echo "<br>==>ligne_csv: $ligne_csv<br>";
                // Génère les valeurs  "(v1, v2, ... vn)"
                $valeurs_SQL .= '(';
                $nombre_valeurs = count($ligne_csv);
                for ($c = 0; $c < $nombre_valeurs; $c++) {
                    // Entre quotes
                    $v = protege_quotes($ligne_csv[$c]);
                    $valeurs_SQL .= "'" . $v . "'";
                    if ($c < $nombre_valeurs - 1) {
                        // Pas la dernière valeur: séparer de la suivante
                        $valeurs_SQL .= ',';
                    }
                }
                $valeurs_SQL .= ')';
                // Fin ligne CSV
                //echo "SQL: " ,$valeurs_SQL, '<br>';
                //if ($numero_ligne_fichier_csv >= 10) break;
            }
            else
            {
                // Fin du fichier CSV
                $fin_csv = true; // Provoquera la sortie du 'While des lots'
                break;           // Sortie du lot courant (avant le 'for')
            }
        }
        // -- Fin lot: Ecris dans fichier SQL
        // Fin de l'instruction 'INSERT INTO table VALUES (), () ....'
        $valeurs_SQL .= ";<br>";
        //echo "<br>Fin lot SQL: $valeurs_SQL <br>";
        fwrite($fichier_sql, $valeurs_SQL);

        if ($numero_lot>=$nombre_maximum_lots_importes) break;
        $numero_lot++;
    }
    // Fin de la transformation = Fin des lots
    // Terminer la la fin de la requête SQL
    fwrite($fichier_sql, $sql_fin);
    // Fermer les fichiers
    $csv->ferme();
    fclose($fichier_sql);
    echo "<h2>Fin transformation</h2><br>";

    // Exécute le SQL produit
    $base = new Base_donnees('monsite4g_anfr');
    // Execute le script d'importation d'un departement sur la base de données
    $base->load_tables($nom_fichier_sql);

    return true;
}

function protege_quotes($curlaine)
{
    $curlaine_protegee = str_replace("'", "''", $curlaine);
    return $curlaine_protegee;
}

// Exécute le script d'importation d'un département
function importe($sql)
{
    //echo "$sql",'<br>';
    $base = new Base_donnees('monsite4g_anfr');
    // Pour les requêtes longues
    $base->execute_sql("SET wait_timeout=5000;");
    $r = $base->execute_sql($sql);
    //echo $r, '<br>';
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
