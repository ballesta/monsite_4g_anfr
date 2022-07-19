<?php

// Lis les mails en provenance de cartoradio.fr
// Chaque mail contient un lien vers les fichiers exportés de cartoradio.fr

class Email_reader {

	// imap server connection
	public $connexion_serveur_mail;

	// inbox storage and inbox message count
	public $inbox;
	public $msg_cnt;

	// eMail login credentials
	private $server = 'imap.1and1.fr';
	private $user   = 'import_cartoradio@ballesta.fr';
	private $pass   = '//11031049';
	//private $port   = 143; // adjust according to server settings
    private $port   = 993; // adjust according to server settings

	// connect to the server and get the inbox emails
	function __construct() {
        echo "Constructeur Email_reader<br>";
		$this->connect();
		$this->inbox();
	}

	// close the server connection
	function close() {
		$this->inbox   = [];
		$this->msg_cnt = 0;
		imap_close($this->connexion_serveur_mail);
	}

	// Open the server connection
	// the imap_open function parameters will need to be changed for the particular server
	// these are laid out to connect to a Dreamhost IMAP server
	function connect() {
        echo "connexion_serveur_mail<br>";
		$this->connexion_serveur_mail =
                imap_open(
                    '{' . "$this->server:$this->port/imap/ssl/novalidate-cert" . '}' . 'INBOX',
                    $this->user,
                    $this->pass)
		or die("Connexion impossible : " . imap_last_error());
	}

	// Move the message to a new folder
	function move($msg_index, $folder='Boîte de réception.Importes') {
		// move on server
		imap_mail_move($this->connexion_serveur_mail, $msg_index, $folder);
		imap_expunge($this->connexion_serveur_mail);

		// re-read the inbox
		$this->inbox();
	}

	// Get a specific message (1 = first email, 2 = second email, etc.)
	function get($msg_index=NULL) {
		if (count($this->inbox) <= 0) {
		    // No mails in inbox
			return array();
		}
		elseif (   ! is_null($msg_index) 
				&& isset($this->inbox[$msg_index])) 
		{
			// Mail of given index position
			return $this->inbox[$msg_index];
		}

		// First mail
		return $this->inbox[0];
	}

	// Read the inbox
	// Executed on beginning, right after connexion
	function inbox() 
	{
		$this->msg_cnt = imap_num_msg($this->connexion_serveur_mail);
		$in = array();
		for($i = 1; $i <= $this->msg_cnt; $i++) {
			$in[] = array(
				'index'     => $i,
				'header'    => imap_headerinfo($this->connexion_serveur_mail, $i),
				'body'      => imap_body($this->connexion_serveur_mail, $i),
				'structure' => imap_fetchstructure($this->connexion_serveur_mail, $i)
			);
		}
		$this->inbox = $in;
	}

	// Read the last (most recent) message of inbox
	function inbox_last_message() 
	{
		$last = imap_num_msg($this->connexion_serveur_mail);
		$last_message = array
						(
						'index'     => $last,
						'header'    => imap_headerinfo($this->connexion_serveur_mail, $last),
						'body'      => imap_body($this->connexion_serveur_mail, $last),
						'structure' => imap_fetchstructure($this->connexion_serveur_mail, $last)
						);
		return $last_message;
	}
	
	function delete_mail($index)
	{		
		imap_delete($this->connexion_serveur_mail,$index)
		or die("Suppression impossible : " . imap_last_error());
		
		imap_expunge($this->connexion_serveur_mail)
		or die("Suppression definitive impossible : " . imap_last_error());
		
		// re-read the inbox
		$this->inbox();
	}

	function delete_all_mails()
	{	
		foreach($this->inbox as $mail)
		{
			$index = $mail['index'];
			imap_delete($this->connexion_serveur_mail,$index)
			or die("Suppression impossible : " . imap_last_error());
		}
		imap_expunge($this->connexion_serveur_mail)
		or die("Suppression definitive impossible : " . imap_last_error());

		// Refresh the inbox
		$this->inbox();
	}
}
?>