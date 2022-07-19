<?php
// Test tableaux associatifs 2D
// QUERY POUR TOUS LES OPÉRATEURS MOBILES ET RIEN D'AUTRE.
/*
 * ://www.cartoradio.fr/api/v1/export?
 * departement=79&
 *
 * categories[]=TEL&
 *
 * operateurs[]=6&
 * operateurs[]=23
 * &operateurs[]=137&
 * operateurs[]=240&
 *
 * operateurautre=false&
 *
 * technologies[]=2G&
 * technologies[]=3G&
 * technologies[]=4G&
 * technologies[]=5G&
 *
 * enservice=false&
 * stationsRadioelec=true&
 * objetsCom=true&
 * anciennete=720&
 * valeurLimiteMin=0&
 * valeurLimiteMax=87&
 *
 * idUtilisateur=10104
 *
 */


$data=
'departement=79&'        .
'categories[]=TEL&'      .
'operateurs[]=6&'        .
'operateurs[]=23&'       .
'operateurs[]=137&'      .
'operateurs[]=240&'      .
'operateurautre=false&'  .
'technologies[]=2G&'     .
'technologies[]=3G&'     .
'technologies[]=4G&'     .
'technologies[]=5G&'     .
'enservice=false&'       .
'stationsRadioelec=true&'.
'objetsCom=true&'        .
'anciennete=720&'        .
'valeurLimiteMin=0&'     .
'valeurLimiteMax=87&'    .
'idUtilisateur=10104'    ;

echo $data;
