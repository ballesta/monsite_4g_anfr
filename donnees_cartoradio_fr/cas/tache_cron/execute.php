<?php

// Execute une action:
// - Arrete si pas OK
// - Renvoie le resultat de l'action si OK
function execute($resultat_action,$message)
{
    trace("Execute: $message");
    if ($resultat_action === false)
        die("<h3>*** Echec 'Execute': $message ***</h3>");
    else
        return $resultat_action;
}