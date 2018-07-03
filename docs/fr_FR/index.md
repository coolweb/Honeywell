Description
===

Ce plugin permet la gestion de la station et des vannes connectées Evohome.

Configuration du plugin
===

Allez dans le menu *Plugins* puis *Gestion des plugins* et ensuite sélectionnez le plugin *Honeywell*.
Remplissez  les champs dans la partie configuration:
- Nom d'utilisateur: L'adresse email de votre compte Honeywell.
- Mot de passe: Le mot de passe de votre compte Honeywell.

> Une fois le nom d'utilisateur et le mot de passe introduit, cliquez sur le bouton *Sauvegarder*, ensuite vous pouvez cliquez sur le bouton *Découverte*.

La station et les vannes de votre  système Evohome sont maintenant créés dans Jeedom.

Equipements
===

2 types d'équipements sont créés, la station et les vannes.

Station
====

La station permet de gérer les actions rapides de votre système de chauffage comme le mode absent, le mode Eco.

Les commandes disponibles:

- **Absent**: permet de passer le système de chauffage en mode absent.
- **Automatique**: permet de passer le système de chauffage en mode automatique et donc suit le planning défini.
- **Chauffage Off**: permet de couper le système de chauffage.
- **Economique**: permet de passer le système de chauffage en mode économique.
- **Journée Off**: permet de passer le système de chauffage en mode journée sans travaill et bascule donc sur le planning associé.
- **Personnalisé**: permet de passer le système de chauffage sur le planning personnalisé.
- **Mode**: commande d'information permettant de récupérer le mode actuel du système de chauffage, les différentes valeurs sont:
    - Auto: mode *automatique*.
    - AutoWithEco: mode *économique*.
    - Away: mode *absent*.
    - DayOff: mode *journée Off*.
    - HeatingOff: mode *chauffage Off*.
    - Custom: mode *personnalisé*.



Vanne
====

Les commandes disponibles:
- **Monter température**: permet de monter la température de la consigne de 0,5C°.
- **Descendre température**: permet de descendre la température de la consigne de 0,5C°.
- **Changer température**: commande de type message permettant de changer la température de la consigne:
    - Mettre une température de consigne permanente:
        - message: valeur de la température.
    - La consigne soit suivre le planning:
        - messasage: scheduled
- **Température**: commande d'information retournant la température actuelle de la pièce où se situe la vanne.
- **Température programmée**: commande d'information retournant la température de la consigne.
- **Jusquà**: commande d'information retournant la date et heure si une température de consigne à été plannifiée jusqu'à une heure fixe, sinon retourne vide.
- **Mode**: commande d'information retournant mode de la vanne, les valeurs possibles sont:
    - **TemporaryOverride**: une température de consigne a été spécifiée jusqu'à une heure donnée.
    - **PermanentOverride**: une température de consigne a été donnée de façon permanente.
    - **Schedule**: la température de consigne suit le planning.