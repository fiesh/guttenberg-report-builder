all: report csv

report: run web/index.html gzips

run::
	@php run.php

web/index.html: index.php
	@php index.php > web/index.html

gzips::
	@./create_gzips.sh

csv::
	@mkdir -p web/csv
	@php createCSV.php > web/csv/csv
	@gzip -9 -c web/csv/csv > web/csv/csv.gz

clean::
	@rm -f web/*.html web/*.html.gz web/csv/csv web/csv/csv.gz web/plagiate/*.png
