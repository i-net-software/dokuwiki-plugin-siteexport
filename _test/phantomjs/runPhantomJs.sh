#!/bin/sh

RET=0;

for FILE in $(find . -name \"*.test.html\")
do

    phantomjs phantom-qunit.js file://$(pwd)/$FILE
    RET=$(($RET + $?))
done

exit $RET