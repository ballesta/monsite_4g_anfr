<?php
include "Accede_site.class.php";

$s = new Accede_site();
//$s->accede_url("http://localhost/monsite4g/site/pages/accueil.php");

//$s->accede_url("http://bernard.ballesta.fr");
$s->accede_url("http://localhost/monsite4g/donnees_cartoradio_fr/composants/Accede_site.class.test_1.php");
echo "***********fin**************";
$s->ferme();

?>