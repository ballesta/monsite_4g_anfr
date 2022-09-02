<?php
echo
"<h1>Import des données de cartoradio.fr</h1>
        Etapes à dérouler manuelement pour les tests.
        <br>
        <br>
        Ces étapes sont exécutées automatiquement chaque semaine par le Cron (WebCron).<br>
        Groupe 'monsite4g'
        <br>
        <br>
        <br>
";

// Test en local ou en production: Changer l'url en conséquence
$nom_serveur = $_SERVER["SERVER_NAME"];
if ($nom_serveur == "localhost")
{
    $serveur = "http://localhost:8000/";
}
else
{
    $serveur = "http://anfr.monsite4g.fr/";
}

echo "<a target=_blank 
         href=\"{$serveur}donnees_cartoradio_fr/cas/tache_cron/init_import.php\">
        Init import
      </a><hr>";
echo "<a target=_blank
         href=\"{$serveur}donnees_cartoradio_fr/cas/tache_cron/cron_export_import_departement.php\">
        Export et Import des départements (un par un)
      </a><hr>";
echo "<a target=_blank
         href=\"{$serveur}donnees_cartoradio_fr/cas/tache_cron/post_import.php\">
        Optimisation base en fin d\"import, avant de transférer dans application 
      </a><hr>";
echo "<a target=_blank
         href=\"{$serveur}donnees_cartoradio_fr/cas/tache_cron/transfert_anfr_exploitation.php\">
        Transfert dans la base en exploitation (pre production)
      </a><hr>";
echo "<a target=_blank
         href=\"{$serveur}donnees_cartoradio_fr/cas/tache_cron/Verification_finale.php\">
        Vérification finale du nombre d\"antennes et supports importes
      </a><hr>";
echo "<br>
      <a target=_blank
         href=\"http://test-etude.monsite4g.fr\">
        Demo http://test-etude.monsite4g.fr    
      </a><hr>";
?>
