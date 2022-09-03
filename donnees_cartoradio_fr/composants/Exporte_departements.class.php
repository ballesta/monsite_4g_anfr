<?php
//
// Composant: Demande d'export des supports et antennes des départements.
//
// Utilisations:
//	-Export de l'ensemble des départements pour avoir une copie de la base de l'ANFR
//		--Utilisé pour l'ouverture du service
//	-Export incrémental de quelques départements par jour au fil de l'eau
//		--Utilisé pour mettre à jour lensemble des départements sur un mois de façon furtive
//

class Exporte_departements
{
	var $base;

	var $ckfile;
	// Constructeur
	function __construct()
	{
		// Connexion à la base de données de l'ANFR dans laquelle sont stockés
		// les supports et antennes.
		$this->base = new Base_donnees('monsite4g_anfr');
	}

	// Initialise la table de suivi des export/import des département.
	// Raz les Supports et les Antennes.
	// Par le cron du serveur webcron.fr
	// job "2-import departement de Cartoradio.fr"
	// Parametres:
	// - $un_seul_departement = Numéro de departement à importer
	function initialise_export_import_tous_departements($departement = null)
	{
		if ($departement == null) {
			// Pas de département particulier demandé
			// Initialise tous les départemnts à non exportés.
			// Demande l'import de tous les départements
			$sql = "UPDATE departements
                    SET dp_date_demande_export 			= NULL,
                        dp_date_telechargement_fichiers = NULL,
                        dp_nom_fichier_zip 				= NULL,
                        dp_date_import 					= NULL";
			$this->base->execute_sql($sql);
			// Tous les départements seront importés
		} else {
			echo "Un seul département demandé : $departement <br>";
			// Initialise tous les départemnts à 'export demandé'
			$sql = "UPDATE departements
                    SET dp_date_demande_export 			= NOW(), 
                        dp_date_telechargement_fichiers = NULL,
                        dp_nom_fichier_zip 				= NULL,
                        dp_date_import 					= NULL;
                    ";
			$this->base->execute_sql($sql);

			// Initialise le départemnt dont l'export est demandé
			// à 'import pas encore demandé'
			$sql = "UPDATE departements
                    SET dp_date_demande_export = NULL 
                    WHERE dp_numero = '$departement';
                    ";
			//echo "sql: $sql <br>";
			$this->base->execute_sql($sql);
		}


		// Raz les Supports et les Antennes pour demarrer le cycle d'importation à zéro;
		$sql = "DELETE FROM antennes;
                DELETE FROM supports;";
		$this->base->execute_sql($sql);

		// Détruit les mails de demande d'import
		echo "<br>Détruit les mails de demande d'import<br>";
		$email_reader = new Email_reader();
		$email_reader->delete_all_mails();
	}

	// Vérifie l'export/import de tous les départements.
	// Appelé après la demande d'emport de chaque département
	// pour détecter la fin de la phase d'export et passer à la réception des fichiers zip.
	function nombre_departements_restants_a_exporter()
	{
		// Initialise tous les départemnts pour suivre l'évolution
		// des export/imports.
		$sql = "SELECT * 
				FROM departements
				WHERE dp_date_demande_export IS NULL;";
		$reste_a_traiter = $this->base->execute_sql($sql);
		$r =  count($reste_a_traiter);
		//echo "Reste à traiter $r <br>";
		return $r;
	}

	// Demande d'export d'un département à Cartoradio.
	function exporte_departement($dp_id)
	{
		$this->demande_export_departement($dp_id);
		$this->enregistre_demande_export_departement($dp_id);
	}

	// Enregistre la demande d'export du département dans la table 'departements'
	function enregistre_demande_export_departement($dp_id)
	{
		$sql = "UPDATE departements
			    SET dp_date_demande_export = NOW()
				WHERE DP_id = $dp_id";
		$this->base->execute_sql($sql);
	}

	// Enregistre la fin de l'import du département dans la table 'departements'
	function enregistre_fin_import_departement($dp_id)
	{
		$sql = "UPDATE departements
			    SET dp_date_import = NOW()
				WHERE DP_id = $dp_id";
		$this->base->execute_sql($sql);
	}

	// Demande l'export d'un département.
	// Simule une demande d'export manuel à partir de la page:
	// - http://www.cartoradio.fr/cartoradio/web/
	// - Avec sélection d'un département à exporter
	// Paramètre $departement_id = champ "dp_id" de la table des départements
	function demande_export_departement($departement_id)
	{
		echo date('e H:i:s'), '- ', 'Demande export departement: ', $departement_id, '<br>';

		Connection_cartoradio::connection();


		$url = 'https://www.cartoradio.fr/api/v1/export';

		// Département à exporter: pas le N° de département,
		// mais un identifiant proche correspondant à l'attribut dp_id
		// de la table "departement"
		$query =
			'departement=' . $departement_id . '&' .
			/*
			'categories[]=TEL&'      .
			'operateurs[]=6&'        .
			'operateurs[]=23&'       .
			'operateurs[]=137&'      .
			'operateurs[]=240&'      .
			'operateurautre=false&'  .
			'technologies[]=2G&'     .
			'technologies[]=3G&'     .
			'technologies[]=4G&'     .
			'technologies[]=5G&'     .
			'enservice=false&'       .
			'stationsRadioelec=true&' .
			'objetsCom=true&'        .
			'anciennete=720&'        .
			'valeurLimiteMin=0&'     .
			'valeurLimiteMax=87&'    .
			*/
			'idUtilisateur=10104';
		// todo Récupérer idUtilisateur de la connexion ?
		echo "Query: $query <br>";

		// Page à appeler
		Connection_cartoradio::option(
			'CURLOPT_URL',
			CURLOPT_URL,
			$url . '?' . $query
		);

		// Envoi la demande d'exportation d'un département
		$reponse = Connection_cartoradio::execute();
		print_r($reponse);
		die();
		if ($reponse) {
			// Réponse à la demande d'export
			$obj = json_decode($reponse);
			print_r($reponse);
			echo '--><br>';
			// Succes de la demande d'export?
			if ($obj->success) {
				echo "Succes de la demande d'export<br>";
				// Enregistre la demande d'export
				$sql_insert = "INSERT INTO  export (exp_id, exp_departement , exp_date         )"
					.             "VALUES (NULL ,  $departement_id    , CURRENT_TIMESTAMP)";
				$this->base->execute_sql($sql_insert);
				$export = true;
			} else {
				echo "*** Echec de la demande d'export ***<br>";
				$export = false;
			}
		} else {
			// Aucune réponse
			echo '*** Erreur demande export: ', $reponse, ' ***<br>';
			$export = false;
		}
		echo '<hr>';
		Connection_cartoradio::ferme();
		// Stoppe pour 2 secondes
		sleep(0);
		return $export;
	}
}
