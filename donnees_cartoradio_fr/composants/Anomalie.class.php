<?php
// Signale et garde trace les anomalies et erreurs de tout poils.
//	Donne le maximum d'information pour le développeur
//  Donne le minimum d'information pour les pirates des caraïbes
class Anomalie
{

	private $html; 	// true  = Sortie des erreurs en HTML
					// false = sortie des erreur sur la console du CLI en ascii
    private $test; 	// true = en test
					//		Donner le maximum d'information pour le développeur
					// false= en production
					//		Donner le minimum d'information pour les pirates des caraïbes

	function __construct()
	{
		if (!isset($_SERVER['SERVER_NAME']))
		{
			// echo 'Aucun serveur: exécution sous DOS en CLI<br>';
			$this->html = false;
		}
		else
		{
			// Sous Apache en local ou en production
			$this->html = true;
			$nom_serveur = $_SERVER['SERVER_NAME'];
			// echo $nom_serveur,'<br>';
			if ($nom_serveur == 'localhost')
			{
				// Tests sur serveur local
				$this->test = true;
			}
			else
			{
				// Exploitation sur serveur public
				$this->test = false;
			}
		}
        $this->fichier_log = fopen("log", "a");
	}

    function titre($message)
    {
        $this->display_line("$message");
    }

    function trace($message, $niveau = 1)
    {
        if ($niveau >0)
        {
            $this->display_line("-$message");
        }
    }


    function erreur_fatale($message)
	{
        $this->display_line("Erreur fatale: $message");
		if ($this->test)
		{
            echo "<hr>";
            echo "Trace des appels:<br>";
            debug_print_backtrace();
            echo "<hr>";
			die("Arrêt suite à erreur fatale");
		}
		else
		{
		    // Laisser continuer malgrès l'erreur fatale.
		}
	}


	private function display_line($message)
    {
        // Formater la date courante en 'AAAA-MM-JJ HH:MM'
        $objDateTime = new DateTime('NOW');
        $date = $objDateTime->format('Y-m-d H:i');
        $line = "$date: $message";
        fwrite($this->fichier_log, "$line\n");
        if ($this->html)
        {
            echo "$line<br/>";
        }
        else
        {
            echo "$line\n";
        }


    }

}

?>
