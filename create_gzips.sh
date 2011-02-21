#!/bin/sh

for a in `find web -name \*.html`; do gzip -9 -c $a > $a.gz ; done
for a in `find web -name \*.css`; do gzip -9 -c $a > $a.gz ; done
