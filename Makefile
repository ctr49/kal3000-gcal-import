
NAME = kal3000-gcal-import
ICALPARSER = icalparser
INSTALLDIR = /usr/share/wordpress/wp-content/plugins/$(NAME)
VERSION = 0.1.0

release: 
	( cd .. ; zip -9 -r $(NAME)-$(VERSION).zip $(ICALPARSER)/readme.md $(ICALPARSER)/src/* $(ICALPARSER)/tools/* $(NAME)/*.php $(NAME)/*.txt)

install: 
	mkdir -p $(INSTALLDIR)
	cp -vu *.txt *.php $(INSTALLDIR)
	chown -R www-data:www-data $(INSTALLDIR)






