#!/bin/zsh

mkdir -p web/images && 
for ((x=1; x<=475; x++)) { convert -gaussian-blur 10x10 images/`printf %03d $x`.png -quality 100 -define png:bit-depth=8 web/images/`printf %03d $x`_blur.png }
