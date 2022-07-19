<?php
/*
Traitements de vérification des données importées
    Mise en forme pour optimiser les requêtes
        Détection des antennes autorisées et non activées
        Mémorisation dans table supports_antennes
Traitement local à la base "monsite4g_anfr", pas de transfert dans la base opérationnelle;
*/

require_once "../../composants/Base_donnees.class.php";

class Post_traitement
{
    function __construct()
    {
        $this->base = new Base_donnees('monsite4g_anfr');
    }

    // Détecte les antennes non activées.
    // Pour toutes les antennes dont le proprietaire est 'Antenne autorisée mais non activée'
    //      Mettre activee = 0
    // Bricolage infame de l'ANFR pour éviter de créer une colonne spécialisée
    function detecte_antennes_non_activees()
    {
        echo "<h2>Détermine les exploitants des antennes non activées</h2>";
        $this->base->execute_sql
        (
            "UPDATE antennes 
                 SET activee = 0 
                 WHERE exploitant = 'Antenne autoris'
                    OR exploitant = 'Antenne autorisée mais non activée';
                "
        );
        return $this;
    }


    // Trouve les exploitants des antennes non activées par analyse de leur fréquence.
    // La table 'frequences' fournie par RD contient les fréquences allouées aux exploitants.
    function determine_exploitants_antennes_non_activees()
    {
        echo "<h2>Trouve les exploitants des antennes non activées</h2>";
        $this->base->execute_sql
        (
          "
            UPDATE antennes a
            SET a.exploitant = (SELECT f.exploitant
                                  FROM frequences f
                                 WHERE ROUND(a.debut) = f.debut
                                   AND ROUND(a.fin)   = f.fin
                               )
            WHERE a.activee = 0;
           "
        );
        return $this;
    }

    // Vide puis Remplis table supports_antennes par produit cartésien supports x exploitants.
    // Table utilisée pour connaitre les antennes d'un exploitant dans une emprise sur la carte.
    // Pour chaque emplacement (support) possède le nombre d'antennes activées et non activée de chaque exploitant.
    function remplis_table_support_antennes()
    {
        echo "<h2>Vide puis Remplis table supports_antennes</h2>";
        $this->base->execute_sql
        (
            " TRUNCATE TABLE supports_antennes;
                  INSERT INTO supports_antennes
                    (
                        `support_id`,
                        `exploitant`,
                        `longitude`,
                        `latitude`
                    )
                    SELECT s.`support_id`,
                           e.`exploitant`,
                           s.`longitude`,
                           s.`latitude`
                    FROM supports s,
                         exploitants e;"
        );
        // Vérifications dans table supports_antennes:
        //   >= 287.860 lignes dans supports_antennes
        //   nbr_antennes_activees      == 0
        //   nbr_antennes_non_activees  == 0

        return $this;
    }

    // Remplis table supports_antennes_activees
    function remplis_table_supports_antennes_activees()
    {
        echo "<h2>Detruit puis remplis table supports_antennes_activees</h2>";
        $this->base->execute_sql
        (
            "
            DROP TABLE IF EXISTS supports_antennes_activees;
            CREATE TABLE supports_antennes_activees
            AS
            SELECT s.`support_id`,
                   s.`exploitant`,
                   COUNT(a.activee) AS nbr_antennes_activees
            FROM supports_antennes s 
            JOIN antennes a
            ON s.support_id = a.support_id
            WHERE a.activee = 1 
              AND a.exploitant = s.exploitant   
            GROUP BY s.`support_id`,
                     s.`exploitant` 
            ORDER BY  s.`support_id`,
                      s.`exploitant`;       

             "
        );
        // ==>  73 484 lignes
        return $this;
}

    // Remplis table supports_antennes_activees
    function remplis_table_supports_antennes_non_activees()
    {
        echo "<h2>Detruit puis remplis table supports_antennes_non_activees</h2>";
        $this->base->execute_sql
        (
            "
            DROP TABLE IF EXISTS supports_antennes_non_activees;
            CREATE TABLE supports_antennes_non_activees
            AS
            SELECT s.`support_id`,
                   s.`exploitant`,
                   COUNT(a.activee) AS nbr_antennes_non_activees
            FROM supports_antennes s 
            JOIN antennes a
            ON s.support_id = a.support_id
            WHERE a.activee = 0  
              AND a.exploitant = s.exploitant     
            GROUP BY s.`support_id`,
                   s.`exploitant` 
            ORDER BY  s.`support_id`,
                   s.`exploitant`; 
             "
        );
        // ==>   6 286 lignes
        return $this;
    }

    // Transfère table supports_antennes_activees
    function transfert_table_supports_antennes_activees()
    {
        echo "<h2>Transfert table supports_antennes_activees</h2>";
        $this->base->execute_sql
        (
            "
            UPDATE supports_antennes sa
            JOIN supports_antennes_activees saa
            ON sa.support_id = saa.support_id
            AND sa.exploitant = saa.exploitant
            SET sa.nbr_antennes_activees = saa.nbr_antennes_activees;
             "
        );
        // ==>    lignes
        return $this;
    }

