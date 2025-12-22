# Cartes

![Static Badge](https://img.shields.io/badge/Release-11.0-b7d7ee)
![Static Badge](https://img.shields.io/badge/License-AGPL_3.0-a5cc52)
![Static Badge](https://img.shields.io/badge/Dotclear-2.36-137bbb)

Ce plugin, basé sur l'API Google Maps, est destiné à ajouter des cartes personnalisées dans vos billets ou pages

Pour cela, le plugin crée un nouveau type d'entrée : les éléments de carte.

Ces éléments, tous éditables, peuvent être

- des points d'intérêts aux icônes personnalisables (svg, jpg ou png)
- des lignes, des polygones, des rectangles ou des cercles de couleur, opacité et épaisseur variées
- des fichier kml distants ou provenant de votre médiathèque
- des flux GeoRSS comme par exemple ceux de Flickr
- des itinéraires routiers d'un point à un autre

Il est ensuite possible d'associer une carte à un billet ou à une page, et d'y inclure un nombre quelconque de ces éléments. Il est également possible d'insérer une carte n'importe où dans le blog grâce à une balise de template.

Le plugin permet d'utiliser OpenStreetMaps comme fournisseur de cartes, ou de personnaliser le style des cartes en important des fichiers de configuration au format JSON.

Les recherches d'adresses bénéficient de l'auto-complétion et du géo-codage.

Le plugin nécessite une clé d'API pour le domaine où il est utilisé. Il est toutefois fourni avec une clé partagée pour les tests.