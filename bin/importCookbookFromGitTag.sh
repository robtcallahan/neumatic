#!/bin/bash


COOKBOOK=$1

TAG=$2

GROUP=$3

CHEFSERVER=$4

cd /var/chef/$CHEFSERVER/cookbooks

rm -rf $COOKBOOK

git clone ssh://git@git.nexgen.neustar.biz:8022/$GROUP/$COOKBOOK.git

cd $COOKBOOK

git checkout $TAG

cd ..

sudo knife cookbook upload $COOKBOOK --freeze
