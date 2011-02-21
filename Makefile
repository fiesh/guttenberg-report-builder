all: web/index.html run

run::
	@php run.php

web/index.html: index.php
	@php index.php > web/index.html
