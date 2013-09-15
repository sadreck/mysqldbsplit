NAME

	MySQL Database Dump Splitter v0.0.1

AUTHOR

	Pavel Tsakalidis [ http://pavel.gr | p@vel.gr ]

USAGE

	--in [sql dump] --out [output folder] --force --postfix-time "d-m-Y" --postfix-name [some text] --ignore [ignore-table1,ignore-table2,...] --only [export-table1,export-table2,...] --list
	
	--in            MySQL dump file.
	--out           Output folder to store individual sql files.
	--force         If --out directory does not exist, create it.
	--postfix-time  PHP date() format string to be appended to table filename.
	--postfix-name  Any text you want to be appended to table filename.
	--ignore        Any tables you want to ignore, comma separated.
	--only          Specific tables you want to export. Overrides --ignore option.
	--list          Only list tables. Will ignore every option except --in.

EXAMPLES	

	php dbsplit.php --in /tmp/data.sql --out /tmp/tables --force --only table1,table2
	php dbsplit.php --in /tmp/data.sql --list
	php dbsplit.php --in /tmp/data.sql --out /tmp/tables --postfix-time "d-m-Y_H-i" --postfix-text myexporttext