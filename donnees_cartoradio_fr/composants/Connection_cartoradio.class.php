<?php
// Connexion au site "www.cartoradio.fr"
// La connexion est mémorisée dans le navigateur (cookies).

// Factorisé en une seule classe
// Connection rémanente à  Cartoradio.fr en début
class Connection_cartoradio
{
    public static
        $curl,          // Connecteur Curl
        $ckfile,        // Stocke le cookie de connection
        $id_connection; // Id renvoyé lors de la connection

    public static function connection()
    {
        echo "Connexion à cartoradio v01<br>";

        self::$ckfile = tempnam(
            "cookies",
            "CURL_Cookie_"
        );
        echo 'Fichier des Cookies: ', self::$ckfile, '<br>';
        //die();
        // L'initialisation du connecteur curl en php se fait 
        // en utilisant la fonction curl_init().
        // cette fonction retourne un connecteur curl.

        $url = 'https://cartoradio.fr/api/v1/utilisateurs/signin';
        self::$curl = curl_init($url);
        echo "curl_init($url)<br>";

        // Précise les Options avant de tenter la connection

        // Utiliser le protocole POST 
        self::option(
            'CURLOPT_POST',
            CURLOPT_POST,
            true
        );

        // Paramètres du header envoyés par la requête de connection.
        self::option(
            'CURLOPT_HTTPHEADER',
            CURLOPT_HTTPHEADER,
            [
                'Cache-Control: no-cache',
                'accept: application/json, text/plain, */*',
                'content-type: application/json',
                'origin: https://cartoradio.fr',
                'login:import_cartoradio@ballesta.fr',
                'pwd://11031049'
            ]
        );

        echo 'COOKIE: ', self::$ckfile, '<br>';
        // Cookie pour mémoriser la connection    
        self::option(
            'CURLOPT_COOKIEJAR',
            CURLOPT_COOKIEJAR,
            self::$ckfile
        );

        // Pour récupérer le contenu de la requête
        self::option(
            'CURLOPT_RETURNTRANSFER',
            CURLOPT_RETURNTRANSFER,
            true
        );

        // Connection à Cartoradio (enfin!)
        $reponse_json = self::execute();

        // Transforme la réponse Json vers un tableau associatif
        $reponse = json_decode($reponse_json, true);
        if (
            is_array($reponse)
            &&
            array_key_exists('id', $reponse)
        ) {
            // Mémorise id
            self::$id_connection = $reponse['id'];
            echo 'self::$id_connection: ', self::$id_connection, "<br>";
            echo "*** Connection 'cartoradio.fr' OK ***<br>";
            return true;
        } else {
            echo "*** Erreur de connection ***: $reponse_json <br>";
            return false;
        }
    }

    public static function option($nom, $code, $valeur)
    {
        echo "Option Curl: $nom=$valeur<br>";
        curl_setopt(
            self::$curl,
            $code,
            $valeur
        );
    }

    public static function execute()
    {
        // Cookies pour preuve de connexion (les 2 sont necessaires)
        self::option(
            'CURLOPT_COOKIEJAR',
            CURLOPT_COOKIEJAR,
            self::$ckfile
        );

        // Fichier dans lequel cURL va lire les cookies
        self::option(
            'CURLOPT_COOKIEFILE',
            CURLOPT_COOKIEFILE,
            self::$ckfile
        );

        // Renvoie le résultat au programme
        self::option(
            'CURLOPT_RETURNTRANSFER',
            CURLOPT_RETURNTRANSFER,
            true
        );
        self::option(
            'CURLOPT_VERBOSE',
            CURLOPT_VERBOSE,
            true
        );

        echo 'Execute Curl<br>';
        $resultat = curl_exec(self::$curl);
        if ($resultat === false) {
            echo 'Erreur Curl: ', curl_error(self::$curl), '<br>';
            die('Fin');
        }
        return $resultat;
    }

    public static function ferme()
    {
        curl_close(self::$curl);
        //unset(self::$curl);
    }
} // class

// Test
if (Connection_cartoradio::connection()) {
    echo "<hr>Connecté<hr>";
    $url = 'https://www.cartoradio.fr/api/v1/export';
    $query =
        'departement=' . '79'
        . '&' . 'idUtilisateur=10104';
} else
    echo "<hr>Non Connecté<hr>";
