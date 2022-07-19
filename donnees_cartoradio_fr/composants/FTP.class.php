<?php
class FTP
{
	function __construct($dossier)
	{
		$ftp_server = "ballesta.fr";
		$ftp_user = "u46531268";
		$ftp_pass = "//031049";

		// Mise en place d'une connexion FTP
		$conn_id = ftp_connect($ftp_server) 
			  	   or 
				   die("Impossible de se connecter au serveur $ftp_server <br>"); 

		// Tentative d'identification 
		if (@ftp_login($conn_id, $ftp_user, $ftp_pass)) 
		{
			echo "Connecté en tant que $ftp_user@$ftp_server<br>";
			// Change le dossier en public_html
			ftp_chdir($conn_id, 'monsite4g/donnees_cartoradio_fr/transfert_base');
			// Affiche le dossier courant
			echo ftp_pwd($conn_id), "<br>" ; // 
			// Activation du mode passif
			ftp_pasv($conn_id, true);		
		} 
		else 
		{
			die("Connexion impossible en tant que $ftp_user<br>");
		}
		$this->conn_id = $conn_id;
		
		// Fermeture de la connexion FTP
		ftp_close($conn_id);
	}

	// Transfere le fichier compressé vers le site.
	function transfere_vers_site($nom_fichier)
	{
		// Charge un fichier
		if (ftp_put($this->conn_id, $nom_fichier, $nom_fichier, FTP_BINARY)) 
		{
			echo "Le fichier $nom_fichier a été chargé avec succès<br>";
		}
		else 
		{
			die("Il y a eu un problème lors du chargement du fichier $nom_fichier<br>");
		}
	}
	
	// Fermeture de la connexion FTP
	function ferme()
	{
		ftp_close($this->conn_id);
	}
}


?>