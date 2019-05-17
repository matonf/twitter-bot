# twittter-bot
PoC bot : proof of concept pour un bot twitter de concours

 DOCUMENTATION
 https://developer.twitter.com/en/docs/tweets/data-dictionary/overview/intro-to-tweet-json

 INSTRUCTIONS
 remplir le fichier i-example.php et le renommer en i.php
 mettre en place une crontab : un déclenchement par heure du fichier b.php
 
 FONCTIONS DU BOT
 0-cherche #concours dans les tweets populaires français, retourne une sélection
 1-favorise le tweet retenu
 2-rt le tweet
 3-poste un commentaire avec des mentions d'utilisateurs inscrits au concours
 4-follow le compte organisateur
 5-follow les comptes associés dans le tweet (co-organisateurs)
