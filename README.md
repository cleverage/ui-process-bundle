## CleverAge/ProcessUIBundle
Une simple interface utilisateur pour cleverage/processbundle

**Pré-requis**  
docker (validé sur la version: Docker version 19.03.12, build 48a66213fe)  
docker-compose (validé sur la version: docker-compose version 1.29.2, build 5becea4c)

**Stack technique**

- PHP 8 / Symfony 5
- Mysql 8
- Nginx 1.19.6

**Installation**  
Un fichier Makefile est disponible. Pour une primo installation du projet il faut exécuter les commandes suivantes
- `make env` afin de "désampler" le fichier .env.dist
- `make upd`  Pour démarrer les services
- `make dependencies`  Pour installer les dépendances php et js
  A partir de ce moment là vous pouvez ouvrir votre navigateur avec l'url http://127.0.0.1:8080

**Utilisation de xdebug 3.x**  
Voir les éléments en commentaire dans le fichier docker-compose.override.yml

**Commandes shell utiles**
- `make upd` pour lancer les services
- `make down` pour stopper les services
- `make php` pour se connecter sur le service php
- `make node` pour se connecter sur le service node
- `make yarn-watch` pour builder les dépendances front avec Webpack
- `make logs` pour afficher les logs des services
