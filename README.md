# kal3000-gcal-import

Ein Wordpress-Plugin, das auf das Grüne Wordpress-Theme <a href="http://kre8tiv.de/urwahl3000/">Urwahl3000</a> aufsetzt und eine Integration beliebig vieler öffentlicher Google-Kalender ermöglicht. 

## Motivation

Für eine solche Integration gibt es eine Reihe von Motivatoren:

* Manche (viele?) Seitenadmins scheuen dem Umgang mit einem Blog- oder CMS-System. Die Terminpflege auszulagern erleichtert den Admins den Umgang mit dem Kalendersystem und senkt die Hemmschwelle. 
* (Öffentliche) Google-Kalender lassen sich auf einfache Weise auch per Smartphone administrieren. Dazu muss der Admin lediglich in GCal entsprechende Admin-Zugänge z.B. für den Ortssprecher oder den News-Redakteur vergeben. Ebenso lassen sich solche Kalender leicht von jedermann in den eigenen Kalender einbinden, um jederzeit die aktuelle Terminübersicht greifbar zu haben. Eine weiter führende Dokumentation findet sich <a href="https://www.gruene-freising.de/... ">hier</a>. 
* So schön Urwahl3000 ist - der auf wpCalendar basierende kal3000 Kalender unterstützt keine Serientermine. Mit diesem Plugin ist das kein Problem mehr, da es Serientermine im Google Kalender automatisch als Serie von Einzelterminen anlegt.

## Eigenschaften

* Administration in Wordpress über die Admin-Oberfläche.
* Einbinden beliebig vieler Google-Kalender.
* Zuordnung dieser Google-Kalender zu bereits angelegten Terminkategorien, beispielsweise je OV.
* Geocoding von Veranstaltungsorten, wie sie aus Google Kalender übernommen werden. Derart angelegte Termine werden auf der Übersichtskarte richtig angezeigt.


## Voraussetzungen / Installation

1. Um eine auf Urwahl3000 und Wordpress basierende KV- oder OV-Seite betreiben zu können, braucht man zunächst eine irgendwo gehostete aktuelle Wordpress-Umgebung. Dazu wird auf die Dokumentation von Urwahl3000 verwiesen.

2. Als nächstes holt man sich das Plugin unter https://www.gruene-freising.de/... (Attachment) und installiert es über die WP-Oberfläche wie gewohnt. (TODO: Klären, ob das Plugin evtl. in Urwahl300 eingebaut wird, ansonsten evtl. offizielles WP-Plugin). 

3. in WP legt man Terminkategorien an, z.B. eine pro OV und eine für den KV, plus weitere nach Bedarf. Das funktioniert am besten mit einer entsprechenden Seitenhierarchie wie auf https://www.gruene-freising.de/... . 

4. Im Admin-Teil des Plugins erscheinen die angelegten Terminkategorien. Jeder Kategorie weist man dann einen öffentlichen Google-Kalender in Form des "public ics"-Links zu, beispielsweise <a href="https://calendar.google.com/calendar/ical/gruene.freising%40gmail.com/public/basic.ics">https://calendar.google.com/calendar/ical/gruene.freising%40gmail.com/public/basic.ics</a>. 

5. Im Admin-Teil kann man auch das Geocoding aktivieren. Das offizielle <a href="https://developers.google.com/maps/documentation/geocoding/start">Google-API</a> erfordert einen API-Key, der bei intensiver Nutzung nicht kostenlos ist. Alternativ dazu kann man den inoffiziellen Weg wählen, der ohne die API auskommt. Auf die Google-Policy wird hingewiesen.

6. Im Admin-Teil kann man das Zeitintervall einstellen, mit dem die Kalender synchronisiert werden. Standardeinstellung ist 60 Minuten.

7. Speichern und fertig.

8. Um die Termine in WP anzuzeigen, gibt es zwei Wege: Das Termine-Widget in der rechten Spalte zeigt immer alle Termine an. Pro OV kann man beispielsweise eine Unterseite mit dem Titel "OV Termine" anlegen, in der folgender Shortcode steht: <code>[wpcalendar kat=TERMINKATEGORIE]</code>. Auf dieser Seite werden dann nur die Termine des dazugehörigen OV angezeigt.

Mit Aktivieren des Plugins beginnt das Plugin sofort mit der Synchronisation.



## Support

Support gibt es aktuell nur per E-Mail an <a href="mailto:hm@seneca.muc.de">Harald Milz</a>. (TODO: Obfuscate mail adsress)


## Internationalization

Since this plugin is only relevant for people using the Urwahl3000 theme, and this includes only members of Bündnis 90 / Die Grünen, the plugin will only be available in German. Should a demand for other languages arise, feel free to contact me - and offer your support :-) 















