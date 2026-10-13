# PHP-website starten

Vereist: PHP 8.1 of nieuwer, met DOM en mbstring. De Markdown-parser is meegeleverd.
Tabler 1.4.0 wordt geladen via jsDelivr; voor de Tabler-opmaak is internet nodig.
Parsedown 1.7.4 is lokaal meegeleverd met twee nullable type-aanpassingen voor PHP 8.4+.

Start vanuit de projectmap:

```sh
php -S 127.0.0.1:8080 router.php
```

Open http://127.0.0.1:8080. README.md is de startpagina. Alle andere Markdown-bestanden
worden bij elke aanvraag automatisch gevonden en getoond onder Alle lesmaterialen.
Je hoeft nieuwe bestanden niet aan de README toe te voegen. Links in Markdown zijn
relatief aan het betreffende bestand; PDF- en Word-bestanden blijven beschikbaar.
HTML in Markdown wordt veilig als tekst behandeld; HTML-commentaren worden verwijderd.

## Hosting

Plaats het project op een PHP-webserver. Voor Apache moeten mod_rewrite en AllowOverride
aanstaan zodat .htaccess interne bestanden en ruwe Markdown blokkeert. Voor Nginx:
blokkeer verborgen paden, website/, tests/, router.php en directe aanvragen naar
md/json/txt/lock-bestanden. Geef uitsluitend index.php door aan PHP-FPM. Stel dezelfde
regels in voordat je de site publiek beschikbaar maakt; Markdown bevat verborgen notities.

## Git pull

Via Website bijwerken kun je git pull --ff-only uitvoeren. Lokaal werkt dit zonder
wachtwoord op het loopback-adres. Voor toegang vanaf andere computers stel je de
omgevingsvariabele BIOLOGIE_ADMIN_PASSWORD op de server in. Zodra een wachtwoord is
ingesteld, is dit ook lokaal verplicht. Gebruik HTTPS op een publieke server.

De webservergebruiker moet Git kunnen uitvoeren, schrijfrechten op de checkout hebben
en toegang hebben tot de ingestelde remote. Authenticatie van Git moet vooraf geregeld
zijn; de knop vraagt geen Git-wachtwoorden. Bestaande Git-hooks worden niet uitgevoerd.
Alleen fast-forward updates zijn toegestaan; conflicten worden niet automatisch opgelost.
De uitvoer verschijnt na de update. Nieuwe bestanden zijn daarna direct vindbaar.

Een update haalt ook PHP-code op: gebruik deze functie alleen met een vertrouwde remote.
Deze installatiehandleiding zelf wordt niet als lesmateriaal gepubliceerd.
