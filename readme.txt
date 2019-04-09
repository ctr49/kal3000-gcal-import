=== Kal3000 Google Calender Importer ===
Contributors: hmilz
Tags: kal3000, urwahl3000, calendar
Donate link: https://www.paypal.me/HaraldMilz
Requires at least: 4.0
Tested up to: 4.9
Requires PHP: 7.2
Stable tag: 0.2.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0

Imports and Merges an Arbitrary Number of Public Google Calendars into Kal3000. 

== Beschreibung ==
Ein Wordpress-Plugin, das auf das Grüne Wordpress-Theme <a href="http://kre8tiv.de/urwahl3000/">Urwahl3000</a> aufsetzt und eine Integration beliebig vieler öffentlicher Google-Kalender ermöglicht. 

Das hier ist noch "work in progress", und es ist noch nicht produktiv benutzbar! Das Plugin könnte Dein Wordpress zerschießen, Deinen Kreis- oder Ortsverband versehentlich auflösen oder den Klimawandel beschleunigen! Aber für mich funktioniert es schon recht ordentlich. 

* Administration in Wordpress über die Admin-Oberfläche.
* Einbinden beliebig vieler Google-Kalender.
* Zuordnung dieser Google-Kalender zu bereits angelegten Terminkategorien, beispielsweise je OV.
* Geocoding von Veranstaltungsorten, wie sie aus Google Kalender übernommen werden. Derart angelegte Termine werden auf der Übersichtskarte richtig angezeigt.


== Installation ==

1. Um eine auf Urwahl3000 und Wordpress basierende KV- oder OV-Seite betreiben zu können, braucht man zunächst eine irgendwo gehostete aktuelle Wordpress-Umgebung. Dazu wird auf die Dokumentation von Urwahl3000 verwiesen.

2. Als nächstes holt man sich das Plugin unter <a href="http://www.seneca.muc.de/kal3000-gcal-import/">http://www.seneca.muc.de/kal3000-gcal-import/</a> und installiert es über die WP-Oberfläche wie gewohnt.  

Hinweis: kal3000-gcal-import nutzt für das Parsen von ICAL-Files und -Feeds das PHP-Modul icalparser (https://github.com/OzzyCzech/icalparser). Die Verwendung und die Einbindung in die Release-ZIP-Files erfolgt mit freundlicher Genehmigung des Autors Roman Ožana. 

== Konfiguration ==

1. in WP legt man Terminkategorien an, z.B. eine pro OV und eine für den KV, plus weitere nach Bedarf. Das funktioniert am besten mit einer entsprechenden Seitenhierarchie wie auf https://www.gruene-freising.de/... . 

2. Im Admin-Teil des Plugins unter "Einstellungen / GCal Importer" erscheinen die angelegten Terminkategorien. Jeder Kategorie weist man dann einen öffentlichen Google-Kalender in Form des "public ics"-Links zu, beispielsweise <a href="https://calendar.google.com/calendar/ical/gruene.freising%40gmail.com/public/basic.ics">https://calendar.google.com/calendar/ical/gruene.freising%40gmail.com/public/basic.ics</a>. 

3. Im Admin-Teil kann man das Zeitintervall einstellen, mit dem die Kalender synchronisiert werden. Standardeinstellung ist 60 Minuten. Bitte beachten, dass der Wordpress-Scheduler die Zeitintervalle nur ungefähr und abhängig von der Seitenaktivität einhält. 

4. Im Admin-Teil kann man das Geocoding aktivieren. Derzeit ist nur ein inoffizieller Weg über Google Maps verfügbar, den Google nicht gerne sieht. Das offizielle <a href="https://developers.google.com/maps/documentation/geocoding/start">Google-API</a> erfordert einen API-Key, der bei intensiver Nutzung nicht kostenlos ist. Auf die Google-Policy wird hingewiesen. Außerdem ist OpenStreetMap verfügbar, aber es kann nicht sehr gut mit den Lokationen aus Google Maps umgehen. Im Moment ist es benutzbar, funktioniert aber nicht zuverlässig. 

5. Speichern und fertig.

Unter "Debugging" finden sich zwei weitere Einstellungen:

1. zum einen kann man ein Debug-Logging aktivieren, mit dem das Plugin Einträge in ${APACHE_LOG_DIR}/error.log schreibt. NONE schreibt nichts, CRIT (critical) am wenigsten, INFO (alles) am meisten. 

2. Die zweite Einstellung löscht den Geocoding-Cache bei jedem Plugin-Neustart, um das Geocoding für jede Lokation neu zu erzwingen, beispielsweise wenn man die Geocoding-Methode geändert hat. 

== Benutzung ==

Um die Termine in WP anzuzeigen, gibt es zwei Wege: Das Termine-Widget in der rechten Spalte zeigt immer alle Termine an. Darüber hinaus kann man beispielsweise pro OV eine Unterseite mit dem Titel "OV Termine" anlegen, in der folgender Shortcode steht: <code>[wpcalendar kat=TERMINKATEGORIE]</code>. Auf dieser Seite werden dann nur die Termine des dazugehörigen OV angezeigt.

Mit "Aktivieren" beginnt das Plugin sofort mit der Synchronisation.


== Frequently Asked Questions ==

Keine bisher. 

== Changelog ==

= 0.3.1 =
* multi-level debugging
* OSM geocoding (unstable) 
* secret events handling
* using cURL for more stability in some places
* added geocoding cache reset on restart option 
* geoshow / geocity mapping

= 0.3 =
* new branch
* added OpenStreetMap geocoding

= 0.2 =
* First fully functioning release. 

= 0.1 =
* Initial release.

== Upgrade Notice ==
= 0.2 =
Upgrade notices describe the reason a user should upgrade

= 0.1 =
This version fixes a security related bug. Upgrade immediately.

