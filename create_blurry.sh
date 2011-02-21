#!/bin/zsh

mkdir -p web/images && 
for ((x=1; x<=475; x++)) { convert -gaussian-blur 5x5 images/`printf %03d $x`.png -quality 0 web/images/`printf %03d $x`_blur.jpg }
