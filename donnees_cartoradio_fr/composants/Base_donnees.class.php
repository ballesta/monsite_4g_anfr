<?php
// Composant: Connexion à la base
// Gère les bases:
// * monsite4g (base par defaut)
// * monsite4g_anfr (contient les supports et les antennes en cours d'importation)
// Sur les serveurs:
// * local pour les tests
// * exploitation sur 1&1
class Base_donnees
{
	public  $connecteur_bd;
	public  $nom_base;
	function __construct($nom_base = 'monsite4g')
	{
	    $this->nom_base = $nom_base;
		$this->connecteur_bd = $this->connecte_base();
		$this->execute_sql("SET NAMES utf8;");
	}

	// Connecte la base de données dont le nom est donné par le constructeur.
	// Renvoie l'objet base de données pour y accéder
	function connecte_base()
	{
	    // print_r($_SERVER); die();  ==>>  localhost
		if (!isset($_SERVER['SERVER_NAME']))
		{
			// Aucun serveur: exécution sous terminal en CLI
			$hostName = "127.0.0.1";
			$dbName = $this->nom_base;
			$dsn = "mysql:dbname=$dbName;host=$hostName";
			$user = '';
			$password = '';
		}
		else
		{
			// Test en local ou en production
			$nom_serveur = $_SERVER['SERVER_NAME'];
			if ($nom_serveur == 'localhost')
			{
				// Tests sur serveur local démarré par "php -S localhost:8000"
				$hostName = "127.0.0.1";
				$dbName = $this->nom_base;
				echo "Base Locale: $dbName <br>";
				$dsn = "mysql:dbname=$dbName;host=$hostName";
				$user = 'bernard';
				$password = '';
			}
			else
			{
				// Test sur serveur public
				switch ($this->nom_base)
				{
                case "monsite4g":
                        $hostName = "db508808486.db.1and1.com";
                        $dbName = "db508808486";
                        $dsn = "mysql:dbname=$dbName;host=$hostName";
                        $user = 'dbo508808486';
                        $password = '//RADIOradio//';
                        break;
                case "monsite4g_anfr":
                        $hostName = "db726712341.db.1and1.com";
                        $dbName = "db726712341";
                        $dsn = "mysql:dbname=$dbName;host=$hostName";
                        $user = 'dbo726712341';
                        $password = '//RADIOradio//anfr';
                        break;
                case "monsite4g_test_etude":
                        $hostName = "db690937562.db.1and1.com";
                        $dbName = "db690937562";
                        $dsn = "mysql:dbname=$dbName;host=$hostName";
                        $user = 'dbo690937562';
                        $password = '//monsite4g_v1//';
                        break;
                default:
                        die("Nom base: " . $this->nom_base . "Doit être monsite4g, monsite4g_anfr, ...");
			    }
			}
		}
		$this->hostName	= $hostName;
		$this->dbName	= $dbName  ;
		$this->dsn      = $dsn     ;
		$this->user     = $user    ;
		$this->password = $password;
		try
		{
			$base = new PDO($dsn, $user, $password);
			echo "Connexion bd: $dbName <br>";
			return $base;
		} catch (PDOException $e) {
			echo 'Echec connexion base de données : ' . $e->getMessage(), '<br>';
			echo $nom_serveur,'<br>';
			echo $dsn,'<br>';
			echo "User: ", $user,'<br>';
			echo "Password: ", $password,'<br>';
			die();
		}
	}

	function execute_sql($sql)
	{
	    //echo "execute_sql($sql) <br>";
	    // Change "monsite4g_anfr" par la base exploitation mémorisée dans "$this->dbName";
	    //$sql = $this->change_base_anfr($sql);
		$mots_instruction_sql = explode(" ", $sql);
		$instruction_sql = strtoupper($mots_instruction_sql[0]);
		//echo $instruction_sql, "--\n";
		switch ($instruction_sql)
		{
			case 'SELECT' : $r = $this->execute_sql_select($sql); break;
			case 'INSERT' : $r = $this->execute_sql_exec  ($sql); break;
			case 'UPDATE' : $r = $this->execute_sql_exec  ($sql); break;
			case 'DELETE' : $r = $this->execute_sql_exec  ($sql); break;
			default:
	                        //$debut_sql = substr($sql,0,1000);
				//echo "<br>Instruction non reconnue sql: $debut_sql <br>";
				$r =$this->execute_sql_exec($sql);
		}
	    //echo "<br><hr>";
		return $r;
	}

	// Change "monsite4g_anfr" par la base exploitation mémorisée dans "$this->dbName";
	function change_base_anfr($sql)
	{
        str_replace("monsite4g_anfr",$this->dbName,$sql);
        return $sql;
    }

	// Requête de modification (insert, update, ...) de la base de données
	function execute_sql_exec($sql)
	{
		try
		{
			$r = $this->connecteur_bd->exec($sql);
			echo "Sql: $sql <br>";
			echo "N: $r<br>";
			return $r;
		} catch (PDOException $e) {
			echo 'Echec requête: ' . $e->getMessage();
			echo "Sql: $sql <br>";
			die('Erreur SQL<br>');
		}
	}

	// Requête de lecture (pas de modification) de la base
	function execute_sql_select($sql)
	{
		try
		{
			$r =$this->connecteur_bd->query($sql, PDO::FETCH_ASSOC)
			                        ->fetchAll();
			return $r;
		} catch (PDOException $e) {
			echo 'Echec requête sélection base de données : ' . $e->getMessage();
			echo "Sql: $sql <br>";
			exit("<hr>***Erreur SQL***");
		}
	}

	// En local seulement
	function dump_tables($nom_tables)
	{
	    echo "Transfert tables: $nom_tables<br>";
	    $dossier = $_SERVER['DOCUMENT_ROOT'] . "/tmp/" ;
		$backup_file_name = $dossier . date("Y-m-d-H-i") . ".sql" ;
		$command = "mysqldump --opt -h {$this->hostName} "
				 . "                -u {$this->user} "
                 . "                -p{$this->password}"
				 . "          {$this->dbName} "
				 . "          $nom_tables "
                 . "> $backup_file_name"
                 ;
		echo $command, '<br>';
		$retour = system("$command", $resultat);
        if ( $retour=== false)
		    echo  "Erreur:", $resultat, "<br>" ;
        else
            echo  "OK:", $resultat, "<br>" ;
        //system("mv $backup_file_name ../../../donnees_cartoradio_fr/transfert/", $resultat);
		return $backup_file_name;
	}

	function rename_table($nom_actuel, $nouveau_nom)
    {
        $sql = "RENAME TABLE $nom_actuel TO $nouveau_nom";
        $this->execute_sql($sql);
    }

	// Load tables from dump
	function load_tables($dump_file_name)
    {
        $command =
              "mysql -h {$this->hostName} "
            . "      -u {$this->user} "
            . "      -p{$this->password}"
            . "      {$this->dbName} "
            . "       <  $dump_file_name";
        echo $command, '<hr>';
        system($command, $resultat);
        echo "Resultat: $resultat<br>" ;
    }
}
?>
