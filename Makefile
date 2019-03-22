
NAME = kal3000-gcal-import
INSTALLDIR = /usr/share/wordpress/wp-content/plugins/$(NAME)
VERSION = 0.1.0

release: 
	cd ..
	zip -9 -r $(NAME)-$(VERSION).zip $(NAME)/*.php $(NAME)/*.txt

install: 
	mkdir -p $(INSTALLDIR)
	cp -va *.txt *.php $(INSTALLDIR)
	chown -R www-data:www-data $(INSTALLDIR)






