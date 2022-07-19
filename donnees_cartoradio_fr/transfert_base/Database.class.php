<?php
class Database
{

	function __construct()
	{
		$this->dbhost = 'localhost';
		$this->dbuser = 'root';
		$this->dbpass = '';
		$this->dbname = 'monsite4g';
	}

	function dump_tables($nom_tables)
	{

		$backup_file = $this->dbname . date("Y-m-d-H-i-s") . '.sql';
		$command = "mysqldump --opt -h {$this->dbhost} "
				 . "                -u {$this->dbuser} "
				 . "                {$this->dbname} "
				 . "                $nom_tables "
				 . " > $backup_file";
		echo $command, '<br>';
		$retour = system("$command", $resultat);
		return $backup_file;
	}	
}