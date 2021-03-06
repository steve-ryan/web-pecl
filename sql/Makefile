# +----------------------------------------------------------------------+
# | The PECL website                                                     |
# +----------------------------------------------------------------------+
# | Copyright (c) 1999-2018 The PHP Group                                |
# +----------------------------------------------------------------------+
# | This source file is subject to version 3.01 of the PHP license,      |
# | that is bundled with this package in the file LICENSE, and is        |
# | available through the world-wide-web at the following url:           |
# | https://php.net/license/3_01.txt                                     |
# | If you did not receive a copy of the PHP license and are unable to   |
# | obtain it through the world-wide-web, please send a note to          |
# | license@php.net so we can mail you a copy immediately.               |
# +----------------------------------------------------------------------+
# | Authors: Stig S. Bakken <ssb@fast.no>                                |
# +----------------------------------------------------------------------+

TABLE_FILES=\
	aggregated_package_stats.sql \
	cvs_acl.sql \
	cvs_groups.sql \
	cvs_group_membership.sql \
	files.sql \
	karma.sql \
	package_acl.sql \
	maintains.sql \
	categories.sql \
	packages.sql \
	package_stats.sql \
	package_aliases.sql \
	releases.sql \
	deps.sql \
	notes.sql \
	users.sql
SQL_TYPE=mysql
SQL_USER=pear
SQL_PW=pear
SQL_MYSQLPW=-p$(SQL_PW)
SQL_DB=pear
SQL_COMMAND=mysql -u '$(SQL_USER)' $(SQL_MYSQLPW) '$(SQL_DB)' < $< > $@ 2>&1
SQL_PIPE_COMMAND=mysql -u '$(SQL_USER)' $(SQL_MYSQLPW) '$(SQL_DB)'
SQL_ADMIN_COMMAND=mysql -u root -p mysql
SQL_GRANT_FILE=

ECHO    = echo
TOUCH   = touch

MISC_FILES=$(SQL_GRANT_FILE)
SQL_FILES=$(TABLE_FILES) $(MISC_FILES)

TABLE_TARGETS=$(TABLE_FILES:.sql=.res)
MISC_TARGETS=$(MISC_FILES:.sql=.res)
SQL_TARGETS=$(TABLE_TARGETS) $(MISC_TARGETS)

CLEANFILES = *.res *.out *~

all: nothing

nothing:
	@$(ECHO)
	@$(ECHO) "   Make what?"
	@$(ECHO)
	@$(ECHO) "   'make create'   to set up database from scratch"
	@$(ECHO) "   'make destroy'  to destroy database"
	@$(ECHO)

create: createdb $(SQL_TARGETS) prime
	@echo "Looking for errors..."
	@if grep -s Error *.res; then \
	    echo "Fix these errors, run 'make destroy' and try again." ; \
	else \
	    echo "No errors found." ; \
	fi

createdb:
	@$(SQL_ADMIN_COMMAND) < createdb.sql

dropdb:
	@$(SQL_ADMIN_COMMAND) < dropdb.sql

destroy: dropdb clean
# Do "make drop" enough times to "solve" those who won't go away because
# of foreign keys to other tables that still exist.
#	$(MAKE) -s drop

drop: clean
	cat $(SQL_FILES) | ./create2drop -$(SQL_TYPE) | $(SQL_PIPE_COMMAND) > drop.out

clean:
	-test -z "$(CLEANFILES)" || rm -f $(CLEANFILES)

test:
	@echo targets: $(SQL_TARGETS)

%.res: %.sql
	@echo -n "processing $<..."
	@$(SQL_COMMAND)
	@echo

# Specify dependencies between tables (by use of foreign keys) as
# dependencies between their respective .res files here.  For example,
# if the table "foo" has foreign keys to the table "bar", add
# "foo.res: bar.res".

package_acl.res: packages.res users.res
maintains.res: packages.res users.res
packages.res: users.res categories.res
releases.res: packages.res
deps.res: packages.res
users.res:
notes.res: packages.res users.res
categories.res:
cvs_acl.res: users.res cvs_groups.res
cvs_groups.res:
cvs_group_membership.res: users.res cvs_groups.res
provides.res: releases.res
state_order.res:
