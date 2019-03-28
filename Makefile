
NAME = kal3000-gcal-import
ICALPARSER = icalparser
INSTALLDIR = /usr/share/wordpress/wp-content/plugins/$(NAME)
SSHACCOUNT = root@ubuntu1804
VERSION = 0.1.0


# Make sure we always ship the latest icalparser version
icalparser:
	if [ -d icalparser ] ; then \
		cd icalparser && git pull ; \
	else \
		git clone https://github.com/OzzyCzech/icalparser ; \
	fi 


release: icalparser 
	cd .. ; \
	rm -f  $(NAME)-$(VERSION).zip ; \
	zip -9 -r $(NAME)-$(VERSION).zip $(NAME)/$(ICALPARSER)/readme.md $(NAME)/$(ICALPARSER)/LICENSE $(NAME)/$(ICALPARSER)/src/* $(NAME)/$(ICALPARSER)/tools/* $(NAME)/*.php $(NAME)/readme.* $(NAME)/README.*


install: icalparser
	rsync --delete -C -av ./ $(SSHACCOUNT):$(INSTALLDIR)
	ssh $(SSHACCOUNT) chown -R www-data:www-data $(INSTALLDIR)


