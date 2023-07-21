#!/bin/bash

# ToDo: Need to add logging into CMDB Notes

CMTSERVER=`ifconfig  | grep 'inet addr:'| grep -v '127.0.0.1' | cut -d: -f2 | awk '{ print $1}'` 


# if [ "$(id -u)" = "0" ]; then
#   echo "For logging purposes, this script may not be run as user \"root\"." 1>&2
#   exit 1
# fi


# lets make sure the log file is in place
BASEDIR=$(dirname $0)
cd $BASEDIR


LOGFILE="$BASEDIR/update_cookbook_version.log"

if ! [ -f $LOGFILE ]
then
	echo "Log File at $LOGFILE missing. Attempting to create it..."
	touch $LOGFILE
	if ! [ -f $LOGFILE ]
	then
		echo "Error creating logfile. Exiting."
		exit 2
	else
		echo "Successfully created logfile."
	fi
	
fi


echo " "
echo " "
echo "This script will update the maximum version of a Chef cookbook allowed in the specified environment"
echo "Please pay careful attention to spelling and case."
echo ""
echo "Enter the Change Request (CR) number for this modification"
read CHANGEREQUEST
echo ""
echo "Enter the Environment name:"
read ENV
# check if ENV exists on the server. Returns 1 if it exists, an error message if it does not.
ENVEXISTS="`wget -qO- --timeout=3 --tries=2 --no-check-certificate https://$CMTSERVER/chef/checkEnvironmentExists/$ENV`"
if [ $ENVEXISTS = "1" ]
then
	# the ENV is real. Continue.
	echo " "
	echo "Environment \"$ENV\" found on the server."
else
	# doesn't exist. Try again!
	echo " "
	echo "The server returned an error - \"$ENVEXISTS\""
	echo " "
	exit 2
fi

echo " "
echo "Enter the Cookbook name:"
read COOKBOOK
# check if COOKBOOK exists on the server. 
COOKBOOKEXISTS="`wget -qO- --timeout=3 --tries=2 --no-check-certificate https://$CMTSERVER/chef/checkCookbookExists/$COOKBOOK`"

# get the current cookbook version if any
ENVCOOKBOOKVERSION="`wget -qO- --timeout=3 --tries=2 --no-check-certificate https://$CMTSERVER/chef/getEnvironmentCookbookVersion/$ENV/$COOKBOOK`"
echo " "

if [ $COOKBOOKEXISTS = "1" ]
then
	# the Cookbook is real. Continue.
	echo "Cookbook \"$COOKBOOK\" found on the server."
	if [ "$ENVCOOKBOOKVERSION" != "" ]
	then
		echo " "
		echo "The current version of the cookbook \"$COOKBOOK\" in the environment \"$ENV\" is: \"$ENVCOOKBOOKVERSION\""
		echo " "
	else
		echo "The cookbook \"$COOKBOOK\" does not currently have any version constraints in the environment \"$ENV\""
	fi
	
else
	# doesn't exist. Try again!
	echo "Error: The cookbook \"$COOKBOOK\" does not exist on the server or there was an error contacting the server"
	echo " "
	exit 2
fi

echo " "
echo "Enter the new Cookbook version:"
read VERSION

echo " "
echo "******************Warning!******************"
echo " "
echo "You are asking to update the installed version of the cookbook: \"$COOKBOOK\""
echo "in the environment: \"$ENV\""
echo "to the version: \"$VERSION\""
echo "from the version: \"$ENVCOOKBOOKVERSION\""
echo "This could possibly affect a large amount of servers so please be sure of what you are asking me to do!"
echo " "
echo "Select 'Yes' to continue or 'No' to cancel"
select yn in "Yes" "No"; do
    case $yn in
        Yes ) RETURN="`wget -qO- --timeout=3 --tries=2 --no-check-certificate https://$CMTSERVER/chef/editEnvironmentCookbookVersion/$ENV/$COOKBOOK/eq/$VERSION`"
   				DATE=$(date +"%m-%d-%Y %k:%M:%S")
        		if [[ "$RETURN" == *"name"* ]] 
        		then
        			echo "Version updated successfully."
        			
        			# log it
        			echo "$DATE - User \"$LOGNAME\" updated the cookbook \"$COOKBOOK\" in the environment \"$ENV\" to version \"$VERSION\"" >> $LOGFILE
        		else
        			echo "There was an error in updating the version."
        			
        			# log it
        			echo "$DATE - User \"$LOGNAME\" tried to update the cookbook \"$COOKBOOK\" in the environment \"$ENV\" to version \"$VERSION\" but something went wrong...\"$RETURN\"" >> $LOGFILE
        		fi
        		break;;
        No ) echo "Ok Bye Bye."; break;;
    esac
done

