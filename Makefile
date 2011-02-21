all: report csv

report: run web/index.html gzips

run::
	@php run.php

web/index.html: index.php
	@php index.php > web/index.html

gzips::
	@./create_gzips.sh

csv::
	@php createCSV.php > web/csv/csv
	@gzip -9 -c web/csv/csv > web/csv/csv.gz
