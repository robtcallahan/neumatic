-- phpMyAdmin SQL Dump
-- version 3.5.0-rc2
-- http://www.phpmyadmin.net
--
-- Host: chopcprvt2.nc.neustar.com
-- Generation Time: Jan 27, 2015 at 03:09 PM
-- Server version: 5.1.67
-- PHP Version: 5.3.3

SET SQL_MODE="NO_AUTO_VALUE_ON_ZERO";
SET time_zone = "+00:00";

--
-- Database: `neumatic`
--

-- --------------------------------------------------------

--
-- Table structure for table `audit`
--

DROP TABLE IF EXISTS `audit`;
CREATE TABLE IF NOT EXISTS `audit` (
  `id` int(5) NOT NULL AUTO_INCREMENT,
  `userId` int(5) NOT NULL,
  `userName` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `dateTime` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ipAddress` varchar(15) COLLATE utf8_unicode_ci NOT NULL,
  `method` varchar(5) COLLATE utf8_unicode_ci NOT NULL,
  `uri` varchar(60) COLLATE utf8_unicode_ci NOT NULL,
  `controller` varchar(16) COLLATE utf8_unicode_ci NOT NULL,
  `function` varchar(24) COLLATE utf8_unicode_ci NOT NULL,
  `parameters` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `descr` varchar(150) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `userId` (`userId`),
  KEY `INDEX` (`userName`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=28076 ;

-- --------------------------------------------------------

--
-- Table structure for table `blade`
--

DROP TABLE IF EXISTS `blade`;
CREATE TABLE IF NOT EXISTS `blade` (
  `id` int(5) NOT NULL AUTO_INCREMENT,
  `serverId` int(10) NOT NULL,
  `distSwitch` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `vlanName` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `vlanId` varchar(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  `chassisName` varchar(24) COLLATE utf8_unicode_ci NOT NULL,
  `chassisId` int(5) NOT NULL,
  `bladeName` varchar(24) COLLATE utf8_unicode_ci NOT NULL,
  `bladeId` int(5) NOT NULL,
  `bladeSlot` int(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `serverId_idx` (`serverId`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=171 ;

-- --------------------------------------------------------

--
-- Table structure for table `blade_archive`
--

DROP TABLE IF EXISTS `blade_archive`;
CREATE TABLE IF NOT EXISTS `blade_archive` (
  `id` int(5) NOT NULL AUTO_INCREMENT,
  `serverId` int(10) NOT NULL,
  `distSwitch` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `vlanName` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `vlanId` varchar(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  `chassisName` varchar(24) COLLATE utf8_unicode_ci NOT NULL,
  `chassisId` int(5) NOT NULL,
  `bladeName` varchar(24) COLLATE utf8_unicode_ci NOT NULL,
  `bladeId` int(5) NOT NULL,
  `bladeSlot` int(3) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `serverId_idx` (`serverId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `chef`
--

DROP TABLE IF EXISTS `chef`;
CREATE TABLE IF NOT EXISTS `chef` (
  `id` int(5) NOT NULL AUTO_INCREMENT,
  `serverId` int(5) NOT NULL,
  `server` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `role` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `environment` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `version` varchar(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `versionStatus` varchar(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `ohaiTime` timestamp NULL DEFAULT NULL,
  `ohaiTimeInt` decimal(10,0) DEFAULT NULL,
  `ohaiTimeDiff` decimal(12,2) DEFAULT NULL,
  `ohaiTimeDiffString` varchar(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `ohaiTimeStatus` varchar(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `cookStartTime` timestamp NULL DEFAULT NULL,
  `cookEndTime` timestamp NULL DEFAULT NULL,
  `cookTimeString` varchar(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `INDEX` (`serverId`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=3136 ;

-- --------------------------------------------------------

--
-- Table structure for table `console`
--

DROP TABLE IF EXISTS `console`;
CREATE TABLE IF NOT EXISTS `console` (
  `id` int(5) NOT NULL AUTO_INCREMENT,
  `serverId` int(10) NOT NULL,
  `consoleLog` longtext COLLATE utf8_unicode_ci,
  `consoleWatcherLog` text COLLATE utf8_unicode_ci,
  `consoleRunning` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `serverId_idx` (`serverId`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=6 ;

-- --------------------------------------------------------

--
-- Table structure for table `dist_switch`
--

DROP TABLE IF EXISTS `dist_switch`;
CREATE TABLE IF NOT EXISTS `dist_switch` (
  `id` int(5) NOT NULL AUTO_INCREMENT,
  `model` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `name` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `enabled` int(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `distSwitch` (`model`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=25 ;

-- --------------------------------------------------------

--
-- Table structure for table `hostgroup`
--

DROP TABLE IF EXISTS `hostgroup`;
CREATE TABLE IF NOT EXISTS `hostgroup` (
  `id` int(5) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `lease`
--

DROP TABLE IF EXISTS `lease`;
CREATE TABLE IF NOT EXISTS `lease` (
  `id` int(5) NOT NULL AUTO_INCREMENT,
  `serverId` int(5) NOT NULL,
  `leaseStart` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `leaseDuration` int(5) NOT NULL DEFAULT '30',
  `expired` tinyint(1) NOT NULL DEFAULT '0',
  `extensionInDays` int(2) NOT NULL DEFAULT '7',
  `numExtensionsAllowed` int(2) NOT NULL DEFAULT '2',
  `numTimesExtended` int(2) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `INDEX` (`serverId`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=904 ;

-- --------------------------------------------------------

--
-- Table structure for table `login`
--

DROP TABLE IF EXISTS `login`;
CREATE TABLE IF NOT EXISTS `login` (
  `id` int(5) NOT NULL AUTO_INCREMENT,
  `userId` int(5) NOT NULL,
  `numLogins` int(4) NOT NULL,
  `lastLogin` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `ipAddr` varchar(24) DEFAULT NULL,
  `userAgent` varchar(132) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `INDEX` (`userId`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=215 ;

-- --------------------------------------------------------

--
-- Table structure for table `node`
--

DROP TABLE IF EXISTS `node`;
CREATE TABLE IF NOT EXISTS `node` (
  `id` int(5) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=2921 ;

-- --------------------------------------------------------

--
-- Table structure for table `node_to_usergroup`
--

DROP TABLE IF EXISTS `node_to_usergroup`;
CREATE TABLE IF NOT EXISTS `node_to_usergroup` (
  `node_id` int(5) NOT NULL,
  `usergroup_id` varchar(50) COLLATE utf8_unicode_ci NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `quota`
--

DROP TABLE IF EXISTS `quota`;
CREATE TABLE IF NOT EXISTS `quota` (
  `id` int(5) NOT NULL AUTO_INCREMENT,
  `dcUid` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `ccrUid` varchar(20) COLLATE utf8_unicode_ci NOT NULL,
  `businessServiceName` varchar(40) COLLATE utf8_unicode_ci NOT NULL,
  `businessServiceId` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `cpus` int(11) NOT NULL,
  `memoryGB` int(11) NOT NULL,
  `storageGB` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=16 ;

-- --------------------------------------------------------

--
-- Table structure for table `rating`
--

DROP TABLE IF EXISTS `rating`;
CREATE TABLE IF NOT EXISTS `rating` (
  `id` int(5) NOT NULL AUTO_INCREMENT,
  `userId` int(5) NOT NULL,
  `rating` decimal(4,1) NOT NULL,
  `dateRated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `comments` varchar(250) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `INDEX` (`userId`)
) ENGINE=InnoDB  DEFAULT CHARSET=latin1 AUTO_INCREMENT=17 ;

-- --------------------------------------------------------

--
-- Table structure for table `server`
--

DROP TABLE IF EXISTS `server`;
CREATE TABLE IF NOT EXISTS `server` (
  `id` int(5) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `serverType` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'vmware',
  `sysId` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `businessServiceName` varchar(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  `businessServiceId` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `subsystemName` varchar(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  `subsystemId` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `cmdbEnvironment` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `description` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `location` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `network` varchar(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  `subnetMask` varchar(16) COLLATE utf8_unicode_ci NOT NULL,
  `gateway` varchar(16) COLLATE utf8_unicode_ci NOT NULL,
  `macAddress` varchar(17) COLLATE utf8_unicode_ci NOT NULL,
  `ipAddress` varchar(16) COLLATE utf8_unicode_ci NOT NULL,
  `cobblerServer` varchar(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  `cobblerDistro` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `cobblerKickstart` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `cobblerMetadata` varchar(255) COLLATE utf8_unicode_ci DEFAULT '''''',
  `remoteServer` int(1) NOT NULL DEFAULT '0',
  `chefServer` varchar(255) COLLATE utf8_unicode_ci NOT NULL,
  `chefRole` varchar(40) COLLATE utf8_unicode_ci NOT NULL,
  `chefEnv` varchar(40) COLLATE utf8_unicode_ci NOT NULL,
  `dateCreated` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `userCreated` varchar(16) COLLATE utf8_unicode_ci NOT NULL,
  `dateUpdated` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `userUpdated` varchar(16) COLLATE utf8_unicode_ci NOT NULL,
  `okToBuild` tinyint(1) NOT NULL DEFAULT '0',
  `status` varchar(32) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'New',
  `statusText` varchar(60) COLLATE utf8_unicode_ci DEFAULT NULL,
  `timeBuildStart` timestamp NULL DEFAULT NULL,
  `timeBuildEnd` timestamp NULL DEFAULT NULL,
  `dateBuilt` timestamp NULL DEFAULT '0000-00-00 00:00:00',
  `userBuilt` varchar(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `dateFirstCheckin` timestamp NULL DEFAULT '0000-00-00 00:00:00',
  `archived` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=2461 ;

-- --------------------------------------------------------

--
-- Table structure for table `server_archive`
--

DROP TABLE IF EXISTS `server_archive`;
CREATE TABLE IF NOT EXISTS `server_archive` (
  `id` int(5) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `serverType` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'vmware',
  `sysId` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `businessServiceName` varchar(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  `businessServiceId` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `subsystemName` varchar(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  `subsystemId` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `cmdbEnvironment` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `location` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `network` varchar(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  `subnetMask` varchar(16) COLLATE utf8_unicode_ci NOT NULL,
  `gateway` varchar(16) COLLATE utf8_unicode_ci NOT NULL,
  `macAddress` varchar(17) COLLATE utf8_unicode_ci NOT NULL,
  `ipAddress` varchar(16) COLLATE utf8_unicode_ci NOT NULL,
  `cobblerServer` varchar(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  `cobblerDistro` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `cobblerKickstart` varchar(100) COLLATE utf8_unicode_ci NOT NULL,
  `chefServer` varchar(40) COLLATE utf8_unicode_ci NOT NULL,
  `chefRole` varchar(40) COLLATE utf8_unicode_ci NOT NULL,
  `chefEnv` varchar(40) COLLATE utf8_unicode_ci NOT NULL,
  `dateCreated` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `userCreated` varchar(16) COLLATE utf8_unicode_ci NOT NULL,
  `dateUpdated` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `userUpdated` varchar(16) COLLATE utf8_unicode_ci NOT NULL,
  `okToBuild` tinyint(1) NOT NULL DEFAULT '0',
  `status` varchar(32) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'New',
  `statusText` varchar(60) COLLATE utf8_unicode_ci DEFAULT NULL,
  `timeBuildStart` timestamp NULL DEFAULT NULL,
  `timeBuildEnd` timestamp NULL DEFAULT NULL,
  `dateBuilt` timestamp NULL DEFAULT '0000-00-00 00:00:00',
  `userBuilt` varchar(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `dateFirstCheckin` timestamp NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `server_pool`
--

DROP TABLE IF EXISTS `server_pool`;
CREATE TABLE IF NOT EXISTS `server_pool` (
  `id` int(5) NOT NULL AUTO_INCREMENT,
  `serverId` int(5) DEFAULT NULL,
  `name` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  `ipAddress` varchar(15) COLLATE utf8_unicode_ci NOT NULL,
  `subnetMask` varchar(15) COLLATE utf8_unicode_ci NOT NULL,
  `gateway` varchar(15) COLLATE utf8_unicode_ci NOT NULL,
  `state` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'Free',
  `userId` int(5) DEFAULT NULL,
  `dateCheckedOut` datetime NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`),
  UNIQUE KEY `serverId` (`serverId`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=106 ;

-- --------------------------------------------------------

--
-- Table structure for table `standalone`
--

DROP TABLE IF EXISTS `standalone`;
CREATE TABLE IF NOT EXISTS `standalone` (
  `id` int(5) NOT NULL AUTO_INCREMENT,
  `serverId` int(10) NOT NULL,
  `iLo` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `iso` varchar(100) COLLATE utf8_unicode_ci DEFAULT NULL,
  `distSwitch` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `vlanName` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `vlanId` varchar(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `serverId_idx` (`serverId`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=15 ;

-- --------------------------------------------------------

--
-- Table structure for table `storage`
--

DROP TABLE IF EXISTS `storage`;
CREATE TABLE IF NOT EXISTS `storage` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `serverId` int(10) NOT NULL,
  `lunSizeGb` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `serverId_idx` (`serverId`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=4845 ;

-- --------------------------------------------------------

--
-- Table structure for table `storage_archive`
--

DROP TABLE IF EXISTS `storage_archive`;
CREATE TABLE IF NOT EXISTS `storage_archive` (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `serverId` int(10) NOT NULL,
  `lunSizeGb` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `serverId_idx` (`serverId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `team`
--

DROP TABLE IF EXISTS `team`;
CREATE TABLE IF NOT EXISTS `team` (
  `id` int(11) NOT NULL,
  `name` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `ownerId` int(11) NOT NULL,
  `dateCreated` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `userCreated` varchar(16) COLLATE utf8_unicode_ci NOT NULL,
  `dateUpdated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `userUpdated` varchar(16) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_name` (`name`),
  KEY `index_ownerId` (`ownerId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tmp`
--

DROP TABLE IF EXISTS `tmp`;
CREATE TABLE IF NOT EXISTS `tmp` (
  `id` int(5) NOT NULL DEFAULT '0',
  `timeBuildStart` timestamp NULL DEFAULT NULL,
  `userCreated` varchar(16) COLLATE utf8_unicode_ci NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user`
--

DROP TABLE IF EXISTS `user`;
CREATE TABLE IF NOT EXISTS `user` (
  `id` int(5) NOT NULL AUTO_INCREMENT,
  `firstName` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `lastName` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `username` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `empId` varchar(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `title` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `dept` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `office` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `email` varchar(64) COLLATE utf8_unicode_ci DEFAULT NULL,
  `officePhone` varchar(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `mobilePhone` varchar(16) COLLATE utf8_unicode_ci DEFAULT NULL,
  `userType` varchar(20) COLLATE utf8_unicode_ci NOT NULL DEFAULT 'User',
  `numServerBuilds` int(5) NOT NULL DEFAULT '0',
  `maxPoolServers` int(2) NOT NULL DEFAULT '3',
  `dateCreated` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `userCreated` varchar(16) COLLATE utf8_unicode_ci NOT NULL,
  `dateUpdated` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `userUpdated` varchar(16) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=232 ;

-- --------------------------------------------------------

--
-- Table structure for table `usergroup`
--

DROP TABLE IF EXISTS `usergroup`;
CREATE TABLE IF NOT EXISTS `usergroup` (
  `id` int(5) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=396 ;

-- --------------------------------------------------------

--
-- Table structure for table `user_team`
--

DROP TABLE IF EXISTS `user_team`;
CREATE TABLE IF NOT EXISTS `user_team` (
  `userId` int(11) NOT NULL,
  `teamId` int(11) NOT NULL,
  UNIQUE KEY `unique_userteam` (`userId`,`teamId`),
  KEY `index_userId` (`userId`),
  KEY `index_teamId` (`teamId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `vlan`
--

DROP TABLE IF EXISTS `vlan`;
CREATE TABLE IF NOT EXISTS `vlan` (
  `id` int(5) NOT NULL AUTO_INCREMENT,
  `distSwitchId` int(5) NOT NULL,
  `vlanId` int(5) NOT NULL,
  `name` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `network` varchar(18) COLLATE utf8_unicode_ci DEFAULT NULL,
  `netmask` varchar(15) COLLATE utf8_unicode_ci NOT NULL,
  `gateway` varchar(15) COLLATE utf8_unicode_ci NOT NULL,
  `enabled` int(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `distSwitch` (`distSwitchId`),
  KEY `vlanId` (`vlanId`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=195 ;

-- --------------------------------------------------------

--
-- Table structure for table `vlan_business_service`
--

DROP TABLE IF EXISTS `vlan_business_service`;
CREATE TABLE IF NOT EXISTS `vlan_business_service` (
  `id` int(5) NOT NULL AUTO_INCREMENT,
  `distSwitchId` int(5) NOT NULL,
  `vlanId` int(5) NOT NULL,
  `name` varchar(132) COLLATE utf8_unicode_ci NOT NULL,
  `sysId` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `environment` varchar(32) COLLATE utf8_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `vlanId_idx` (`vlanId`),
  KEY `distSwitchId_idx` (`distSwitchId`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=33 ;

-- --------------------------------------------------------

--
-- Table structure for table `vlan_dhcp_relay`
--

DROP TABLE IF EXISTS `vlan_dhcp_relay`;
CREATE TABLE IF NOT EXISTS `vlan_dhcp_relay` (
  `id` int(5) NOT NULL AUTO_INCREMENT,
  `vlanId` int(5) NOT NULL,
  `address` varchar(15) COLLATE utf8_unicode_ci NOT NULL,
  `enabled` int(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`),
  KEY `vlanId_idx` (`vlanId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;

-- --------------------------------------------------------

--
-- Table structure for table `vmware`
--

DROP TABLE IF EXISTS `vmware`;
CREATE TABLE IF NOT EXISTS `vmware` (
  `id` int(5) NOT NULL AUTO_INCREMENT,
  `serverId` int(10) NOT NULL,
  `vSphereSite` varchar(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  `vSphereServer` varchar(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  `dcName` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `dcUid` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `ccrName` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `ccrUid` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `rpUid` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `hsName` varchar(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  `instanceUuid` varchar(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  `vmSize` varchar(10) COLLATE utf8_unicode_ci NOT NULL,
  `numCPUs` int(11) NOT NULL,
  `memoryGB` int(11) NOT NULL,
  `vlanName` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `vlanId` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `serverId_idx` (`serverId`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=2283 ;

-- --------------------------------------------------------

--
-- Table structure for table `vmware_archive`
--

DROP TABLE IF EXISTS `vmware_archive`;
CREATE TABLE IF NOT EXISTS `vmware_archive` (
  `id` int(5) NOT NULL AUTO_INCREMENT,
  `serverId` int(10) NOT NULL,
  `vSphereSite` varchar(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  `vSphereServer` varchar(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  `dcName` varchar(32) COLLATE utf8_unicode_ci DEFAULT NULL,
  `dcUid` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `ccrName` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `ccrUid` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `rpUid` varchar(20) COLLATE utf8_unicode_ci DEFAULT NULL,
  `hsName` varchar(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  `instanceUuid` varchar(40) COLLATE utf8_unicode_ci DEFAULT NULL,
  `vmSize` varchar(10) COLLATE utf8_unicode_ci NOT NULL,
  `numCPUs` int(11) NOT NULL,
  `memoryGB` int(11) NOT NULL,
  `vlanName` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  `vlanId` varchar(32) COLLATE utf8_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  KEY `serverId_idx` (`serverId`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci AUTO_INCREMENT=1 ;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `audit`
--
ALTER TABLE `audit`
  ADD CONSTRAINT `audit_ibfk_1` FOREIGN KEY (`userId`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `blade`
--
ALTER TABLE `blade`
  ADD CONSTRAINT `blade_ibfk_2` FOREIGN KEY (`serverId`) REFERENCES `server` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `blade_archive`
--
ALTER TABLE `blade_archive`
  ADD CONSTRAINT `blade_archive_ibfk_2` FOREIGN KEY (`serverId`) REFERENCES `server_archive` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `chef`
--
ALTER TABLE `chef`
  ADD CONSTRAINT `chef_ibfk_1` FOREIGN KEY (`serverId`) REFERENCES `server` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `console`
--
ALTER TABLE `console`
  ADD CONSTRAINT `console_ibfk_2` FOREIGN KEY (`serverId`) REFERENCES `server` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `lease`
--
ALTER TABLE `lease`
  ADD CONSTRAINT `lease_ibfk_1` FOREIGN KEY (`serverId`) REFERENCES `server` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `login`
--
ALTER TABLE `login`
  ADD CONSTRAINT `login_ibfk_1` FOREIGN KEY (`userId`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `rating`
--
ALTER TABLE `rating`
  ADD CONSTRAINT `rating_ibfk_1` FOREIGN KEY (`userId`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `standalone`
--
ALTER TABLE `standalone`
  ADD CONSTRAINT `standalone_ibfk_1` FOREIGN KEY (`serverId`) REFERENCES `server` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `storage`
--
ALTER TABLE `storage`
  ADD CONSTRAINT `storage_ibfk_2` FOREIGN KEY (`serverId`) REFERENCES `server` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `storage_archive`
--
ALTER TABLE `storage_archive`
  ADD CONSTRAINT `storage_archive_ibfk_2` FOREIGN KEY (`serverId`) REFERENCES `server_archive` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `team`
--
ALTER TABLE `team`
  ADD CONSTRAINT `team_ibfk_1` FOREIGN KEY (`ownerId`) REFERENCES `user` (`id`);

--
-- Constraints for table `user_team`
--
ALTER TABLE `user_team`
  ADD CONSTRAINT `user_team_ibfk_2` FOREIGN KEY (`teamId`) REFERENCES `team` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `user_team_ibfk_1` FOREIGN KEY (`userId`) REFERENCES `user` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `vlan`
--
ALTER TABLE `vlan`
  ADD CONSTRAINT `vlan_ibfk_1` FOREIGN KEY (`distSwitchId`) REFERENCES `dist_switch` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `vlan_business_service`
--
ALTER TABLE `vlan_business_service`
  ADD CONSTRAINT `vlan_business_service_ibfk_1` FOREIGN KEY (`distSwitchId`) REFERENCES `dist_switch` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `vlan_business_service_ibfk1` FOREIGN KEY (`vlanId`) REFERENCES `vlan` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `vlan_dhcp_relay`
--
ALTER TABLE `vlan_dhcp_relay`
  ADD CONSTRAINT `vlan_dhcp_relay_ibfk1` FOREIGN KEY (`vlanId`) REFERENCES `vlan` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `vmware`
--
ALTER TABLE `vmware`
  ADD CONSTRAINT `vmware_ibfk_2` FOREIGN KEY (`serverId`) REFERENCES `server` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `vmware_archive`
--
ALTER TABLE `vmware_archive`
  ADD CONSTRAINT `vmware_archive_ibfk_2` FOREIGN KEY (`serverId`) REFERENCES `server_archive` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;
