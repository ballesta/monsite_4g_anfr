<?php
//
// Transfert des tables 'supports' et 'antennes'
// De la base locale vers la base de production
//
// Préparation du transfert dans ce dossier.
//	Dump des tables 'supports' et 'antennes'
//  Compression des tables dans supports.zip et antennes.zip
// Envoi dans www.monsite4g/donnees_cartoradio_fr/mise_en_production par FTP
// Sur site www.monsite4g par FTP
//	host: ballesta.fr
//  login as:  u46531268
//	password: //031049
//	 
//	Lancement du script de chargemnent sur monsite4g
// 	Décompression
//	Comptage des nombres de supports et d'antennes avant
//	suppression des lignes de antennes et supports
//	Chargement des tables supports et antennes
//	Comptage du nombre de supports et d'antennes après
//

require_once "../composants/Base_donnees.class.php";

programme();

function programme()
{
	// Pour les accents
	header( 'content-type: text/html; charset=utf-8' );

	// Transfert des tables 'supports' et 'antennes'
	//   de la base locale 
	//   vers la base de production
	$nom_backup_zip = transfert_tables();

	// Exécute le chargement des tables sur le site monsite4g.
	// Le fichier contenant les tables est maintenant sur le serveur,
	// reste à charger les tables sur la base de données du serveur.
	// Le chargement est précédé d'une sauvegardes de l"ensemble de la base.
	chargement_tables_sur_site($nom_backup_zip);
}

// Transfert des tables 'supports' et 'antennes'
// De la base locale vers la base de production
function transfert_tables()
{

	// Extrait les tables supports et antennes de la base monsite4g au format SQL.
	echo "Transfert des tables 'supports' et 'antennes'<br>";
	$nom_backup = dump_tables('supports antennes');

	// Compresse les tables extraites pour diminuer le temps de transfert 
	// vers le site par FTP.
	echo "Compresse les tables extraites<br>";
	$nom_backup_zip = compresse_dump_sql($nom_backup);
	echo $nom_backup_zip, "<br>";

	// Transfere le fichier compressé vers le site.
	echo "Transfert des tables 'supports' et 'antennes' compressées vers le site<br>";
	upload_ftp($nom_backup_zip);
	return $nom_backup_zip;
}

// Extrait les tables supports et antennes de la base monsite4g au format SQL.
function dump_tables($nom_tables)
{
	$bd = new Base_donnees();
	return $bd->dump_tables($nom_tables);
}

// Compresse les tables extraites 
// pour diminuer le temps de transfert vers le site par FTP.
function compresse_dump_sql($nom_backup)
{
	$nom_backup_zip = $nom_backup . '.zip';
	$zip = new ZipArchive();
	if ($zip->open($nom_backup_zip, ZipArchive::CREATE)!==TRUE) 
	{
		exit("Impossible d'ouvrir le fichier <$nom_backup_zip>\n");
	}
	else
	{
		$zip->addFile($nom_backup);
		$zip->close();	 
	}
	return $nom_backup_zip;
}

// Transfere le fichier compressé vers le site.
function upload_ftp($nom_backup_zip)
{
	$ftp = new FTP($dossier_destination='monsite4g/donnees_cartoradio_fr/transfert_base');
	$ftp->transfere_vers_site($nom_backup_zip);
	$ftp->ferme();
}

// Exécute le chargement des tables sur le site monsite4g.
function chargement_tables_sur_site($nom_backup_zip)
{
	// Déclenche le script distant par CURL
	
}
?>