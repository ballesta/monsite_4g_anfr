<?php
/**
 * Created by PhpStorm.
 * User: bernard
 * Date: 23/11/18
 * Time: 19:18
 */

class Importe_departements
{
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
        importe_table('supports',
            $fichier_csv,
            $sql_insert_into);

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
        importe_table('antennes',
            $fichier_csv,
            $sql_insert_into);
        flush();
        return $departement;
    }

// Ajoute le département importé aux supports
    function ajoute_departement_aux_supports()
    {
        $base = new Base_donnees('monsite4g_anfr');
        // Pour les requêtes longues
        $r = $base->execute_sql("SELECT DISTINCT substr(code_postal,1,2) AS departement,
                                        COUNT(*) as n   
                                 FROM supports
                                 WHERE departement IS NULL
                                 GROUP BY departement
                                 ORDER BY n DESC;");
        $departement = $r[0]['departement'];
        echo "<br>departement importé:$departement<br>";
        $base->execute_sql("UPDATE supports 
                               SET departement = substr(code_postal,1,2)
                             WHERE departement IS NULL;");

        // Département ayant le plus de supports
        $departement = $r[0]['departement'];
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
        $sql_valeurs = transforme_csv_vers_inserts_sql("../../import_cartoradio/temporaire/$fichier_csv");
//----
        $sql_fin = <<<FIN
;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
FIN;

        $sql = $sql_debut . $sql_valeurs . $sql_fin;
        $sql = utf8_encode($sql);
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
        if (($fichier_csv = fopen($nom_fichier_csv, "r")) !== FALSE) {
            $valeurs_insert_sql = "";
            // Lis la prochaine ligne du fichier csv à importer dans un tableau de valeurs
            while (($ligne_csv = fgetcsv($fichier_csv, 1000, ";", '"')) !== FALSE) {
                $numero_ligne_fichier_csv++;
                // Saute les deux première lignes qui contienent le nom des champs
                if ($numero_ligne_fichier_csv > 2) {
                    $nombre_valeurs = count($ligne_csv);
                    $valeurs = '(';
                    // Pour chaque champ de la ligne csv
                    for ($c = 0; $c < $nombre_valeurs; $c++) {
                        // Entre quotes
                        $v = protege_quotes($ligne_csv[$c]);
                        $valeurs .= "'" . $v . "'";
                        if ($c < $nombre_valeurs - 1) {
                            // Pas la dernière valeur:séparer par une virgule
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
        $base = new Base_donnees('monsite4g_anfr');
        // Pour les requêtes longues
        $base->execute_sql("SET wait_timeout=5000;");
        $r = $base->execute_sql($sql);
        //echo $r, '\n';
        //print_r($base->connecteur_bd->errorInfo());
    }



}
