#!/bin/sh
#
# This script sets up a DokuWiki environment to run the plugin's tests on
# scrutinizer-ci.com.
# The plugin itself will be moved to its correct location within the DokiWiki
# hierarchy.
#
# @author Andreas Gohr <andi@splitbrain.org>
# @author Gerry Weißbach <gerry.w@gammaproduction.de>

# make sure this runs on travis only
if [ -z "$SCRUTINIZER" ] || [ -z "$CI" ]  ; then
    echo 'This script is only intended to run on scrutinizer-ci build servers'
    exit 0
fi

cd ../../../

# check if template or plugin
if [ -e 'plugin.info.txt' ]; then
    type='plugin'
    dir='plugins'
elif [ -e 'template.info.txt' ]; then
    type='template'
    dir='tpl'
else
    echo 'No plugin.info.txt or template.info.txt found!'
    exit 0
fi

# find out where this plugin belongs to
BASE=`awk '/^base/{print $2}' ${type}.info.txt`
if [ -z "$BASE" ]; then
    echo "This plugins misses a base entry in ${type}.info.txt"
    exit 0
fi

# move everything to the correct location
echo ">MOVING TO: lib/$dir/$BASE"
mkdir -p lib/${dir}/$BASE
mv * lib/${dir}/$BASE/ 2>/dev/null
mv .* lib/${dir}/$BASE/ 2>/dev/null

# checkout DokuWiki into current directory (no clone because dir isn't empty)
# the branch is specified in the $DOKUWIKI environment variable
DOKUWIKI=${DOKUWIKI:-master}
echo ">CLONING DOKUWIKI: $DOKUWIKI"
git init
git config --global user.email "tools@inetsoftware.de"
git config --global user.name "i-net /// software"
git pull --depth 1 https://github.com/splitbrain/dokuwiki.git $DOKUWIKI

# install additional requirements
REQUIRE="lib/${dir}/$BASE/requirements.txt"
if [ -f "$REQUIRE" ]; then
    grep -v '^#' "$REQUIRE" | \
    while read -r LINE
    do
        if [ ! -z "$LINE" ]; then
            echo ">REQUIREMENT: $LINE"
            git clone $LINE
        fi
    done
fi

# link dependencies
ln -s ../../../ dokuwiki-dependency

# change working directory
cd -

# we now have a full dokuwiki environment with our plugin installed
# scrutinizer can take over
exit 0