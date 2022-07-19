<?php
/**
 * Created by PhpStorm.
 * User: bernard
 * Date: 21/02/19
 * Time: 10:46
 */

/*
 CREATE TABLE `departements` (
  `dp_id` int(11) NOT NULL,
  `importer` tinyint(1) NOT NULL DEFAULT '1',
  `dp_numero` varchar(3) NOT NULL,
  `dp_nom` varchar(40) NOT NULL,
  `dp_date_demande_export` datetime DEFAULT NULL,
  `dp_date_telechargement_fichiers` datetime DEFAULT NULL,
  `dp_nom_fichier_zip` varchar(100) DEFAULT NULL,
  `dp_date_import` datetime DEFAULT NULL,
  `dp_nbr_supports` int(11) NOT NULL DEFAULT '0',
  `dp_nbr_antennes` int(11) NOT NULL DEFAULT '0',
  `dp_erreurs` mediumtext COMMENT 'Erreurs à l''import du département',
  `pays_id` int(11) NOT NULL DEFAULT '1'
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8;
*/

// Trace les phases d'import des départements dans la table 'departements'
// Procède en deux temps:
// 1. Mémorise les valeurs pendant l'import
// 2. Met à jour le département en fin d'import
class Trace_import_departements
{
    static $attributs = [];

    // Mémorise les  valeur pour un département.
    // La mémorisation est necessaire car le département n'est connu que après téléchargement et traitement
    // du fichier compressé.
    public static function memorise_attribut(string $attribut, $valeur)
    {
        echo "Attribut: $attribut <br>";
        if (!isset(self::$attributs[$attribut]))
        {
            self::$attributs[$attribut] = $valeur;
        }
        else
        {
            self::$attributs[$attribut] .= $valeur;
        }
    }


    // Met à jour les  valeur pour l'import d'un département
    public static function maj_attributs()
    {

        $departement = self::$attributs['dp_numero'];
        // Construit l'instruction de mise à jour
        $sql_update = "UPDATE departements SET ";
        $s=" ";
        foreach (self::$attributs as $attribut => $valeur)
        {
            $sql_update .= "$s $attribut='$valeur'";
            $s=',';
        }
        $sql_update .= " WHERE departement = $departement;";

        // Exécute la mise à jour
        //echo $sql_update;
        $base = new Base_donnees('monsite4g_anfr');
        $r = $base->execute_sql($sql_update);
    }

}

/*
// Tests unitaires
Trace_import_departements::memorise_attribut('aaaa', 1111);
Trace_import_departements::memorise_attribut('bbbb', 2222);
Trace_import_departements::memorise_attribut('eeee', 'xxxx');
Trace_import_departements::memorise_attribut('eeee', 'yyyy');

Trace_import_departements::maj_attributs(78);
*/
