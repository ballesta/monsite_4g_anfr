# Monsite4G : Chargement Données
Appel des scripts pour l'import des données de l'ANFR
## 1) monsite4g_anfr/donnees_cartoradio_fr/cas/ tache_cron/init_import.php
Demarre un cycle d'import complet
## 2) monsite4g_anfr/ donnees_cartoradio_fr/ cas/tache_cron/ cron_export_import_departement.php
Demande d'import d'un département à l'ANFR
Réception du mail de l'ANFR contenant le lien de téléchargement du departement n-1
Téléchargement des données ANFR du département
Import dans base de données
### 3) monsite4g_anfr/donnees_cartoradio_fr/cas/tache_cron/post_import.php
    Traitements de vérification des données importées
    Mise en form pour optimiser les requêtes
        Détection des antennes autorisées et non activées
        Mémorisation dans table supports_antennes
### 4) monsite4g_anfr/donnees_cartoradio_fr/cas/tache_cron/transfert_anfr_exploitation.php
    Transfert de la base Anfr vers la base de test-etude.monsite4g

### Bases de données
Bases de données utilisées pour l'import de Cartoradio:
    Voir site.sql

### Git
git remote -v
    origin  https://github.com/ballesta/monsite_4g_anfr.git

