all: report csv

report: run gzips

run::
	@php run.php

gzips::
	@./create_gzips.sh

csv::
	@mkdir -p web/csv
	@php createCSV.php > web/csv/csv
	@gzip -9 -c web/csv/csv > web/csv/csv.gz

clean::
	@rm -f web/*.html web/*.html.gz web/csv/csv web/csv/csv.gz web/plagiate/*.png