    // Transfère table supports_antennes_non_activees
    function transfert_table_supports_antennes_non_activees()
    {
        echo "<h2>Transfert table supports_antennes_non_activees</h2>";
        $this->base->execute_sql
        (
            "
            UPDATE supports_antennes sa
            JOIN supports_antennes_non_activees sana
            ON sa.support_id = sana.support_id
            AND sa.exploitant = sana.exploitant
            SET sa.nbr_antennes_non_activees = sana.nbr_antennes_non_activees;
             "
        );
        // ==>    lignes
        return $this;
    }

    // Précise  le département importé de type 97x dans les supports
    // Modif Base:
    //     ALTER TABLE supports MODIFY departement VARCHAR(5) NULL DEFAULT NULL;
    function ajoute_departement_97_aux_supports()
    {
        echo "<h2>Précise  le département importé de type 97x dans les supports</h2>";

        $this->base->execute_sql("
UPDATE supports 
   SET departement = substr(code_postal,1,3)
 WHERE departement = 97
   AND substr(code_postal,1,2) = '97';");

        return $this;
    }

    // Corse du Nord et Sud
    // 2A = Nord = 201*
    // 2B = Sud  = 202*
    // Modif Base:
    //     ALTER TABLE supports MODIFY departement VARCHAR(5) NULL DEFAULT NULL;
    function ajoute_departement_corse_aux_supports()
    {
        echo "<h2>Précise  le département importé de type 97x dans les supports</h2>";

        // 2A = Nord = 201*
        $this->base->execute_sql("
            UPDATE supports 
               SET departement = '2A'
             WHERE substr(code_postal,1,3) = '201';");

        // 2B = Sud  = 202*
        $this->base->execute_sql("
            UPDATE supports 
               SET departement = '2B'
             WHERE substr(code_postal,1,3) = '202';");

        return $this;
    }


    // Supprime département 00
    /// select a.* from supports s join  antennes a on s.support_id = a.support_id  where s.departement = 0;
    //+------------+------------+-------------------+--------------+---------------+----------------+-----------+--------------+--------+-------------+---------+-------+------+-------+---------------------+---------+
    //| antenne_id | support_id | numero_cartoradio | exploitant   | type_antenne  | numero_antenne | dimension | directivite  | azimut | hauteur_sol | systeme | debut | fin  | unite | ajout               | activee |
    //+------------+------------+-------------------+--------------+---------------+----------------+-----------+--------------+--------+-------------+---------+-------+------+-------+---------------------+---------+
    //|   11120511 |    1607940 |           1394111 | RESEAU PRIVE | Cierge/Perche |        4033984 |         1 | Non Directif |      0 |           3 | PMR     |   453 |  456 | MHz   | 2018-11-24 20:31:52 |       1 |
    //|   11120512 |    1607940 |           1394111 | RESEAU PRIVE | Cierge/Perche |        4033984 |         1 | Non Directif |      0 |           3 | PMR     |   463 |  466 | MHz   | 2018-11-24 20:31:52 |       1 |
    //+------------+------------+-------------------+--------------+---------------+----------------+-----------+--------------+--------+-------------+---------+-------+------+-------+---------------------+---------+
    function supprime_departement_00()
    {
        echo "<h2>Supprime département 00</h2>";

        $this->base->execute_sql(
            "
            delete supports, antennes
            from supports  join  antennes 
              on supports.support_id = antennes.support_id
            where supports.departement = 0;
            "
        );
        return $this;
    }

    function compte_supports_par_departements()
    {
        echo "<h2>Compte supports par départements</h2>";

        $this->base->execute_sql(
            "
            UPDATE departements d
                SET dp_nbr_supports = 
                (
                   SELECT count(departement)
                     FROM supports s 
                    WHERE s.departement = d.dp_numero 
                );
            "
        );

        return $this;
    }

    function compte_antennes_par_departements()
    {
        echo "<h2>Compte antennes par départements</h2>";

        $this->base->execute_sql
        (
             "
             UPDATE departements d
                SET dp_nbr_antennes = 
                (
                   SELECT count(*)
                     FROM supports s INNER JOIN  antennes a
                       ON s.support_id = a.support_id
                    WHERE s.departement = d.dp_numero 
                );
             "
        );

        return $this;
    }

}
echo "<h1>Optimisation après import ANFR</h1>";
$p = new Post_traitement();
$p
    ->detecte_antennes_non_activees()
    ->determine_exploitants_antennes_non_activees()
    ->remplis_table_support_antennes()
    ->remplis_table_supports_antennes_activees()
    ->remplis_table_supports_antennes_non_activees()
    ->transfert_table_supports_antennes_activees()
    ->transfert_table_supports_antennes_non_activees()
    ->supprime_departement_00()
    ->ajoute_departement_97_aux_supports()
    ->ajoute_departement_corse_aux_supports()
    ->compte_supports_par_departements()
    ->compte_antennes_par_departements()
;

