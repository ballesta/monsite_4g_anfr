<?php
class Accede_site
{
	private $ch;

	function __construct()
	{
		// Création d'une nouvelle ressource cURL
		$this->ch = curl_init();
	}

	function accede_url($url)
	{
		// Configuration de l'URL à appeler
		curl_setopt($this->ch, CURLOPT_URL, $url);
		// Inclure le header dans la réponse (0=Yes)
		curl_setopt($this->ch, CURLOPT_HEADER, 0);
		// Envoi les résultats au navigateur (true = return, false = print)
		curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, false);
		// Suivre toutes les en-têtes "Location: " que le serveur envoie
		curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, true); 
		// Récupération de l'URL et affichage sur le naviguateur
		curl_exec($this->ch);
	}

	function ferme()
	{
		// Fermeture de la session cURL
		curl_close($this->ch);
	}
}

?>