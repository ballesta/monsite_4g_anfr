<?php

// Download file keeping the original name
class CurlDownloader
{
    private $remoteFileName = NULL;
    private $ch = NULL;
    private $headers = array();
    private $response = NULL;
    private $fp = NULL;
    private $debug = FALSE;
    private $fileSize = 0;

    // const DEFAULT_FNAME = 'remote.out';
	
	// Constructor
    public function __construct(
								$url 	// Url of file to download under same name
							   )
    {
        echo 'Contructeur: ', $url, '<br>';
        $this->init($url);
    }

	// Debug
    public function toggleDebug()
    {
        $this->debug = !$this->debug;
    }

	// Initialisation: called by constructor
    // Set callback functions
    public function init($url)
    {
        echo 'Init: ', $url, '<br>';
        if( !$url )
            throw new InvalidArgumentException("Need a URL");

        $this->ch = curl_init();
        curl_setopt($this->ch, CURLOPT_URL, $url);
        curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->ch, CURLOPT_HEADERFUNCTION,
					           array($this, 'headerCallback'));
        curl_setopt($this->ch, CURLOPT_WRITEFUNCTION,
                               array($this, 'bodyCallback'));
    }

	// Récupère le nom de fichier dans le header.
	// Ouvre le fichier dans $this->fp.
	
	// Le header est envoyé ligne par ligne:
	// 		HTTP/1.1 200 OK 
	// 		Date: Sun, 07 Sep 2014 11:29:03 GMT 
	// 		Server: Apache 
	// 		X-Powered-By: PHP/5.3.3 
	// 		Set-Cookie: symfony=kh035fbnnhg7jmsph38bnfr7n1; path=/ 
	// 		Expires: -1 
	// 		Cache-Control: private 
	// 		Pragma: private 
	// 	==>	Content-Disposition: attachment; filename="EXPORT_CARTORADIO_07-09-2014_13h28m11.zip" <== 
	// 		Vary: Accept-Encoding,User-Agent 
	// 		Connection: close 
	// 		Transfer-Encoding: chunked 
	// 		Content-Type: multipart/x-zip 
	// 		<ligne vide>
    public function headerCallback($ch, $string)
    {
		// Appelé pour chaque lignez du header.
		// Traite le header suivant:
		//  'Content-Disposition: attachment; filename="EXPORT_CARTORADIO_07-09-2014_13h28m11.zip"'
	    echo "headerCallback Header: $string <br>";
        $len = strlen($string);	
		// 
        if( !strstr($string, ':') )
        {
			// Différent de  "xxxxxxxxxx:yyyyyyyyyyyy"
            $this->response = trim($string);
			echo "HEADER VIDE/ $string <BR>";
            return $len;
        }
		// Format "Content-Disposition:yyyyyyyyyyyy"
        list($name, $value) = explode(':', $string, 2);
        if( strcasecmp($name, 'Content-Disposition') == 0 )
        {
		    // "attachment; filename="EXPORT_CARTORADIO_07-09-2014_13h28m11.zip"'
			//              ---------------------------------------------------
            echo "Fichier importé: $name<br>";
            $parts = explode(';', $value);
            if( count($parts) > 1 )
            {
                foreach($parts AS $crumb)
                {
                    if( strstr($crumb, '=') )
                    {
                        list($pname, $pval) = explode('=', $crumb);
                        $pname = trim($pname);
                        if( strcasecmp($pname, 'filename') == 0 )
                        {
                            // Using basename to prevent path injection
                            // in malicious headers.
                            $this->remoteFileName = "../../import_cartoradio/" . basename($this->unquote(trim($pval)));
							// Opens local destination file with same name as remote file
                            $this->fp = fopen($this->remoteFileName, 'wb');
                        }
                    }
                }
            }
        }
        $this->headers[$name] = trim($value);
        return $len;
    }
	
	// Appelé sur réception du contenu du fihier à télécharger
    public function bodyCallback($ch, $string)
    {
        if($this->fp )
		{
			// Fichier trnasmis dans le header
			$len = fwrite($this->fp, $string);
        	$this->fileSize += $len;
			return $len;
		}
        else
		{
			// Pas de fichier transmis: le lien est périmé (déjà utilisé).
			// Le header contenant le nom de fichier n'a pas été reçu par 
			$len =  0;
		}
		return $len;
	}

    public function download()
    {
        echo "Avant download<br>";
        var_dump($this->ch);
        $retval = curl_exec($this->ch);
        $redirectURL = curl_getinfo($this->ch,CURLINFO_EFFECTIVE_URL );
        echo "redirectURL: $redirectURL <br>";
        echo "Après download: $retval <br>";
        if( $this->debug )
            var_dump($this->headers);
        if ($this->fp != null)
			fclose($this->fp);
        curl_close($this->ch);
        return $this->fileSize;
    }

    public function getFileName() 
	{ 
		return $this->remoteFileName; 
	}

    private function unquote($string)
    {
        return str_replace(array("'", '"'), 
		                   '', 
						   $string);
    }
}

/*
$dl = new curlDownloader(
    'https://dl.example.org/torrent/cool-movie/4358-hash/download.torrent'
);
$size = $dl->download();
printf("Downloaded %u bytes to %s\n", $size, $dl->getFileName());
*/

?>