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

        self::$ckfile = tempnam("/tmp", "CURL_Cookie_");

        // L'initialisation du connecteur curl en php se fait 
        // en utilisant la fonction curl_init().
        // cette fonction retourne un connecteur curl.

        $url = 'https://cartoradio.fr/api/v1/utilisateurs/signin';
        self::$curl = curl_init($url);
        echo "curl_init($url)<br>";

        // Précise les Options avant de tenter la connection

        // Utiliser le protocole POST 
        curl_setopt(
            self::$curl,
            CURLOPT_POST,
            true
        );

        // Paramètres du header envoyés par la requête de connection.
        curl_setopt(
            self::$curl,
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

        // Cookie pour mémoriser la connection    
        curl_setopt(
            self::$curl,
            CURLOPT_COOKIEJAR,
            self::$ckfile
        );

        // Pour récupérer le contenu de la requête
        curl_setopt(
            self::$curl,
            CURLOPT_RETURNTRANSFER,
            true
        );

        // Connection à Cartoradio (enfin!)
        $reponse_json = curl_exec(self::$curl);

        // Transforme la réponse Json vers un tableau associatif
        $reponse = json_decode($reponse_json, true);
        if (
            is_array($reponse) &&
            array_key_exists('id', $reponse)
        ) {
            // Mémorise id
            self::$id_connection = $reponse['id'];
            echo 'self::$id_connection: ', self::$id_connection, "<br>";
            return true;
        } else {
            echo "*** Erreur de connection ***: $reponse_json <br>";
            return false;
        }
    }
} // class


/* 
// Test
if (Cartoradio::connection())
    echo "<hr>Connecté<hr>";
else
    echo "<hr>Non Connecté<hr>";
*/