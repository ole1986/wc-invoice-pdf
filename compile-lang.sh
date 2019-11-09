#!/bin/bash

echo "Clearing previous compiled languages..."
rm -f lang/*.mo

echo

for f in lang/*.po
do
    echo "Compile language $f into ${f:0:-3}.mo"
    msgfmt -v $f -o "${f:0:-3}.mo" 2> /dev/null
done