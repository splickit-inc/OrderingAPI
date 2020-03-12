/*
 Navicat MySQL Data Transfer

 Source Server         : localhost
 Source Server Version : 50137
 Source Host           : localhost
 Source Database       : smaw_prod

 Target Server Version : 50137
 File Encoding         : utf-8

 Date: 10/09/2013 14:31:26 PM
*/

SET NAMES utf8;
SET FOREIGN_KEY_CHECKS = 0;
SET GLOBAL sql_mode = '';

--
-- Table structure for table `User_Groups `
--

CREATE TABLE `User_Groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `description` varchar(255) NOT NULL,
  `promo_id` int(11) DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`id`),
  UNIQUE KEY `name` (`name`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1000 ;

--
-- Table structure for table `User_Group_Members`
--

CREATE TABLE `User_Group_Members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_group_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_group_id` (`user_group_id`,`user_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1000 ;

--
-- Table structure for table `Airports`
--

CREATE TABLE `Airports` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `display_name` varchar(255) NOT NULL,
  `code` varchar(5) NOT NULL,
  `address` varchar(255) NOT NULL,
  `city` varchar(255) NOT NULL,
  `state` varchar(2) NOT NULL,
  `zip` varchar(5) NOT NULL,
  `lat` float(10,6) NOT NULL,
  `lng` float(10,6) NOT NULL,
  `active` char(1) NOT NULL DEFAULT 'Y',
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`id`),
  KEY `zip` (`zip`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1001 ;

--
-- Dumping data for table `Airports`
--

INSERT INTO `Airports` VALUES(1000, 'Denver International Airport', 'Denver International Airport', 'DEN', '8500 Pena Blvd.', 'Denver', 'CO', '80249', 39.849850, -104.673721,'Y', '2013-11-08 10:07:58', '0000-00-00 00:00:00', 'N');

--
-- Table structure for table `Airport_Areas`
--

CREATE TABLE `Airport_Areas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `airport_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`id`),
  KEY `airport_id` (`airport_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1004 ;

--
-- Dumping data for table `Airport_Areas`
--

INSERT INTO `Airport_Areas` VALUES(1000, 1000, 'Terminal A', '2013-11-08 10:12:27', '0000-00-00 00:00:00', 'N');
INSERT INTO `Airport_Areas` VALUES(1001, 1000, 'Terminal B', '2013-11-08 10:12:27', '0000-00-00 00:00:00', 'N');
INSERT INTO `Airport_Areas` VALUES(1002, 1000, 'Terminal C', '2013-11-08 10:12:42', '0000-00-00 00:00:00', 'N');
INSERT INTO `Airport_Areas` VALUES(1003, 1000, 'Main Terminal', '2013-11-08 12:25:15', '0000-00-00 00:00:00', 'N');

--
-- Table structure for table `Airport_Areas_Merchants_Map`
--

CREATE TABLE `Airport_Areas_Merchants_Map` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `airport_area_id` int(11) NOT NULL,
  `merchant_id` int(11) NOT NULL,
  `location` varchar(255) NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`id`),
  UNIQUE KEY `merchant_id` (`merchant_id`),
  KEY `concourse_id` (`airport_area_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1000 ;

-- ----------------------------
--  Table structure for `Activity_History`
-- ----------------------------
DROP TABLE IF EXISTS `Activity_History`;
CREATE TABLE `Activity_History` (
  `activity_id` int(11) NOT NULL AUTO_INCREMENT,
  `activity` varchar(100) NOT NULL DEFAULT '',
  `doit_dt_tm` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `executed_dt_tm` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `stamp` varchar(255) NULL DEFAULT NULL,
  `locked` char(1) NOT NULL DEFAULT 'N',
  `info` varchar(510) NOT NULL DEFAULT '',
  `activity_text` text,
  `tries` int(2) NOT NULL DEFAULT '0',
  `repeat_interval` int(11) NOT NULL DEFAULT '0' COMMENT 'in seconds',
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`activity_id`),
  KEY `locked` (`locked`,`doit_dt_tm`)
) ENGINE=InnoDB AUTO_INCREMENT=21261 DEFAULT CHARSET=utf8 COMMENT='History of all activities that are scheduled';

-- ----------------------------
--  Table structure for `Adm_Flurry_Data`
-- ----------------------------
DROP TABLE IF EXISTS `Adm_Flurry_Data`;
CREATE TABLE `Adm_Flurry_Data` (
  `flurry_data_id` int(11) NOT NULL AUTO_INCREMENT,
  `flurry_id` int(11) DEFAULT NULL,
  `info_date` date NOT NULL,
  `active_users` int(11) NOT NULL,
  `new_users` int(11) NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`flurry_data_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `Adm_Flurry_Key`
-- ----------------------------
DROP TABLE IF EXISTS `Adm_Flurry_Key`;
CREATE TABLE `Adm_Flurry_Key` (
  `flurry_id` int(11) NOT NULL AUTO_INCREMENT,
  `skin_id` int(11) NOT NULL,
  `flurry_key` varchar(30) NOT NULL,
  `app_platform` varchar(10) NOT NULL,
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`flurry_id`),
  UNIQUE KEY `flurry_key` (`flurry_key`),
  KEY `skin_id` (`skin_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `Balance_Change`
-- ----------------------------
DROP TABLE IF EXISTS `Balance_Change`;
CREATE TABLE `Balance_Change` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `balance_before` decimal(10,2) DEFAULT NULL,
  `charge_amt` decimal(10,2) NOT NULL,
  `balance_after` decimal(10,2) DEFAULT NULL,
  `process` varchar(50) NOT NULL,
  `cc_processor` varchar(255) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `cc_transaction_id` varchar(30) DEFAULT NULL,
  `notes` varchar(255) DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `fk_BC_user_id` (`user_id`),
  KEY `order_id` (`order_id`)
-- ,CONSTRAINT `fk_BC_user_id` FOREIGN KEY (`user_id`) REFERENCES `User` (`user_id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=MyISAM AUTO_INCREMENT=24085 DEFAULT CHARSET=utf8 COMMENT='To track any change to the balance of a user';

-- ----------------------------
--  Table structure for `Brand2`
-- ----------------------------
DROP TABLE IF EXISTS `Brand2`;
CREATE TABLE `Brand2` (
  `brand_id` int(11) NOT NULL AUTO_INCREMENT,
  `brand_name` varchar(255) NOT NULL,
  `active` char(1) NOT NULL DEFAULT 'Y',
  `allows_tipping` enum('Y','N') NOT NULL DEFAULT 'N',	
  `allows_in_store_payments` enum('Y','N') NOT NULL DEFAULT 'N',
  `brand_external_identifier` varchar(255) DEFAULT NULL,
  `cc_processor_username` varchar(30) NOT NULL DEFAULT 'splickitapiuser',
  `cc_processor_password` varchar(20) NOT NULL DEFAULT 'Spl2ck2ty',
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  `loyalty` enum('Y','N') NOT NULL DEFAULT 'N',
  PRIMARY KEY (`brand_id`),
  KEY `brand_external_identifier` (`brand_external_identifier`)
) ENGINE=InnoDB AUTO_INCREMENT=646 DEFAULT CHARSET=utf8;

INSERT INTO `Brand2` VALUES(100, 'System Default', 'Y', 'N', 'N', NULL, 'splickitapiuser', 'Spl2ck2ty', '2011-12-11 18:04:19', '2011-12-11 18:01:39', 'N', 'N');
INSERT INTO `Brand2` VALUES(400, 'Yum Ticket', 'Y', 'N', 'N', NULL, 'splickitapiuser', 'Spl2ck2ty', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N', 'Y');

-- ----------------------------
--  Table structure for `Brand_Loyalty_Fails`
-- ----------------------------
DROP TABLE IF EXISTS `Brand_Loyalty_Fails`;
CREATE TABLE `Brand_Loyalty_Fails` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `device_id` varchar(255) NOT NULL COMMENT 'could we also put user id in here',
  `brand_id` int(11) NOT NULL,
  `failed_attempts` int(11) NOT NULL DEFAULT '0',
  `unlock_at_ts` int(11) DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`id`),
  UNIQUE KEY `device_id_2` (`device_id`,`brand_id`),
  KEY `device_id` (`device_id`),
  KEY `brand_id` (`brand_id`),
  KEY `unlock_at_ts` (`unlock_at_ts`)
) ENGINE=InnoDB AUTO_INCREMENT=201 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `Brand_Loyalty_Rules`
-- ----------------------------
DROP TABLE IF EXISTS `Brand_Loyalty_Rules`;
CREATE TABLE `Brand_Loyalty_Rules` (
  `brand_loyalty_rules_id` int(11) NOT NULL AUTO_INCREMENT,
  `brand_id` int(11) NOT NULL,
  `max_points_per_order` int(11) NOT NULL DEFAULT '88888',
  `use_cheapest` char(1) NOT NULL DEFAULT 'Y',
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`brand_loyalty_rules_id`),
  UNIQUE KEY `brand_id` (`brand_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1004 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `Brand_MU_Map`
-- ----------------------------
DROP TABLE IF EXISTS `Brand_MU_Map`;
CREATE TABLE `Brand_MU_Map` (
  `map_id` int(11) NOT NULL AUTO_INCREMENT,
  `brand_id` int(11) NOT NULL,
  `merchant_user_id` int(11) NOT NULL,
  PRIMARY KEY (`map_id`),
  KEY `brand_id` (`brand_id`),
  CONSTRAINT `fk_Brd_brand_id` FOREIGN KEY (`brand_id`) REFERENCES `Brand` (`brand_id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=157 DEFAULT CHARSET=utf8 COMMENT='To maintain list of Merchant Users associated Brands';

-- ----------------------------
--  Table structure for `Brand_Points`
-- ----------------------------
DROP TABLE IF EXISTS `Brand_Points`;
CREATE TABLE `Brand_Points` (
  `brand_points_id` int(11) NOT NULL AUTO_INCREMENT,
  `brand_id` int(11) NOT NULL,
  `points` int(11) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`brand_points_id`),
  KEY `brand_id` (`brand_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1004 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `Brand_Points_Object_Map`
-- ----------------------------
DROP TABLE IF EXISTS `Brand_Points_Object_Map`;
CREATE TABLE `Brand_Points_Object_Map` (
  `map_id` int(11) NOT NULL AUTO_INCREMENT,
  `brand_points_id` int(11) NOT NULL,
  `object_type` varchar(255) NOT NULL,
  `object_id` int(11) NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`map_id`),
  KEY `brand_point_id` (`brand_points_id`,`object_type`,`object_id`)
) ENGINE=InnoDB AUTO_INCREMENT=109 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `Document`
-- ----------------------------
DROP TABLE IF EXISTS `Document`;
CREATE TABLE `Document` (
  `id` int(25) NOT NULL AUTO_INCREMENT,
  `file_type` varchar(50) NOT NULL DEFAULT '',
  `process_type` varchar(250) NOT NULL DEFAULT '',
  `file_name` varchar(250) NOT NULL DEFAULT '',
  `file_size` int(11) NOT NULL,
  `file_content` longblob NOT NULL,
  `file_extension` varchar(10) NOT NULL DEFAULT '',
  `stamp` varchar(50) DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2546 DEFAULT CHARSET=latin1;

-- ----------------------------
--  Table structure for `Dummy_User_Test`
-- ----------------------------
DROP TABLE IF EXISTS `Dummy_User_Test`;
CREATE TABLE `Dummy_User_Test` (
  `object_id` int(11) NOT NULL,
  `uuid` varchar(40) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `Errors`
-- ----------------------------
DROP TABLE IF EXISTS `Errors`;
CREATE TABLE `Errors` (
  `error_id` int(11) NOT NULL AUTO_INCREMENT,
  `message` text NOT NULL,
  `info` varchar(255) DEFAULT NULL,
  `custom01` text,
  `custom02` varchar(255) DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`error_id`)
) ENGINE=MyISAM AUTO_INCREMENT=3084305 DEFAULT CHARSET=utf8 COMMENT='Debugging activity log';

-- ----------------------------
--  Table structure for `Favorite`
-- ----------------------------
DROP TABLE IF EXISTS `Favorite`;
CREATE TABLE `Favorite` (
  `favorite_id` int(11) NOT NULL AUTO_INCREMENT,
  `favorite_name` varchar(30) NOT NULL,
  `merchant_id` int(11) NOT NULL DEFAULT '0',
  `user_id` int(11) NOT NULL DEFAULT '0',
  `favorite_json` text NOT NULL,
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`favorite_id`),
  KEY `merchant_id` (`merchant_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `Favorite_ibfk_1` FOREIGN KEY (`merchant_id`) REFERENCES `Merchant` (`merchant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `Favorite_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `User` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=12813 DEFAULT CHARSET=utf8 COMMENT='Stores Favorites';

-- ----------------------------
--  Table structure for `Favorite_Order_Detail`
-- ----------------------------
DROP TABLE IF EXISTS `Favorite_Order_Detail`;
CREATE TABLE `Favorite_Order_Detail` (
  `favorite_id` int(11) NOT NULL DEFAULT '0',
  `favorite_detail_id` int(11) NOT NULL AUTO_INCREMENT,
  `item_size_id` int(11) NOT NULL DEFAULT '0',
  `external_id` varchar(50) DEFAULT NULL,
  `name` varchar(50) DEFAULT NULL,
  `note` varchar(250) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT '0',
  `created` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`favorite_detail_id`),
  KEY `favorite_id` (`favorite_id`),
  KEY `item_size_id` (`item_size_id`),
  CONSTRAINT `fk_favorite_id` FOREIGN KEY (`favorite_id`) REFERENCES `Favorite_Orders` (`favorite_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_favorite_item_size_id` FOREIGN KEY (`item_size_id`) REFERENCES `Item_Size_Map` (`item_size_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='History of all placed order details';

-- ----------------------------
--  Table structure for `Favorite_Order_Detail_Modifier`
-- ----------------------------
DROP TABLE IF EXISTS `Favorite_Order_Detail_Modifier`;
CREATE TABLE `Favorite_Order_Detail_Modifier` (
  `favorite_detail_id` int(11) NOT NULL DEFAULT '0',
  `favorite_detail_mod_id` int(11) NOT NULL AUTO_INCREMENT,
  `modifier_size_id` int(11) NOT NULL DEFAULT '0',
  `external_id` varchar(50) DEFAULT NULL,
  `mod_quantity` int(11) NOT NULL DEFAULT '0',
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`favorite_detail_mod_id`),
  KEY `modifier_size_id` (`modifier_size_id`),
  KEY `favorite_detail_id` (`favorite_detail_id`),
  CONSTRAINT `fk_favorite_detail_id` FOREIGN KEY (`favorite_detail_id`) REFERENCES `Favorite_Order_Detail` (`favorite_detail_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_favorite_modifier_size_id` FOREIGN KEY (`modifier_size_id`) REFERENCES `Modifier_Size_Map` (`modifier_size_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='History of all favorite order detail modifiers';

-- ----------------------------
--  Table structure for `Favorite_Orders`
-- ----------------------------
DROP TABLE IF EXISTS `Favorite_Orders`;
CREATE TABLE `Favorite_Orders` (
  `favorite_id` int(11) NOT NULL AUTO_INCREMENT,
  `favorite_name` varchar(30) NOT NULL,
  `order_id` int(11) NOT NULL,
  `merchant_id` int(11) NOT NULL DEFAULT '0',
  `user_id` int(11) NOT NULL DEFAULT '0',
  `tip_amt` decimal(10,2) NOT NULL DEFAULT '0.00',
  `note` varchar(250) DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`favorite_id`),
  KEY `merchant_id` (`merchant_id`),
  KEY `user_id` (`user_id`),
  CONSTRAINT `fk_favorite_merchant_id` FOREIGN KEY (`merchant_id`) REFERENCES `Merchant` (`merchant_id`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_favorite_user_id` FOREIGN KEY (`user_id`) REFERENCES `User` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='Stores Favorites';

-- ----------------------------
--  Table structure for `Gift`
-- ----------------------------
DROP TABLE IF EXISTS `Gift`;
CREATE TABLE `Gift` (
  `gift_id` int(11) NOT NULL AUTO_INCREMENT,
  `gift_token` varchar(255) NOT NULL,
  `gifter_user_id` int(11) NOT NULL,
  `receiver_email` varchar(255) NOT NULL,
  `receiver_user_id` int(11) NOT NULL,
  `amt` decimal(10,2) NOT NULL COMMENT 'the max amount the can be charged',
  `used_amt` decimal(10,2) NOT NULL,
  `expires_on` date NOT NULL COMMENT 'last day this gift is valid',
  `used_on` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `order_id` int(11) DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`gift_id`),
  UNIQUE KEY `uuid` (`gift_token`),
  KEY `gifter_user_id` (`gifter_user_id`,`receiver_user_id`),
  KEY `order_id` (`order_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5918 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `Group`
-- ----------------------------
DROP TABLE IF EXISTS `Group`;
CREATE TABLE `Group` (
  `group_id` int(11) NOT NULL AUTO_INCREMENT,
  `group_name` varchar(100) NOT NULL,
  `group_type` varchar(50) NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`group_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `Group_Order`
-- ----------------------------
DROP TABLE IF EXISTS `Group_Order`;
CREATE TABLE `Group_Order` (
  `group_order_id` int(11) NOT NULL AUTO_INCREMENT,
  `group_order_token` varchar(20) NOT NULL,
  `admin_user_id` int(11) NOT NULL,
  `merchant_id` int(11) NOT NULL,
  `sent_ts` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`group_order_id`),
  UNIQUE KEY `token` (`group_order_token`),
  KEY `admin_id` (`admin_user_id`,`merchant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4480 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `Group_Order_Detail`
-- ----------------------------
DROP TABLE IF EXISTS `Group_Order_Detail`;
CREATE TABLE `Group_Order_Detail` (
  `group_order_detail_id` int(11) NOT NULL AUTO_INCREMENT,
  `group_order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `order_json` text NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`group_order_detail_id`),
  KEY `group_order_id` (`group_order_id`,`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=21251 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `Holiday`
-- ----------------------------
DROP TABLE IF EXISTS `Holiday`;
CREATE TABLE `Holiday` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `merchant_id` int(11) NOT NULL,
  `newyearsday` char(1) NOT NULL DEFAULT 'C',
  `easter` char(1) NOT NULL DEFAULT 'C',
  `fourthofjuly` char(1) NOT NULL DEFAULT 'C',
  `thanksgiving` char(1) NOT NULL DEFAULT 'C',
  `christmas` char(1) NOT NULL DEFAULT 'C',
  PRIMARY KEY (`id`),
  UNIQUE KEY `merchant_id` (`merchant_id`),
  CONSTRAINT `FK_Holiday_1` FOREIGN KEY (`merchant_id`) REFERENCES `Merchant` (`merchant_id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=2886 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `Holiday_Hour`
-- ----------------------------
DROP TABLE IF EXISTS `Holiday_Hour`;
CREATE TABLE `Holiday_Hour` (
  `holiday_id` int(11) NOT NULL AUTO_INCREMENT,
  `merchant_id` int(11) NOT NULL,
  `the_date` date NOT NULL,
  `day_open` char(1) NOT NULL,
  `open` time NOT NULL,
  `close` time NOT NULL,
  `second_close` time DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`holiday_id`),
  UNIQUE KEY `merchant_date` (`merchant_id`,`the_date`),
  KEY `merchant_id` (`merchant_id`),
  KEY `the_date` (`the_date`)
) ENGINE=InnoDB AUTO_INCREMENT=12841 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `Hour`
-- ----------------------------
DROP TABLE IF EXISTS `Hour`;
CREATE TABLE `Hour` (
  `hour_id` int(11) NOT NULL AUTO_INCREMENT,
  `merchant_id` int(11) NOT NULL DEFAULT '0',
  `hour_type` varchar(10) NOT NULL DEFAULT 'R',
  `day_of_week` varchar(10) NOT NULL DEFAULT '0',
  `open` time NOT NULL DEFAULT '00:00:00',
  `close` time DEFAULT NULL,
  `second_close` time DEFAULT NULL,
  `day_open` char(1) NOT NULL DEFAULT 'Y',
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`hour_id`),
  UNIQUE KEY `merchant_day` (`merchant_id`,`hour_type`,`day_of_week`),
  KEY `fk_H_merchant_id` (`merchant_id`),
  KEY `hour_type` (`hour_type`),
  KEY `day_of_week` (`day_of_week`),
  CONSTRAINT `fk_H_merchant_id` FOREIGN KEY (`merchant_id`) REFERENCES `Merchant` (`merchant_id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `Hour_ibfk_1` FOREIGN KEY (`hour_type`) REFERENCES `Lookup` (`type_id_value`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `Hour_ibfk_2` FOREIGN KEY (`day_of_week`) REFERENCES `Lookup` (`type_id_value`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=38140 DEFAULT CHARSET=utf8 COMMENT='To maintain all merchant business hours';

-- ----------------------------
--  Table structure for `Hour_Temp`
-- ----------------------------
DROP TABLE IF EXISTS `Hour_Temp`;
CREATE TABLE `Hour_Temp` (
  `hour_id` int(11) NOT NULL AUTO_INCREMENT,
  `merchant_id` int(11) NOT NULL DEFAULT '0',
  `hour_type` varchar(10) NOT NULL,
  `day_of_week` varchar(10) NOT NULL DEFAULT '0',
  `open` time NOT NULL DEFAULT '00:00:00',
  `close` time NOT NULL DEFAULT '00:00:00',
  `day_open` char(1) NOT NULL DEFAULT 'Y',
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`hour_id`),
  KEY `fk_H_merchant_id` (`merchant_id`),
  KEY `hour_type` (`hour_type`),
  KEY `day_of_week` (`day_of_week`)
) ENGINE=InnoDB AUTO_INCREMENT=6099 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `Item`
-- ----------------------------
DROP TABLE IF EXISTS `Item`;
CREATE TABLE `Item` (
  `item_id` int(11) NOT NULL AUTO_INCREMENT,
  `external_item_id` varchar(255) DEFAULT NULL,
  `menu_type_id` int(11) NOT NULL,
  `tax_group` int(11) NOT NULL DEFAULT '1',
  `item_name` varchar(35) NOT NULL,
  `item_print_name` varchar(50) DEFAULT NULL COMMENT 'for ticket printer delivery',
  `description` varchar(255) NOT NULL,
  `active` char(1) NOT NULL DEFAULT 'Y',
  `priority` int(11) NOT NULL DEFAULT '0',
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`item_id`),
  KEY `menu_type_id` (`menu_type_id`),
  KEY `external_item_id` (`external_item_id`),
  CONSTRAINT `fk_MuI_menu_type_id` FOREIGN KEY (`menu_type_id`) REFERENCES `Menu_Type` (`menu_type_id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=281734 DEFAULT CHARSET=utf8 COMMENT='To maintain list of menu items that can be individually orde';

-- ----------------------------
--  Table structure for `Item_Modifier_Group_Map`
-- ----------------------------
DROP TABLE IF EXISTS `Item_Modifier_Group_Map`;
CREATE TABLE `Item_Modifier_Group_Map` (
  `map_id` int(11) NOT NULL AUTO_INCREMENT,
  `merchant_id` int(11) NOT NULL DEFAULT '0' COMMENT 'zero means all merchants pointed to this menu get it',
  `item_id` int(11) NOT NULL,
  `modifier_group_id` int(11) NOT NULL,
  `display_name` varchar(50) NOT NULL DEFAULT 'Please Name Me',
  `min` int(2) NOT NULL DEFAULT '0',
  `max` int(2) NOT NULL DEFAULT '1',
  `price_override` decimal(10,2) NOT NULL DEFAULT '0.00',
  `price_max` decimal(10,2) NOT NULL DEFAULT '88888.00' COMMENT 'this will determin if there is maximum charge for the group regardless of number of items chosen',
  `priority` int(11) NOT NULL,
  `combo_tag` varchar(10) DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`map_id`),
  UNIQUE KEY `item_mod` (`item_id`,`modifier_group_id`,`merchant_id`),
  KEY `modifier_group_id` (`modifier_group_id`),
  KEY `merchnat_id` (`merchant_id`),
  KEY `item_id` (`item_id`),
  CONSTRAINT `fk_MIMGM_item_id` FOREIGN KEY (`item_id`) REFERENCES `Item` (`item_id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `fk_MIMGM_modifier_group_id` FOREIGN KEY (`modifier_group_id`) REFERENCES `Modifier_Group` (`modifier_group_id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=6073379 DEFAULT CHARSET=utf8 COMMENT='To associate Item, Modifier_Group and Behavior tables';

-- ----------------------------
--  Table structure for `Item_Modifier_Item_Map`
-- ----------------------------
DROP TABLE IF EXISTS `Item_Modifier_Item_Map`;
CREATE TABLE `Item_Modifier_Item_Map` (
  `map_id` int(11) NOT NULL AUTO_INCREMENT,
  `item_id` int(11) NOT NULL,
  `modifier_item_id` int(11) NOT NULL,
  `mod_item_min` int(2) NOT NULL DEFAULT '0',
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`map_id`),
  UNIQUE KEY `item_modifier_item` (`item_id`,`modifier_item_id`),
  KEY `fk_ICW_item_id` (`item_id`),
  KEY `fk_ICW_modifier_item_id` (`modifier_item_id`),
  CONSTRAINT `fk_ICW_item_id` FOREIGN KEY (`item_id`) REFERENCES `Item` (`item_id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `fk_ICW_modifier_item_id` FOREIGN KEY (`modifier_item_id`) REFERENCES `Modifier_Item` (`modifier_item_id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=824252 DEFAULT CHARSET=utf8 COMMENT='To associte Item and Modifier_item tables';

-- ----------------------------
--  Table structure for `Item_Size_Map`
-- ----------------------------
DROP TABLE IF EXISTS `Item_Size_Map`;
CREATE TABLE `Item_Size_Map` (
  `item_size_id` int(11) NOT NULL AUTO_INCREMENT,
  `merchant_id` int(11) NOT NULL DEFAULT '0',
  `external_id` varchar(255) DEFAULT NULL,
  `item_id` int(11) NOT NULL DEFAULT '0',
  `size_id` int(11) NOT NULL DEFAULT '0',
  `tax_group` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `active` char(1) NOT NULL DEFAULT 'Y',
  `priority` int(11) NOT NULL DEFAULT '10',
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`item_size_id`),
  UNIQUE KEY `item-size-merchant` (`merchant_id`,`item_id`,`size_id`),
  KEY `item_id` (`item_id`),
  KEY `size_id` (`size_id`),
  KEY `external_id` (`external_id`),
  KEY `merchant_id` (`merchant_id`),
  CONSTRAINT `fk_IP_item_id` FOREIGN KEY (`item_id`) REFERENCES `Item` (`item_id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `fk_IP_size_id` FOREIGN KEY (`size_id`) REFERENCES `Sizes` (`size_id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=895523 DEFAULT CHARSET=utf8 COMMENT='To associate Item and Size tables';

-- ----------------------------
--  Table structure for `Lookup_Master`
-- ----------------------------
DROP TABLE IF EXISTS `Lookup_Master`;
CREATE TABLE `Lookup_Master` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type_id_field` varchar(100) NOT NULL,
  `description` varchar(100) NOT NULL,
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`id`),
  KEY `OTHER` (`type_id_field`)
) ENGINE=InnoDB AUTO_INCREMENT=107 DEFAULT CHARSET=utf8 COMMENT='To maintain group list of reference informations';

INSERT INTO `Lookup_Master` VALUES(2, 'ach_type', 'Uses for ACH information', '2010-07-13 21:10:54', '2010-07-14 21:07:42', 'N');
INSERT INTO `Lookup_Master` VALUES(4, 'address_type', 'Type of Address associated to Merchant', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(6, 'answer_value', 'Reporting Parameter', '2010-07-13 21:38:50', '2010-07-14 21:07:43', 'N');
INSERT INTO `Lookup_Master` VALUES(8, 'billing_type', 'Merchant billing method', '2010-07-13 21:10:54', '2010-07-14 21:07:45', 'N');
INSERT INTO `Lookup_Master` VALUES(10, 'card_type', 'Type of Credit Card', '2010-07-13 21:10:54', '2010-07-14 21:07:45', 'N');
INSERT INTO `Lookup_Master` VALUES(12, 'cat_id', 'Menu Type Category', '2010-11-01 06:00:00', '2010-11-01 06:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(14, 'contract_term', 'Length of contact is months', '2010-08-13 17:16:27', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(16, 'country', 'Country field drop down list', '2010-07-13 21:10:54', '2010-07-14 21:07:46', 'N');
INSERT INTO `Lookup_Master` VALUES(18, 'date_range', 'Reporting Parameter', '2010-07-13 21:38:50', '2010-07-14 20:32:59', 'N');
INSERT INTO `Lookup_Master` VALUES(20, 'day_of_week', 'Day of Week (Sunday thru Saturday)', '2010-07-13 21:10:54', '2010-07-14 20:32:59', 'N');
INSERT INTO `Lookup_Master` VALUES(22, 'deal_type', 'Merchant Agreement Fee Description', '2010-08-13 15:12:58', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(24, 'hour_type', 'Business Hours', '2010-07-14 19:44:55', '2010-07-14 20:32:59', 'N');
INSERT INTO `Lookup_Master` VALUES(26, 'last_two_weeks_only', 'Reporting Parameter', '2010-07-13 21:38:13', '2010-07-14 20:32:59', 'N');
INSERT INTO `Lookup_Master` VALUES(28, 'locale', 'Taxing Authority', '2010-07-14 20:35:18', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(30, 'logical_delete', 'non physical deletion of record', '2010-07-14 20:35:18', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(32, 'menu_type', 'Categories to Organize a Menu', '2010-07-13 21:10:54', '2010-07-14 21:07:47', 'N');
INSERT INTO `Lookup_Master` VALUES(34, 'merchant_type', 'Reporting Parameter', '2010-07-13 21:38:13', '2010-07-14 20:33:39', 'N');
INSERT INTO `Lookup_Master` VALUES(36, 'message_type', 'Category of Customer or Merchant Communications', '2010-07-14 22:16:53', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(38, 'min_max', 'Select value range', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(40, 'mobile_app_type', 'Mobile Application Platform', '2010-07-14 22:40:40', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(42, 'modifier_type', 'Modifier Group Type', '0000-00-00 00:00:00', '2010-09-04 16:39:43', 'N');
INSERT INTO `Lookup_Master` VALUES(44, 'month', 'Calendar Month', '2011-03-21 06:00:00', '2011-03-21 06:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(46, 'order_del_type', 'Merchant Order Delivery method', '2010-07-13 21:10:54', '2010-07-14 21:07:47', 'N');
INSERT INTO `Lookup_Master` VALUES(48, 'order_type', 'Transaction Type', '2010-07-14 22:20:14', '2010-07-14 22:20:45', 'N');
INSERT INTO `Lookup_Master` VALUES(50, 'pymt_sch', 'Merchant Payment Schedule', '2010-08-13 17:17:09', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(52, 'report_group', 'Merchant Group Category for Reports', '2010-07-13 21:10:54', '2010-07-14 21:07:48', 'N');
INSERT INTO `Lookup_Master` VALUES(54, 'splickit_team_orders', 'Reporting Parameter', '2010-07-13 21:47:40', '2010-07-14 20:33:39', 'N');
INSERT INTO `Lookup_Master` VALUES(56, 'state', 'State and Province drop down list', '2010-07-13 21:10:54', '2010-07-14 21:07:49', 'N');
INSERT INTO `Lookup_Master` VALUES(58, 'status', 'Order Status', '2010-07-14 22:31:09', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(60, 'str_type', 'Store Category', '2010-07-13 21:10:54', '2010-07-14 21:07:49', 'N');
INSERT INTO `Lookup_Master` VALUES(62, 'time_zone', 'Time Zone', '2010-07-13 21:35:34', '2010-07-14 21:07:50', 'N');
INSERT INTO `Lookup_Master` VALUES(64, 'topic', 'Feedback topic ', '2010-07-13 21:10:54', '2010-07-14 21:07:51', 'N');
INSERT INTO `Lookup_Master` VALUES(66, 'trans_fee_payee', 'Payer for Splickit Transaction Fee', '2010-08-13 17:16:27', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(68, 'trans_fee_type', 'Transaction Fee type drop down list', '2010-07-13 21:10:54', '2010-07-14 21:07:51', 'N');
INSERT INTO `Lookup_Master` VALUES(70, 'yes_no', 'Yes No Dropdown', '2010-08-13 17:17:49', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(72, 'zero_dallor_orders', 'Zero Dollar Indicator for Reports', '2010-07-13 21:10:54', '2010-07-14 20:33:39', 'N');
INSERT INTO `Lookup_Master` VALUES(74, 'current_status', 'Current Status', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(76, 'object_type', 'Object Type (Menu_Change_Schedule)', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(79, 'time_increments', 'Time Increments (Delivery)', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(81, 'holiday', 'Holiday Store Status', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(85, 'splickit_agreements', 'Splickit Merchant Agreement Language', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(88, 'credit_type', 'Credit Type for Order Reversals', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(90, 'merchant_menu_type', 'Merchant Menu Type', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(92, 'cc_processor', 'CC Processor', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(94, 'inc_trans_pay_cycle', 'Transaction Payment Period', '0000-00-00 00:00:00', '2012-02-03 17:40:07', 'N');
INSERT INTO `Lookup_Master` VALUES(100, 'yes_no_LTO', 'Special Active dropdown for LTO objects', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(102, 'payment_cycle', 'Merchant Payments', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(103, 'order_delivery_template', 'Custom format for order delivery', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(104, 'custom_order_delivery_template', '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(105, 'utility_message_template', '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(106, 'message_template', '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(null, 'nutritional_label', 'nutritional_label', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(null,'delivery_price_type','differnt types of delivery price caclulation',NOW(),NOW(),'N');

-- ----------------------------
--  Table structure for `Lookup`
-- ----------------------------
DROP TABLE IF EXISTS `Lookup`;
CREATE TABLE `Lookup` (
  `lookup_id` int(11) NOT NULL AUTO_INCREMENT,
  `type_id_field` varchar(100) NOT NULL DEFAULT '',
  `type_id_value` varchar(100) NOT NULL,
  `type_id_name` text NOT NULL,
  `active` char(1) NOT NULL DEFAULT 'Y',
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`lookup_id`),
  UNIQUE KEY `cnst_field_vale` (`type_id_field`,`type_id_value`),
  KEY `fk_L_type_id_field` (`type_id_field`),
  KEY `type_id_value` (`type_id_value`),
  CONSTRAINT `fk_L_type_id_field` FOREIGN KEY (`type_id_field`) REFERENCES `Lookup_Master` (`type_id_field`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=3548 DEFAULT CHARSET=utf8 COMMENT='To maintain detail list of reference informations';

INSERT INTO `Lookup` VALUES(2533, 'str_type', 'C', 'Cafe', 'Y', '2010-07-13 21:41:15', '2010-07-14 20:58:58', 'N');
INSERT INTO `Lookup` VALUES(2534, 'str_type', 'D', 'Deli', 'Y', '2010-07-13 21:41:15', '2010-07-14 20:58:58', 'N');
INSERT INTO `Lookup` VALUES(2535, 'str_type', 'S', 'Sandwich Shop', 'Y', '2010-07-13 21:41:15', '2010-07-14 20:58:58', 'N');
INSERT INTO `Lookup` VALUES(2536, 'str_type', 'P', 'Pizza', 'Y', '2010-07-13 21:41:15', '2010-07-14 20:58:58', 'N');
INSERT INTO `Lookup` VALUES(2537, 'str_type', 'M', 'Mexican', 'Y', '2010-07-13 21:41:15', '2010-07-14 20:58:58', 'N');
INSERT INTO `Lookup` VALUES(2538, 'order_del_type', 'P', 'Phone', 'Y', '2010-07-13 21:41:15', '2010-07-14 20:58:56', 'N');
INSERT INTO `Lookup` VALUES(2539, 'order_del_type', 'E', 'Email', 'Y', '2010-07-13 21:41:15', '2010-07-14 20:58:56', 'N');
INSERT INTO `Lookup` VALUES(2540, 'order_del_type', 'F', 'Fax', 'Y', '2010-07-13 21:41:15', '2010-07-14 20:58:56', 'N');
INSERT INTO `Lookup` VALUES(2541, 'order_del_type', 'R', 'Blackberry', 'Y', '2010-07-13 21:41:15', '2010-07-14 20:58:56', 'N');
INSERT INTO `Lookup` VALUES(2542, 'order_del_type', 'T', 'Printer', 'Y', '2010-07-13 21:41:15', '2010-07-14 20:58:56', 'N');
INSERT INTO `Lookup` VALUES(2548, 'day_of_week', '1', 'Sunday', 'Y', '2010-07-13 21:41:50', '2010-07-13 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(2549, 'day_of_week', '2', 'Monday', 'Y', '2010-07-13 21:41:50', '2010-07-13 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(2550, 'day_of_week', '3', 'Tuesday', 'Y', '2010-07-13 21:41:50', '2010-07-13 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(2551, 'day_of_week', '4', 'Wednesday', 'Y', '2010-07-13 21:41:50', '2010-07-13 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(2552, 'day_of_week', '5', 'Thursday', 'Y', '2010-07-13 21:41:50', '2010-07-13 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(2553, 'day_of_week', '6', 'Friday', 'Y', '2010-07-13 21:41:50', '2010-07-13 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(2554, 'day_of_week', '7', 'Saturday', 'Y', '2010-07-13 21:41:50', '2010-07-13 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(2555, 'trans_fee_type', 'F', 'Flat', 'Y', '2010-07-13 21:41:50', '2010-07-14 20:58:59', 'N');
INSERT INTO `Lookup` VALUES(2556, 'trans_fee_type', 'P', 'Percent', 'Y', '2010-07-13 21:41:50', '2010-07-14 20:58:59', 'N');
INSERT INTO `Lookup` VALUES(2557, 'state', 'AB', 'Alberta', 'Y', '2010-07-13 21:41:50', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2558, 'state', 'BC', 'British Columbia', 'Y', '2010-07-13 21:41:50', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2559, 'state', 'MB', 'Manitoba', 'Y', '2010-07-13 21:41:50', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2560, 'state', 'NB', 'New Brunswick', 'Y', '2010-07-13 21:41:50', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2561, 'state', 'NL', 'Newfoundland and Labrador', 'Y', '2010-07-13 21:41:50', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2562, 'state', 'NT', 'Northwest Territories', 'Y', '2010-07-13 21:41:50', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2563, 'state', 'NS', 'Nova Scotia', 'Y', '2010-07-13 21:41:50', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2564, 'state', 'NU', 'Nunavut', 'Y', '2010-07-13 21:41:50', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2565, 'state', 'ON', 'Ontario', 'Y', '2010-07-13 21:41:50', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2566, 'state', 'PE', 'Prince Edward Island', 'Y', '2010-07-13 21:41:50', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2567, 'state', 'QC', 'Quebec', 'Y', '2010-07-13 21:41:50', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2568, 'state', 'SK', 'Saskatchewan', 'Y', '2010-07-13 21:41:50', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2569, 'state', 'YT', 'Yukon', 'Y', '2010-07-13 21:41:50', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2579, 'country', 'US', 'United States', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2580, 'country', 'AF', 'Afghanistan', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2581, 'country', 'AL', 'Albania', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2582, 'country', 'DZ', 'Algeria', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2583, 'country', 'AS', 'American Samoa', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2584, 'country', 'AD', 'Andorra', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2585, 'country', 'AO', 'Angola', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2586, 'country', 'AI', 'Anguilla', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2587, 'country', 'AQ', 'Antarctica', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2588, 'country', 'AG', 'Antigua And Barbuda', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2589, 'country', 'AR', 'Argentina', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2590, 'country', 'AM', 'Armenia', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2591, 'country', 'AW', 'Aruba', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2592, 'country', 'AU', 'Australia', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2593, 'country', 'AT', 'Austria', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2594, 'country', 'AZ', 'Azerbaijan', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2595, 'country', 'BS', 'Bahamas', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2596, 'country', 'BH', 'Bahrain', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2597, 'country', 'BD', 'Bangladesh', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2598, 'country', 'BB', 'Barbados', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2599, 'country', 'BY', 'Belarus', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2600, 'country', 'BE', 'Belgium', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2601, 'country', 'BZ', 'Belize', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2602, 'country', 'BJ', 'Benin', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2603, 'country', 'BM', 'Bermuda', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2604, 'country', 'BT', 'Bhutan', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2605, 'country', 'BO', 'Bolivia', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2606, 'country', 'BA', 'Bosnia And Herzegowina', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2607, 'country', 'BW', 'Botswana', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2608, 'country', 'BV', 'Bouvet Island', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2609, 'country', 'BR', 'Brazil', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2610, 'country', 'IO', 'British Indian Ocean Territory', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2611, 'country', 'BN', 'Brunei Darussalam', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2612, 'country', 'BG', 'Bulgaria', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2613, 'country', 'BF', 'Burkina Faso', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2614, 'country', 'BI', 'Burundi', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2615, 'country', 'KH', 'Cambodia', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2616, 'country', 'CM', 'Cameroon', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2617, 'country', 'CA', 'Canada', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2618, 'country', 'CV', 'Cape Verde', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2619, 'country', 'KY', 'Cayman Islands', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2620, 'country', 'CF', 'Central African Republic', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2621, 'country', 'TD', 'Chad', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2622, 'country', 'CL', 'Chile', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2623, 'country', 'CN', 'China', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2624, 'country', 'CX', 'Christmas Island', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2625, 'country', 'CC', 'Cocos (Keeling) Islands', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2626, 'country', 'CO', 'Colombia', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2627, 'country', 'KM', 'Comoros', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2628, 'country', 'CG', 'Congo', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2629, 'country', 'CK', 'Cook Islands', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2630, 'country', 'CR', 'Costa Rica', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2631, 'country', 'CI', 'Cote D''Ivoire', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2632, 'country', 'HR', 'Croatia', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2633, 'country', 'CY', 'Cyprus', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2634, 'country', 'CZ', 'Czech Republic', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2635, 'country', 'DK', 'Denmark', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2636, 'country', 'DJ', 'Djibouti', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2637, 'country', 'DM', 'Dominica', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2638, 'country', 'DO', 'Dominican Republic', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2639, 'country', 'TP', 'East Timor', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2640, 'country', 'EC', 'Ecuador', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2641, 'country', 'EG', 'Egypt', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2642, 'country', 'SV', 'El Salvador', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2643, 'country', 'GQ', 'Equatorial Guinea', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2644, 'country', 'ER', 'Eritrea', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2645, 'country', 'EE', 'Estonia', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2646, 'country', 'ET', 'Ethiopia', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2647, 'country', 'FK', 'Falkland Islands', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2648, 'country', 'FO', 'Faroe Islands', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2649, 'country', 'FJ', 'Fiji', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2650, 'country', 'FI', 'Finland', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2651, 'country', 'FR', 'France', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2652, 'country', 'FX', 'France Metropolitan', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2653, 'country', 'GF', 'French Guiana', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2654, 'country', 'PF', 'French Polynesia', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2655, 'country', 'TF', 'French Southern Territories', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2656, 'country', 'GA', 'Gabon', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2657, 'country', 'GM', 'Gambia', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2658, 'country', 'GE', 'Georgia', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2659, 'country', 'DE', 'Germany', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2660, 'country', 'GH', 'Ghana', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2661, 'country', 'GI', 'Gibraltar', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2662, 'country', 'GR', 'Greece', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2663, 'country', 'GL', 'Greenland', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2664, 'country', 'GD', 'Grenada', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2665, 'country', 'GP', 'Guadeloupe', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2666, 'country', 'GU', 'Guam', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2667, 'country', 'GT', 'Guatemala', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2668, 'country', 'GN', 'Guinea', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2669, 'country', 'GW', 'Guinea-Bissau', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2670, 'country', 'GY', 'Guyana', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2671, 'country', 'HT', 'Haiti', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2672, 'country', 'HM', 'Heard And Mc Donald Islands', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2673, 'country', 'HN', 'Honduras', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2674, 'country', 'HK', 'Hong Kong', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2675, 'country', 'HU', 'Hungary', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2676, 'country', 'IS', 'Iceland', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2677, 'country', 'IN', 'India', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2678, 'country', 'ID', 'Indonesia', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2679, 'country', 'IQ', 'Iraq', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2680, 'country', 'IE', 'Ireland', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2681, 'country', 'IL', 'Israel', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2682, 'country', 'IT', 'Italy', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2683, 'country', 'JM', 'Jamaica', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2684, 'country', 'JP', 'Japan', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2685, 'country', 'JO', 'Jordan', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2686, 'country', 'KZ', 'Kazakhstan', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2687, 'country', 'KE', 'Kenya', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2688, 'country', 'KI', 'Kiribati', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2689, 'country', 'KW', 'Kuwait', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2690, 'country', 'KG', 'Kyrgyzstan', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2691, 'country', 'LA', 'Lao People''s Republic', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2692, 'country', 'LV', 'Latvia', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2693, 'country', 'LB', 'Lebanon', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2694, 'country', 'LS', 'Lesotho', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2695, 'country', 'LR', 'Liberia', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2696, 'country', 'LY', 'Libyan Arab Jamahiriya', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2697, 'country', 'LI', 'Liechtenstein', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2698, 'country', 'LT', 'Lithuania', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2699, 'country', 'LU', 'Luxembourg', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2700, 'country', 'MO', 'Macau', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2701, 'country', 'MK', 'Macedonia', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2702, 'country', 'MG', 'Madagascar', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2703, 'country', 'MW', 'Malawi', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2704, 'country', 'MY', 'Malaysia', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2705, 'country', 'MV', 'Maldives', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2706, 'country', 'ML', 'Mali', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2707, 'country', 'MT', 'Malta', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2708, 'country', 'MH', 'Marshall Islands', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2709, 'country', 'MQ', 'Martinique', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2710, 'country', 'MR', 'Mauritania', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2711, 'country', 'MU', 'Mauritius', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2712, 'country', 'YT', 'Mayotte', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2713, 'country', 'MX', 'Mexico', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2714, 'country', 'FM', 'Micronesia', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2715, 'country', 'MD', 'Moldova', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2716, 'country', 'MC', 'Monaco', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2717, 'country', 'MN', 'Mongolia', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2718, 'country', 'MS', 'Montserrat', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2719, 'country', 'MA', 'Morocco', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2720, 'country', 'MZ', 'Mozambique', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2721, 'country', 'MM', 'Myanmar', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2722, 'country', 'NA', 'Namibia', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2723, 'country', 'NR', 'Nauru', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2724, 'country', 'NP', 'Nepal', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2725, 'country', 'NL', 'Netherlands', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2726, 'country', 'AN', 'Netherlands Antilles', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2727, 'country', 'NC', 'New Caledonia', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2728, 'country', 'NZ', 'New Zealand', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2729, 'country', 'NI', 'Nicaragua', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2730, 'country', 'NE', 'Niger', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2731, 'country', 'NG', 'Nigeria', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2732, 'country', 'NU', 'Niue', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2733, 'country', 'NF', 'Norfolk Island', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2734, 'country', 'MP', 'Northern Mariana Islands', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2735, 'country', 'NO', 'Norway', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2736, 'country', 'OM', 'Oman', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2737, 'country', 'PK', 'Pakistan', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2738, 'country', 'PW', 'Palau', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2739, 'country', 'PS', 'Palestine', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2740, 'country', 'PA', 'Panama', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2741, 'country', 'PG', 'Papua New Guinea', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2742, 'country', 'PY', 'Paraguay', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2743, 'country', 'PE', 'Peru', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2744, 'country', 'PH', 'Philippines', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2745, 'country', 'PN', 'Pitcairn', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2746, 'country', 'PL', 'Poland', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2747, 'country', 'PT', 'Portugal', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2748, 'country', 'PR', 'Puerto Rico', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2749, 'country', 'QA', 'Qatar', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2750, 'country', 'RE', 'Reunion', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2751, 'country', 'RO', 'Romania', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2752, 'country', 'RU', 'Russian Federation', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2753, 'country', 'RW', 'Rwanda', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2754, 'country', 'KN', 'Saint Kitts And Nevis', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2755, 'country', 'LC', 'Saint Lucia', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2756, 'country', 'VC', 'Saint Vincent And The Grenadines', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2757, 'country', 'WS', 'Samoa', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2758, 'country', 'SM', 'San Marino', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2759, 'country', 'ST', 'Sao Tome And Principe', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2760, 'country', 'SA', 'Saudi Arabia', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2761, 'country', 'SN', 'Senegal', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2762, 'country', 'SC', 'Seychelles', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2763, 'country', 'SL', 'Sierra Leone', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2764, 'country', 'SG', 'Singapore', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2765, 'country', 'SK', 'Slovakia', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2766, 'country', 'SI', 'Slovenia', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2767, 'country', 'SB', 'Solomon Islands', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2768, 'country', 'SO', 'Somalia', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2769, 'country', 'ZA', 'South Africa', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2770, 'country', 'GS', 'South Georgia/South Sandwich Island', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2771, 'country', 'KR', 'South Korea', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2772, 'country', 'ES', 'Spain', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2773, 'country', 'LK', 'Sri Lanka', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2774, 'country', 'SH', 'St Helena', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2775, 'country', 'PM', 'St Pierre and Miquelon', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2776, 'country', 'SR', 'Suriname', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2777, 'country', 'SJ', 'Svalbard And Jan Mayen Islands', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2778, 'country', 'SZ', 'Swaziland', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2779, 'country', 'SE', 'Sweden', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2780, 'country', 'CH', 'Switzerland', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2781, 'country', 'TW', 'Taiwan', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2782, 'country', 'TJ', 'Tajikistan', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2783, 'country', 'TZ', 'Tanzania', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2784, 'country', 'TH', 'Thailand', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2785, 'country', 'TG', 'Togo', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2786, 'country', 'TK', 'Tokelau', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2787, 'country', 'TO', 'Tonga', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2788, 'country', 'TT', 'Trinidad And Tobago', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2789, 'country', 'TN', 'Tunisia', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2790, 'country', 'TR', 'Turkey', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2791, 'country', 'TM', 'Turkmenistan', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2792, 'country', 'TC', 'Turks And Caicos Islands', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2793, 'country', 'TV', 'Tuvalu', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2794, 'country', 'UG', 'Uganda', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2795, 'country', 'UA', 'Ukraine', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2796, 'country', 'AE', 'United Arab Emirates', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2797, 'country', 'GB', 'United Kingdom', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2798, 'country', 'UM', 'United States Minor Outlying Island', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2799, 'country', 'UY', 'Uruguay', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2800, 'country', 'UZ', 'Uzbekistan', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2801, 'country', 'VU', 'Vanuatu', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2802, 'country', 'VA', 'Vatican City State', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2803, 'country', 'VE', 'Venezuela', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2804, 'country', 'VN', 'Vietnam', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2805, 'country', 'VG', 'Virgin Islands (British)', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2806, 'country', 'VI', 'Virgin Islands (U.S.)', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2807, 'country', 'WF', 'Wallis And Futuna Islands', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2808, 'country', 'EH', 'Western Sahara', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2809, 'country', 'YE', 'Yemen', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2810, 'country', 'ZR', 'Zaire', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2811, 'country', 'ZM', 'Zambia', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2812, 'country', 'ZW', 'Zimbabwe', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(2813, 'state', 'AL', 'Alabama', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2814, 'state', 'AK', 'Alaska', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2815, 'state', 'AZ', 'Arizona', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2816, 'state', 'AR', 'Arkansas', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2817, 'state', 'CA', 'California', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2818, 'state', 'CO', 'Colorado', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2819, 'state', 'CT', 'Connecticut', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2820, 'state', 'DE', 'Delaware', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2821, 'state', 'DC', 'District of Columbia', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2822, 'state', 'FL', 'Florida', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2823, 'state', 'GA', 'Georgia', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2824, 'state', 'HI', 'Hawaii', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2825, 'state', 'ID', 'Idaho', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2826, 'state', 'IL', 'Illinois', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2827, 'state', 'IN', 'Indiana', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2828, 'state', 'IA', 'Iowa', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2829, 'state', 'KS', 'Kansas', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2830, 'state', 'KY', 'Kentucky', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2831, 'state', 'LA', 'Louisiana', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2832, 'state', 'ME', 'Maine', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2833, 'state', 'MD', 'Maryland', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2834, 'state', 'MA', 'Massachusetts', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2835, 'state', 'MI', 'Michigan', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2836, 'state', 'MN', 'Minnesota', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2837, 'state', 'MS', 'Mississippi', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2838, 'state', 'MO', 'Missouri', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2839, 'state', 'MT', 'Montana', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2840, 'state', 'NE', 'Nebraska', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2841, 'state', 'NV', 'Nevada', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2842, 'state', 'NH', 'New Hampshire', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2843, 'state', 'NJ', 'New Jersey', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2844, 'state', 'NM', 'New Mexico', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2845, 'state', 'NY', 'New York', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2846, 'state', 'NC', 'North Carolina', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2847, 'state', 'ND', 'North Dakota', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2848, 'state', 'OH', 'Ohio', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2849, 'state', 'OK', 'Oklahoma', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2850, 'state', 'OR', 'Oregon', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2851, 'state', 'PA', 'Pennsylvania', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2852, 'state', 'PR', 'Puerto Rico', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2853, 'state', 'RI', 'Rhode Island', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2854, 'state', 'SC', 'South Carolina', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2855, 'state', 'SD', 'South Dakota', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2856, 'state', 'TN', 'Tennessee', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2857, 'state', 'TX', 'Texas', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2858, 'state', 'UT', 'Utah', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2859, 'state', 'VT', 'Vermont', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2860, 'state', 'VI', 'Virgin Islands', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2861, 'state', 'VA', 'Virginia', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2862, 'state', 'WA', 'Washington', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2863, 'state', 'WV', 'West Virginia', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2864, 'state', 'WI', 'Wisconsin', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(2865, 'state', 'WY', 'Wyoming', 'Y', '2010-07-13 21:44:01', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(3105, 'answer_value', 'YesNo', 'Yes or No Drop Down', 'Y', '2010-07-13 21:46:19', '2010-07-14 20:58:53', 'N');
INSERT INTO `Lookup` VALUES(3106, 'answer_value', 'CheckBox', 'Check Box Control', 'Y', '2010-07-13 21:46:19', '2010-07-14 20:58:53', 'N');
INSERT INTO `Lookup` VALUES(3108, 'order_del_type', 'S', 'Selbysoft', 'Y', '2010-07-13 21:46:32', '2010-07-14 20:58:56', 'N');
INSERT INTO `Lookup` VALUES(3109, 'order_del_type', 'C', 'CoffeShopManager', 'Y', '2010-07-13 21:46:32', '2010-07-14 20:58:56', 'N');
INSERT INTO `Lookup` VALUES(3110, 'report_group', '1', 'Alpha', 'Y', '2010-07-13 21:46:32', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(3111, 'report_group', '2', 'Demo', 'Y', '2010-07-13 21:46:32', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(3112, 'report_group', '3', 'Micro', 'Y', '2010-07-13 21:46:32', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(3113, 'report_group', '4', 'PromoOnly', 'Y', '2010-07-13 21:46:32', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(3114, 'report_group', '5', 'Test', 'Y', '2010-07-13 21:46:32', '2010-07-14 20:58:57', 'N');
INSERT INTO `Lookup` VALUES(3118, 'zero_dallor_orders', 'No', 'AND order_amt != 0', 'Y', '2010-07-13 21:46:53', '2010-07-13 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(3119, 'zero_dallor_orders', 'Yes', 'AND 1=1', 'Y', '2010-07-13 21:46:53', '2010-07-13 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(3123, 'splickit_team_orders', 'No', 'AND U.user_id NOT IN (20000, 2, 10, 20278, 20211, 20102, 20001, 20126, 20315, 20349, 20306, 20596, 21126)', 'Y', '2010-07-13 21:47:49', '2010-10-12 01:34:04', 'N');
INSERT INTO `Lookup` VALUES(3124, 'splickit_team_orders', 'Yes', 'AND 1=1', 'Y', '2010-07-13 21:47:49', '2010-07-13 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(3126, 'last_two_weeks_only', 'Yes', 'AND TIMESTAMPDIFF(DAY,U.modified,CURDATE( )) > 14', 'Y', '2010-07-13 21:48:12', '2010-07-13 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(3127, 'last_two_weeks_only', 'No', 'AND 1=1', 'Y', '2010-07-13 21:48:12', '2010-07-13 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(3128, 'merchant_type', 'Q', 'Quick Menu', 'Y', '2010-07-13 21:48:12', '2010-07-13 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(3129, 'merchant_type', 'F', 'Full Menu', 'Y', '2010-07-13 21:48:12', '2010-07-13 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(3130, 'date_range', 'Inception To Date', 'AND ''X'' = ''X''', 'Y', '2010-07-13 21:48:12', '2011-01-27 23:15:40', 'N');
INSERT INTO `Lookup` VALUES(3131, 'date_range', 'Month To Date', 'AND DATE_FORMAT(pickup_dt_tm,''%Y-%m-%d'') >= STR_TO_DATE( CONCAT( DATE_FORMAT( CURDATE( ) , "%Y%m" ) , "01" ) , "%Y%m%d" )', 'Y', '2010-07-13 21:48:12', '2010-10-15 15:40:30', 'N');
INSERT INTO `Lookup` VALUES(3132, 'date_range', 'Last 90 Days', 'AND DATE_FORMAT(pickup_dt_tm , "%Y%m%d" ) >= DATE_ADD(CURDATE(), INTERVAL - 90 DAY)', 'Y', '2010-07-13 21:48:12', '2011-01-27 23:21:13', 'N');
INSERT INTO `Lookup` VALUES(3133, 'date_range', 'Last 7  Days', 'AND DATE_FORMAT(pickup_dt_tm , "%Y%m%d" ) >= DATE_ADD(CURDATE(), INTERVAL - 7 DAY)', 'Y', '2010-07-13 21:48:12', '2011-01-27 23:21:13', 'N');
INSERT INTO `Lookup` VALUES(3134, 'date_range', 'Last Month', 'AND DATE_FORMAT(pickup_dt_tm,''%Y-%m-%d'') >= STR_TO_DATE( CONCAT(  DATE_FORMAT( CURDATE( ) , "%Y" ), SUBSTR(STR_TO_DATE(DATE_FORMAT( CURDATE( ) , "%m" ) - 1, "%m"),6,2)  , "01" ) , "%Y%m%d" ) AND DATE_FORMAT(pickup_dt_tm,''%Y-%m-%d'') < STR_TO_DATE( CONCAT(  DATE_FORMAT( CURDATE( ) , "%Y" ), SUBSTR(STR_TO_DATE(DATE_FORMAT( CURDATE( ) , "%m" ) - 0, "%m"),6,2)  , "01" ) , "%Y%m%d" )', 'Y', '2010-07-13 21:48:12', '2010-10-15 15:41:01', 'N');
INSERT INTO `Lookup` VALUES(3135, 'menu_type', '5', 'Burritos', 'Y', '2010-07-13 21:48:12', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(3136, 'menu_type', '6', 'Sandwiches', 'Y', '2010-07-13 21:48:12', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(3137, 'menu_type', '2', 'Drinks', 'Y', '2010-07-13 21:48:12', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(3138, 'menu_type', '7', 'Salads', 'Y', '2010-07-13 21:48:12', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(3139, 'menu_type', '8', 'Entrees', 'Y', '2010-07-13 21:48:12', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(3140, 'menu_type', '9', 'Signature Items', 'Y', '2010-07-13 21:48:12', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(3141, 'menu_type', '1', 'Coffee Drinks', 'Y', '2010-07-13 21:48:12', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(3142, 'menu_type', '10', 'Breggos', 'Y', '2010-07-13 21:48:12', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(3143, 'menu_type', '11', 'Loose Food Items', 'Y', '2010-07-13 21:48:12', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(3144, 'menu_type', '12', 'Sides', 'Y', '2010-07-13 21:48:12', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(3145, 'menu_type', '13', 'Box Lunch', 'Y', '2010-07-13 21:48:12', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(3146, 'menu_type', '3', 'Slushys', 'Y', '2010-07-13 21:48:12', '2010-07-14 20:58:55', 'N');
INSERT INTO `Lookup` VALUES(3157, 'time_zone', '-12', '(GMT -12:00) Eniwetok, Kwajalein', 'Y', '2010-07-13 21:48:24', '2010-07-14 20:58:59', 'N');
INSERT INTO `Lookup` VALUES(3158, 'time_zone', '-11', '(GMT -11:00) Midway Island, Samoa', 'Y', '2010-07-13 21:48:24', '2010-07-14 20:58:59', 'N');
INSERT INTO `Lookup` VALUES(3159, 'time_zone', '-10', '(GMT -10:00) Hawaii', 'Y', '2010-07-13 21:48:24', '2010-07-14 20:58:59', 'N');
INSERT INTO `Lookup` VALUES(3160, 'time_zone', '-9', '(GMT -9:00) Alaska', 'Y', '2010-07-13 21:48:24', '2010-07-14 20:58:59', 'N');
INSERT INTO `Lookup` VALUES(3161, 'time_zone', '-8', '(GMT -8:00) Pacific Time (US &amp; Canada)', 'Y', '2010-07-13 21:48:24', '2010-07-14 20:58:59', 'N');
INSERT INTO `Lookup` VALUES(3162, 'time_zone', '-7', '(GMT -7:00) Mountain Time (US &amp; Canada)', 'Y', '2010-07-13 21:48:24', '2010-07-14 20:58:59', 'N');
INSERT INTO `Lookup` VALUES(3163, 'time_zone', '-6', '(GMT -6:00) Central Time (US &amp; Canada), Mexico City', 'Y', '2010-07-13 21:48:24', '2010-07-14 20:58:59', 'N');
INSERT INTO `Lookup` VALUES(3164, 'time_zone', '-5', '(GMT -5:00) Eastern Time (US &amp; Canada), Bogota, Lima', 'Y', '2010-07-13 21:48:24', '2010-07-14 20:58:59', 'N');
INSERT INTO `Lookup` VALUES(3165, 'time_zone', '-4.5', '(GMT -4:30) Caracas', 'Y', '2010-07-13 21:48:24', '2010-07-14 20:58:59', 'N');
INSERT INTO `Lookup` VALUES(3166, 'time_zone', '-4', '(GMT -4:00) Atlantic Time (Canada), La Paz, Santiago', 'Y', '2010-07-13 21:48:24', '2010-07-14 20:58:59', 'N');
INSERT INTO `Lookup` VALUES(3167, 'time_zone', '-3.5', '(GMT -3:30) Newfoundland', 'Y', '2010-07-13 21:48:24', '2010-07-14 20:58:59', 'N');
INSERT INTO `Lookup` VALUES(3168, 'time_zone', '-3', '(GMT -3:00) Brazil, Buenos Aires, Georgetown', 'Y', '2010-07-13 21:48:24', '2010-07-14 20:58:59', 'N');
INSERT INTO `Lookup` VALUES(3169, 'time_zone', '-2', '(GMT -2:00) Mid-Atlantic', 'Y', '2010-07-13 21:48:24', '2010-07-14 20:58:59', 'N');
INSERT INTO `Lookup` VALUES(3170, 'time_zone', '-1', '(GMT -1:00 hour) Azores, Cape Verde Islands', 'Y', '2010-07-13 21:48:24', '2010-07-14 20:58:59', 'N');
INSERT INTO `Lookup` VALUES(3171, 'time_zone', '0', '(GMT) Western Europe Time, London, Lisbon, Casablanca', 'Y', '2010-07-13 21:48:24', '2010-07-14 20:58:59', 'N');
INSERT INTO `Lookup` VALUES(3172, 'time_zone', '1', '(GMT +1:00 hour) Brussels, Copenhagen, Madrid, Paris', 'Y', '2010-07-13 21:48:24', '2010-07-14 20:58:59', 'N');
INSERT INTO `Lookup` VALUES(3173, 'time_zone', '2', '(GMT +2:00) Kaliningrad, South Africa', 'Y', '2010-07-13 21:48:24', '2010-07-14 20:58:59', 'N');
INSERT INTO `Lookup` VALUES(3174, 'time_zone', '3', '(GMT +3:00) Baghdad, Riyadh, Moscow, St. Petersburg', 'Y', '2010-07-13 21:48:24', '2010-07-14 20:58:59', 'N');
INSERT INTO `Lookup` VALUES(3175, 'time_zone', '3.5', '(GMT +3:30) Tehran', 'Y', '2010-07-13 21:48:24', '2010-07-14 20:58:59', 'N');
INSERT INTO `Lookup` VALUES(3176, 'time_zone', '4', '(GMT +4:00) Abu Dhabi, Muscat, Baku, Tbilisi', 'Y', '2010-07-13 21:48:24', '2010-07-14 20:58:59', 'N');
INSERT INTO `Lookup` VALUES(3177, 'time_zone', '4.5', '(GMT +4:30) Kabul', 'Y', '2010-07-13 21:48:24', '2010-07-14 20:58:59', 'N');
INSERT INTO `Lookup` VALUES(3178, 'time_zone', '5', '(GMT +5:00) Ekaterinburg, Islamabad, Karachi, Tashkent', 'Y', '2010-07-13 21:48:24', '2010-07-14 20:58:59', 'N');
INSERT INTO `Lookup` VALUES(3179, 'time_zone', '5.5', '(GMT +5:30) Bombay, Calcutta, Madras, New Delhi', 'Y', '2010-07-13 21:48:24', '2010-07-14 20:58:59', 'N');
INSERT INTO `Lookup` VALUES(3180, 'time_zone', '5.75', '(GMT +5:45) Kathmandu', 'Y', '2010-07-13 21:48:24', '2010-07-14 20:58:59', 'N');
INSERT INTO `Lookup` VALUES(3181, 'time_zone', '6', '(GMT +6:00) Almaty, Dhaka, Colombo', 'Y', '2010-07-13 21:48:24', '2010-07-14 20:58:59', 'N');
INSERT INTO `Lookup` VALUES(3182, 'time_zone', '6.5', '(GMT +6:30) Yangon, Cocos Islands', 'Y', '2010-07-13 21:48:24', '2010-07-14 20:58:59', 'N');
INSERT INTO `Lookup` VALUES(3183, 'time_zone', '7', '(GMT +7:00) Bangkok, Hanoi, Jakarta', 'Y', '2010-07-13 21:48:24', '2010-07-14 20:58:59', 'N');
INSERT INTO `Lookup` VALUES(3184, 'time_zone', '8', '(GMT +8:00) Beijing, Perth, Singapore, Hong Kong', 'Y', '2010-07-13 21:48:24', '2010-07-14 20:58:59', 'N');
INSERT INTO `Lookup` VALUES(3185, 'time_zone', '9', '(GMT +9:00) Tokyo, Seoul, Osaka, Sapporo, Yakutsk', 'Y', '2010-07-13 21:48:24', '2010-07-14 20:58:59', 'N');
INSERT INTO `Lookup` VALUES(3186, 'time_zone', '9.5', '(GMT +9:30) Adelaide, Darwin', 'Y', '2010-07-13 21:48:24', '2010-07-14 20:58:59', 'N');
INSERT INTO `Lookup` VALUES(3187, 'time_zone', '10', '(GMT +10:00) Eastern Australia, Guam, Vladivostok', 'Y', '2010-07-13 21:48:24', '2010-07-14 20:58:59', 'N');
INSERT INTO `Lookup` VALUES(3188, 'time_zone', '11', '(GMT +11:00) Magadan, Solomon Islands, New Caledonia', 'Y', '2010-07-13 21:48:24', '2010-07-14 20:58:59', 'N');
INSERT INTO `Lookup` VALUES(3189, 'time_zone', '12', '(GMT +12:00) Auckland, Wellington, Fiji, Kamchatka', 'Y', '2010-07-13 21:48:24', '2010-07-14 20:58:59', 'N');
INSERT INTO `Lookup` VALUES(3220, 'hour_type', 'R', 'Splickit Pickup Hours', 'Y', '2010-07-14 20:25:01', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3221, 'hour_type', 'S', 'Store Hours', 'Y', '2010-07-14 20:25:01', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3223, 'message_type', 'O', 'Mark Order as Still Open', 'Y', '2010-07-14 22:17:29', '2011-07-06 17:08:38', 'N');
INSERT INTO `Lookup` VALUES(3224, 'message_type', 'X', 'Mark Order as Executed', 'Y', '2010-07-14 22:17:29', '2011-11-13 01:25:02', 'N');
INSERT INTO `Lookup` VALUES(3226, 'locale', 'County', 'County Tax Authority', 'Y', '2010-07-14 22:26:11', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3227, 'locale', 'City', 'City Tax Authority', 'Y', '2010-07-14 22:26:11', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3229, 'locale', 'State', 'State Tax Authority', 'Y', '2010-07-14 22:26:40', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3230, 'locale', 'District', 'District Tax Authority', 'Y', '2010-07-14 22:26:40', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3232, 'mobile_app_type', 'I', 'Apple iPhone', 'Y', '2010-07-14 22:41:42', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3233, 'mobile_app_type', 'A', 'Google Android', 'Y', '2010-07-14 22:41:42', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3235, 'mobile_app_type', 'B', 'Blackberry', 'Y', '2010-07-14 22:42:04', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3236, 'modifier_type', 'S', 'Side', 'Y', '2010-07-15 16:01:28', '2010-09-04 16:39:28', 'N');
INSERT INTO `Lookup` VALUES(3237, 'modifier_type', 'T', 'Top', 'Y', '2010-07-15 16:01:28', '2010-09-07 22:04:40', 'N');
INSERT INTO `Lookup` VALUES(3240, 'status', 'P', 'Pending', 'Y', '2010-07-22 19:16:09', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3241, 'status', 'O', 'Open', 'Y', '2010-07-22 19:16:09', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3243, 'status', 'X', 'Executed', 'Y', '2010-07-22 19:16:09', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3247, 'status', 'N', 'Cancelled', 'Y', '2010-07-22 19:16:09', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3248, 'status', 'C', 'Closed', 'Y', '2010-07-22 19:16:09', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3252, 'deal_type', 'M', 'Month Fixed Fee', 'Y', '2010-08-13 15:13:30', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3253, 'deal_type', 'V', 'Variable Fee with Max Limit', 'Y', '2010-08-13 15:13:30', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3255, 'yes_no', 'Y', 'Yes', 'Y', '2010-08-13 17:18:13', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3256, 'yes_no', 'N', 'No', 'Y', '2010-08-13 17:18:13', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3258, 'trans_fee_payee', 'M', 'Merchant', 'Y', '2010-08-13 17:19:15', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3259, 'trans_fee_payee', 'C', 'Customer', 'Y', '2010-08-13 17:19:15', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3261, 'contract_term', '0', 'Month to Month', 'Y', '2010-08-13 17:20:29', '2010-08-13 17:23:09', 'N');
INSERT INTO `Lookup` VALUES(3262, 'contract_term', '6', '6 Months', 'Y', '2010-08-13 17:20:29', '2010-08-13 17:23:09', 'N');
INSERT INTO `Lookup` VALUES(3264, 'contract_term', '12', '12 Months', 'Y', '2010-08-13 17:20:54', '2010-08-13 17:23:09', 'N');
INSERT INTO `Lookup` VALUES(3265, 'contract_term', '24', '24 Months', 'Y', '2010-08-13 17:20:54', '2010-08-13 17:23:09', 'N');
INSERT INTO `Lookup` VALUES(3267, 'contract_term', '36', '36 Months', 'Y', '2010-08-13 17:21:03', '2010-08-13 17:23:09', 'N');
INSERT INTO `Lookup` VALUES(3268, 'pymt_sch', '1', 'Monthly', 'Y', '2010-08-13 17:23:46', '2011-03-23 02:43:34', 'N');
INSERT INTO `Lookup` VALUES(3269, 'pymt_sch', '2', 'Bi-Monthly', 'Y', '2010-08-13 17:23:46', '2011-03-23 02:36:53', 'N');
INSERT INTO `Lookup` VALUES(3271, 'pymt_sch', '4', 'Weekly', 'N', '2010-08-13 17:24:03', '2011-03-23 02:52:26', 'N');
INSERT INTO `Lookup` VALUES(3272, 'min_max', '0', '0', 'Y', '2010-09-04 06:00:00', '2010-09-04 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(3273, 'min_max', '1', '1', 'Y', '2010-09-04 06:00:00', '2010-09-04 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(3274, 'min_max', '2', '2', 'Y', '2010-09-04 06:00:00', '2010-09-04 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(3275, 'min_max', '3', '3', 'Y', '2010-09-04 06:00:00', '2010-09-04 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(3276, 'min_max', '4', '4', 'Y', '2010-09-04 06:00:00', '2010-09-04 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(3278, 'min_max', '5', '5', 'Y', '2010-09-04 06:00:00', '2010-09-04 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(3279, 'min_max', '6', '6', 'Y', '2010-09-04 06:00:00', '2010-09-04 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(3280, 'min_max', '7', '7', 'Y', '2010-09-04 06:00:00', '2010-09-04 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(3282, 'min_max', '8', '8', 'Y', '2010-09-04 06:00:00', '2010-09-04 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(3283, 'min_max', '9', '9', 'Y', '2004-09-04 06:00:00', '2010-09-16 14:24:44', 'N');
INSERT INTO `Lookup` VALUES(3284, 'date_range', 'Today', 'AND DATE_FORMAT(pickup_dt_tm , "%Y%m%d" ) = DATE_ADD(CURDATE(), INTERVAL- 0 DAY) ', 'Y', '2010-09-14 06:00:00', '2010-10-01 16:00:16', 'N');
INSERT INTO `Lookup` VALUES(3285, 'date_range', 'Yesterday', 'AND DATE_FORMAT(pickup_dt_tm , "%Y%m%d" ) = DATE_ADD(CURDATE(), INTERVAL -1 DAY)', 'Y', '2010-09-14 06:00:00', '2010-10-01 15:57:23', 'N');
INSERT INTO `Lookup` VALUES(3286, 'min_max', '10', '10', 'Y', '2010-09-04 06:00:00', '2010-09-04 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(3287, 'min_max', '11', '11', 'Y', '2010-09-04 06:00:00', '2010-09-04 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(3288, 'min_max', '12', '12', 'Y', '2010-09-04 06:00:00', '2010-09-04 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(3289, 'min_max', '13', '13', 'Y', '2010-09-04 06:00:00', '2010-09-04 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(3290, 'min_max', '14', '14', 'Y', '2010-09-04 06:00:00', '2010-09-04 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(3291, 'min_max', '15', '15', 'Y', '2010-09-04 06:00:00', '2010-09-04 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(3292, 'min_max', '16', '16', 'Y', '2010-09-04 06:00:00', '2010-09-04 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(3293, 'min_max', '17', '17', 'Y', '2010-09-04 06:00:00', '2010-09-04 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(3294, 'min_max', '18', '18', 'Y', '2010-09-04 06:00:00', '2010-09-04 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(3295, 'min_max', '19', '19', 'Y', '2010-09-04 06:00:00', '2010-09-04 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(3296, 'min_max', '20', '20', 'Y', '2010-09-04 06:00:00', '2010-09-04 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(3297, 'status', 'E', 'Executed Properly', 'Y', '2010-10-21 06:00:00', '2010-10-21 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(3298, 'status', 'T', 'Test Order', 'Y', '2010-10-21 06:00:00', '2010-10-21 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(3299, 'cat_id', 'S', 'Side', 'Y', '2010-11-01 06:00:00', '2010-11-01 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(3301, 'cat_id', 'D', 'Drink', 'Y', '2010-11-01 06:00:00', '2010-11-01 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(3302, 'cat_id', 'T', 'Dessert', 'Y', '2010-11-01 06:00:00', '2010-11-01 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(3303, 'cat_id', 'E', 'Entree', 'Y', '2010-11-01 06:00:00', '2010-11-01 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(3305, 'order_del_type', 'L', 'Lennys', 'Y', '2010-12-18 20:26:32', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3309, 'cat_id', 'K', 'Kids', 'Y', '2011-01-22 07:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3311, 'date_range', 'MM-DD-YYYY', 'AND DATE_FORMAT(pickup_dt_tm , "%m-%d-%Y" ) = '' + DAY_NBR + ''', 'N', '2011-01-23 07:00:00', '2011-02-17 21:14:40', 'N');
INSERT INTO `Lookup` VALUES(3337, 'month', '1', 'January', 'Y', '2011-03-21 06:00:00', '2011-03-21 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(3339, 'month', '2', 'February', 'Y', '2011-03-21 06:00:00', '2011-03-21 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(3341, 'month', '3', 'March', 'Y', '2011-03-21 06:00:00', '2011-03-21 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(3343, 'month', '4', 'April', 'Y', '2011-03-21 06:00:00', '2011-03-21 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(3345, 'month', '5', 'May', 'Y', '2011-03-21 06:00:00', '2011-03-21 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(3347, 'month', '6', 'June', 'Y', '2011-03-21 06:00:00', '2011-03-21 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(3349, 'month', '7', 'July', 'Y', '2011-03-21 06:00:00', '2011-03-21 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(3351, 'month', '8', 'August', 'Y', '2011-03-21 06:00:00', '2011-03-21 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(3353, 'month', '9', 'September', 'Y', '2011-03-21 06:00:00', '2011-03-21 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(3355, 'month', '10', 'October', 'Y', '2011-03-21 06:00:00', '2011-03-21 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(3357, 'month', '11', 'November', 'Y', '2011-03-21 06:00:00', '2011-03-21 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(3359, 'month', '12', 'December', 'Y', '2011-03-21 06:00:00', '2011-03-21 06:00:00', 'N');
INSERT INTO `Lookup` VALUES(3360, 'order_del_type', 'W', 'Windows Service', 'Y', '2010-07-13 21:41:15', '2011-05-14 21:05:56', 'N');
INSERT INTO `Lookup` VALUES(3362, 'order_del_type', 'I', 'Outbound Text to Speech', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3364, 'order_del_type', 'G', 'GPRS-Standard', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3366, 'order_del_type', 'GM', 'GPRS-Moes', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3368, 'order_del_type', 'TS', 'GPRS-Trigger SMS', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3370, 'order_del_type', 'FP', 'Fax-PitaPit', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3372, 'order_del_type', 'FE', 'Fax-Check', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3374, 'order_del_type', 'ET', 'Email-T', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3376, 'hour_type', 'D', 'Splickit Delivery Hours', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3378, 'address_type', 'S', 'Shipping', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3380, 'address_type', 'B', 'Billing', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3382, 'current_status', 'off', 'Off', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3384, 'current_status', 'on', 'On', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3386, 'object_type', 'item', 'Item', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3388, 'object_type', 'menu_type', 'Menu Type', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3391, 'time_increments', '20', '20', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3393, 'time_increments', '15', '15', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3395, 'order_del_type', 'GE', 'GPRS- Just Exceptions (Holds and Adds)', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3401, 'holiday', 'O', 'Open', 'Y', '2011-11-16 07:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3403, 'holiday', 'C', 'Closed', 'Y', '2011-11-16 07:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3405, 'status', 'V', 'Void', 'Y', '2010-07-22 19:16:09', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3407, 'pitapit_deal_terms', '599_Subscription', '$599 per Year Subscription', 'Y', '2010-08-13 17:18:13', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3409, 'pitapit_deal_terms', '5_percent_of_orders', '5% of Order Amount for all Online and Mobile Orders', 'Y', '2010-08-13 17:18:13', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3411, 'splickit_agreements', '1059', 'Splickit Merchant Agreement\r\n\r\nThis agreement is made as of its Acceptance on the Splickit Portal ("Effective Date") by and between splick-it, Inc. ("splick-it"), located at 1405 Arapahoe Ave, Boulder, CO 80302 and Pita Pit Franchise Operator  ("Customer").  splick-it agrees to license usage of its proprietary mobile and internet based order and payment system and to provide support services as described below under the terms and conditions of this Agreement.\r\n\r\n1.	splick-it System and Services\r\nsplick-it operates a restaurant ordering and payment system enabling placement of orders by consumers ("End Users") through the  use of splick-itʼs hosted technology platform that utilizes an Internet web portal, iPhone application, and Android application to place, present and pay for menu items (the "splick-it System"). The splick-it System will be branded to the Customer''s specifications.\r\n\r\n2.	splick-it Licenses\r\nDuring the term and subject to the terms and conditions of this Agreement, splick-it hereby grants Customer a non-exclusive, non-transferable, non-sublicensable license to access and use the splick-it System for the purpose of selling selected products to End Users who order those products through the splick-it System. The License granted here-in is for the Customer''s stores listed in Exhibit B. Any use of the splick-it System not expressly permitted in this Agreement is prohibited\r\n\r\n3.	Hardware\r\nIn most locations, Pita Pit will use existing Fax devices for splickit, inc. order presentment.  If applicable, the Customer agrees to purchase from splick-it and install, one SMS printer ("Receiving Device") for each store not able to utilize Fax. splick-it agrees to sell Customer the Receiving Device at splick-it''s cost plus 10%.  It is Customers'' responsibility to care for the Receiving Device and keep it from getting lost, stolen, or misused by employees.   splick-it  will replace or fix any defective Receiving Devices in the first 90 days after activation; after 90 days replacement costs will be the responsibility of the Customer. \r\n\r\n4.	Menu Management\r\nsplick-it will provision the System with Customer''s menus prior to enabling System operation. splick-it understands that pricing and menu items may vary from store to store and will communicate with each store to capture such variances for the initial provisioning.  Splick-it will perform any future menu changes requested by the customer after the initial provisioning at a rate of $50 per hour. Such changes need to be communicated in writing and authorized via purchase order.\r\n\r\n5.	Customer Responsibilities\r\n5.1	Customer agrees to cooperate with splick-it in a timely manner during the initial provisioning period to assure that all items required for enabling the System, including Customer specific branding templates, store locations and contact information, per store ACH information, menus and other items as needed are provided to meet the Provisioning Schedule in Exhibit C.\r\n5.2	Customer agrees to include its mobile/online ordering capabilities provided by splick-it in its regular print, television, radio and digital media marketing campaigns. \r\n5.3	Customer agrees that each of the participating stores will;\r\na) assign sufficient priority to orders presented via the splick-it System  splick-it orders (orders coming through via the splick-it System) ensuring that orders are ready for pick up at time quoted to customer;\r\nb) allocate space within the Customer''s premises for the splick-it System hardware and/or software and ensure that the system is secure from theft and damage;\r\nc) create a well-demarcated "mobile/online pick up area", which allows customers to bypass the traditional line when retrieving their online/mobile pickup orders; \r\nd) ensure that a splick-it System-trained employee is on premises at all times and that all such employees have reviewed the splick-it training materials, and \r\ne) display in-store marketing material promoting the usage of the mobile ordering system.\r\n\r\n6. Fees, Payments, and Taxes.\r\n6.1   In consideration of the licenses and Services provided under this Agreement, Customer will pay splick-it the monthly fees specified in Exhibit A. All Fees are exclusive of, and Customer will be solely responsible for, all taxes applicable to the transactions contemplated by this Agreement, except for any taxes based upon splick-it''s net income. Any payment that is over thirty (30) days late will accrue interest at the rate of 18% per annum, compounded monthly, until paid in full. Notwithstanding any other provision of this Agreement, splick-it may, at its sole election, suspend its provision of and Customers access to the Service without liability to Customer until such time as Customer has made all payments then due.\r\n6.2   Payments from splick-it to the Customers stores will be made via ACH transactions per the frequency set-forth in Exhibit A and shall include the price of goods plus applicable sales taxes paid by the consumer, net of any promotional discount offered by the store, credit card fees, ACH and other payment processing fees. Currently credit card will be charged at Pita Pit prevailing rate at Mercury Payment Systems.  ACH fees are $1.00 per ACH remittance to the Customer''s FO stores when using the standard splick-it settlement process.  Customer is responsible for any credit card charge backs associated with their account and/or transactions.\r\n\r\n7. Intellectual Property\r\n7.1 "splick-it Property" means (a) splick-it''s restaurant ordering technologies, marketing and promotional tools and all related software (including the splick-it System), documentation, ideas, methods, data, databases, software, or invention developed by splick-it in providing the Services and the deliverables resulting from the Services, and (b) any and all derivative works, enhancements or other modifications to any of the above. Subject only to the licenses expressly granted in this Agreement, as between splick-it and Customer, splick-it will be the sole owner of the splick-it Property and all intellectual property rights in and to the splick-it Property.\r\n7.2   For the term of this agreement, Customer grants to splick-it a non-exclusive, non-assignable, royalty-free,paid-up license to use the Customer''s  licensed marks on splick-it''s website and related marketing and advertising materials in order for splick-it to market its services and Customers use of the splick-it System. splick-it will comply with any reasonable trademark usage guidelines provided by Customer in advance.\r\n\r\n8. Confidentiality\r\nEach party will treat the contents of this agreement and any other information exchanged between the parties that is not part of the public domain as confidential information, and will exercise no less than reasonable care with respect to the handling and protection of such information. \r\n\r\n9. Warranties and Disclaimers\r\nsplick-it warrants that the splick-it System and  Services will be delivered in accordance with industry standards, will perform the functions described in this agreement. splick-it is not responsible for the act or omission of any carrier, any limitations imposed by such carrier, or such carrier''s ability or inability to support the Services.  splick-it is not responsible for any limitations of the Internet, for any error made by you in using the Services and is not  liable for any inability to access or use the Services, or any errors, non-conformities, or other problems with the Services, arising from, related to, or caused in whole or in part by any event, circumstance, act or omission outside of splick-it''s control. \r\n\r\n10. Limitation of Liability. \r\nEXCEPT FOR BREACHES OF SECTION 2,3 OR 8, IN NO EVENT WILL EITHER PARTY BE LIABLE FOR ANY INDIRECT, SPECIAL, PUNITIVE, OR CONSEQUENTIAL DAMAGES OF ANY KIND OR NATURE WHATSOEVER, SUFFERED BY THE OTHER PARTY OR ANY THIRD PARTY. splick-it''s total aggregate liability for any damages arising out of or related to this Agreement will not exceed the Fees actually received by splick-it hereunder during the 30 days preceding the conduct giving rise to the claim. The existence of one or more claims will not enlarge this limit. \r\n\r\n11. Term. The term will be one year from the Effective Date and will automatically renew on each anniversary of that date provided that the merchant has not provided an "intent to terminate" in writing with 90 days notice.  Upon the termination of this Agreement for any reason, all licenses granted to Customer will immediately terminate, and any applicable Fees owed by Customer through the date of termination will become due and payable. Upon termination or expiration of the Agreement, Customer will immediately cease using the splick-it System. Notwithstanding the termination of this Agreement for any reason, the rights and duties of the parties under Sections 6-10 will survive such termination and remain in full force and effect.\r\n\r\nPRICING AND PAYMENT TERMS\r\n\r\nBase Service Fees and Billing Arrangements:\r\n\r\n1. Pita Pit Franchise Operator agrees to either\r\na. one time annual fee of $599\r\nOR\r\nb. 5% of all presented orders\r\n2.Pita Pit Franchise Operator agrees that the billing cycle will be annual and automatically renew on each anniversary of the date of agreement unless Franchise Operator provides written notice to splickit, inc. 90 days prior to end of term.\r\n3.If Pita Pit Franchisee chooses not to receive orders via FAX the Franchisee agrees to pay a Hardware fee at 10% over splick-it''s cost (approx. $150) for any location desiring to receive orders via splick-it''s GPRS (General Packet Radio Service text based device. Also available to Pita Pit USA franchisees wishing to use existing web connected computers, may access splick-it''s proprietary "web based order delivery" software for no charge (connected printers if desired will be at cost plus 10%).\r\n4.splick-it, inc agrees to provide daily/weekly/monthly reporting to Pita Pit USA and Franchise Operator detailing the transaction information of each franchise location participating in the Platform.\r\n5. splick-it, inc agrees to pay Pita Pit USA participating franchisees for all orders rendered through the Platform on a weekly basis by ACH to the account provided by each participating franchisee or directly via Mercury existing platform for cash transfers. If Mercury Payments is the Franchise Operator payment processor, Franchise Operator agrees to provide ACH for billing collection.\r\n6. Pita Pit USA agrees to pay for each participating Franchise Operator store, a basic signage package, as approved by Pita Pit Marketing (bag stuffers, Pick Up Signs, Window decals etc.) at approximately $100/ store from the Pita Pit USA Marketing fund. Any additional signage will be charged to the Franchise Operator at the prevailing market rates.\r\n', 'Y', '0000-00-00 00:00:00', '2011-11-29 03:45:55', 'N');
INSERT INTO `Lookup` VALUES(3413, 'pitapit_deal_processor', 'mercury', 'Mercury Payments', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3415, 'pitapit_deal_processor', 'splickit', 'Splickit Payments', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3417, 'splickit_agreements', '11115', 'Merchant Agreement - Chicago Test\r\n\r\nTerms & Conditions\r\n(version 09.08.11)\r\n\r\nWhereas, Merchant (as defined below) has submitted to splick-it, Inc. (''splick-it'') a Merchant Agreement (''Agreement'') consisting of an on-line application (including a direct-debit authorization) submitted by Merchant at and these terms and conditions;\r\n\r\nNow, therefore, in consideration of the mutual covenants contained herein, and for other good and valuable consideration, the receipt and sufficiency of which are hereby acknowledged, Merchant and splick-it hereby agree that the Agreement will be governed by these terms and conditions:\r\n\r\n1. splick-it System and Services; Merchant Agreement and Acceptance.\r\n1.1  splick-it is willing to provide Merchant access to and use of its remote restaurant ordering system, technology, and related services described below (the ''Services'') on the condition that Merchant accepts all of the terms and conditions contained in the Agreement.  splick-it operates a restaurant ordering and payment system enabling placement of orders by consumers (''End Users'') through the use of splick-it?s hosted technology platform that utilizes an Internet web portal, iPhone application, and Android application to place orders, and pay for them (the ''splick-it System'').  Collectively, the splick-it System and Services are the ''splick-it Solution.''  In order for splick-it to provide these Services, Merchant will comply with the merchant responsibilities as set forth in Schedule 1 (''Merchant Responsibilities'') set forth below. Merchant acknowledges that splick-it is continually enhancing and evolving its splick-it System and that Merchant participation, cooperation, and feedback are essential to the evolution of the service.\r\n1.2  ''Merchant'' means a QuiznosÂ® franchise owner that has submitted a Merchant Agreement to splick-it subject to acceptance by splick-it.  Subject to the terms and conditions of the Agreement, and to acceptance of the Agreement by splick-it, splick-it will allow Merchant access to and use of the splick-it System.  splick-it will notify Merchant whether splick-it has accepted the Agreement and the Agreement will take effect upon such notice.  \r\n\r\n2. splick-it Licenses. During the term and subject to the terms and conditions of the Agreement, splick-it hereby grants Merchant a non-exclusive, non-transferable, non-sublicensable license to access and use the splick-it System for its internal business purpose of selling selected products to End Users who order those products through the splick-it System. Any use of the splick-it System not expressly permitted in the Agreement is prohibited. Except as expressly permitted in the Agreement, Merchant will not, and will not allow or authorize any third party to: (i) allow use of or access to the splick-it System or sublicense, lease, (including operation of a time sharing service or service bureau), transfer or assign its rights to access and use the splick-it System, in whole or in part, to a third party; (ii) alter or otherwise modify the splick-it System, or disassemble, reverse engineer or otherwise attempt to derive the source code of the splick-it System; (iii) remove or destroy any proprietary markings, confidential legends or any trademarks, trade names or brand names of splick-it placed upon or contained within the splick-it System; or (iv) post or transmit into the splick-it System any information or software which contains a virus, cancelbot, Trojan horse, worm or other harmful component. Merchant may allow its employees to access and use the splick-it System (the ''Authorized Users'') in accordance with the license grant and restrictions in this Section 2. Merchant will prohibit Authorized Users from disclosing any Merchant access codes provided by splick-it to any third party. Merchant is solely responsible for the acts and omissions of its Authorized Users with regard to their use of the splick-it System.\r\n\r\n3. Hardware; Order Escalation.\r\n3.1  For each Merchant in the United States, splick-it will, upon the request of Merchant and for a fee of $150 (''Receiving Device Fee''), make available to the Merchant a SMS printer (''Receiving Device'') to receive orders processed through splick-it.  It is Merchants'' responsibility to care for the Receiving Device and keep it from getting lost, stolen, or misused by employees.  When using an SMS device, Merchant will be responsible to pay for all text charges that are not authorized or related to the splick-it service.  splick-it will replace or fix any defective Receiving Devices in the first 90 days after activation; after 90 days Merchant will pay for any replacement Receiving Devices.  If splick-it does not roll splick-it services out to the entire QuiznosÂ® chain on or before November 30, 2011, Merchant may choose to return the Receiving Device in full working order to splick-it for a full refund of the Receiving Device Fee.\r\n3.2  splick-it monitors all order delivery to the Merchants and if an order transmission fails, splick-it escalates Merchant notification by contacting the Merchant''s restaurant directly.  If the escalation fails, splick-it will notify the End User via email that End User''s transaction could not be delivered to the Merchant and End-User will not be charged.\r\n\r\n4. Fees, Payment, and Taxes.\r\n4.1 In consideration of the licenses and Services provided under the Agreement, Merchant will work with splick-it and Franchisor (as defined below) in good faith to test the splick-it System and splick-it will not charge any fees (other than the Receiving Device Fee, if applicable, and Processing Fees) to Merchant for the Services during the term of the Agreement. Notwithstanding the waiver of fees, Merchant will be solely responsible for all taxes applicable to the transactions contemplated by the Agreement, except for any taxes based upon splick-it''s net income. splick-it can elect at its sole discretion to net any amounts due from Merchant against payments due to the Merchant. Any payment that is over thirty (30) days late will accrue interest at the rate of 18% per annum, compounded monthly, until paid in full. Notwithstanding any other provision of the Agreement, splick-it may, at its sole election, suspend its provision of, and Merchant''s access to, the splick-it Solution without liability to Merchant until such time as Merchant has made all payments then due.\r\n4.2 The amount each End User is charged for items purchased through the splick-it System is set by Merchant. The List Price of products sold by Merchant may be updated by Merchant via a web portal hosted by splick-it and will be published immediately by splick-it. Other than as described herein, each party will be responsible for its own costs and expenses incurred in performing its obligations hereunder. splick-it will provide Merchant and Franchisor with a weekly summary of End User purchases, and splick-it will pay Merchant the amounts earned from End User purchases by check or ACH weekly (each weekly summary and weekly payment will cover all End User purchases during the prior week). Merchant is responsible to pay any transaction fees incurred by any third party payment processor required to receive payment as well as a processing fee (''Processing Fee'') to splick-it of $1.00 per weekly payment. splick-it reserves the right at any time to change its third party payment processor. The Merchant further acknowledges that splick-it will not be responsible or have any liability for paying Merchant for End User orders to the extent that the orders are incorrectly filled by Merchant or unduly delayed by Merchant. At its sole discretion, Merchant may issue a refund, in whole or in part, to an End User who purchased a product through the splick-it System, provided however, splick-it will not reimburse Merchant or be liable in any way for such refunds if splick-it  has properly processed the order. Merchant understands and agrees that splick-it may charge End Users a membership fee and/or a per transaction fee to use the splick-it System.  splick-it warrants that the prices displayed to End Users will be accurately reflect pricing information provided by Franchisor and Merchant, that splick-it will collect the proper amount (including applicable sales taxes) from End Users and remit the proper amount (including sales taxes) to Merchant.\r\n4.3 splick-it requires credit card payment from End User at the time of sale. Merchant understands and agrees that splick-it will pass through payment processing fees associated with End User transactions through splick-it''s payment gateway, and these payment processing fees will be reflected on the weekly summary.  splick-it is PCI certified, but does not maintain consumers credit card information on its servers nor will it provide any consumer credit card information to the Merchant during the order process or at any other time.  Merchant agrees that the pass through rate for payment processing is currently 29 cents per transaction and 2.69% of the transaction amount, but at no time will the pass through rate be more than 0.2% higher than splick-it?s actual cost.  Merchant is not responsible for any credit card charge backs associated with their account and/or transactions.\r\n\r\n5. Intellectual Property.\r\n5.1 ''splick-it Property'' means (a) splick-it?s restaurant ordering technologies, marketing and promotional tools and all related software (including the splick-it System), documentation, ideas, and methods, (b) End User Data (as defined below), and any other data, databases, software, or invention developed by splick-it in providing the Services and the deliverables resulting from the Services, and (c) any and all derivative works, enhancements or other modifications to any of the above. Subject only to the licenses expressly granted in the Agreement, as between splick-it and Merchant, splick-it will be the sole owner of the splick-it Property and all intellectual property rights in and to the splick-it Property.\r\n5.2 Trademark; Collateral.\r\na) [intentionally omitted]\r\nb) splick-it may provide Merchant with marketing and advertising collateral (''Collateral'') during the term of the Agreement which may contain splick-it''s trademarks, trade names, logos, or service marks (''Marks''). Subject to the limitations set forth below, splick-it grants to Merchant a non-exclusive, non-transferable, non-sublicenseable license to display the Collateral and Marks only in connection with Merchant''s use and promotion of the splick-it Solution in Merchant''s restaurant as directed by splick-it. Merchant agrees and acknowledges that it has no interest in the Collateral or Marks other than the license granted under the Agreement and that it will not obtain any rights in or to any of the Collateral or Marks through its use its use of the splick-it System. Merchant further agrees that, as between splick-it and Merchant, splick-it is and will continue to be the sole and exclusive owner of all right, title and interest in and to the Collateral and Marks in any form or embodiment thereof and agrees that all goodwill associated with or attached to the Collateral and Marks arising out of the use thereof by Merchant will inure to the benefit of splick-it. Merchant will display the Collateral and Marks only in the form provided by splick-it and as reasonably directed by splick-it. Merchant will not combine Collateral or Marks with any other trademarks or logos (other than trademarks or logos of the QuiznosÂ® brand). Merchant will not do anything that might harm the reputation or goodwill associated with the Marks. Merchant will immediately cease the display of Collateral or Marks upon splick-it''s request. Merchant will not contest or challenge splick-it''s ownership of the Marks, and Merchant will not register or attempt to register any Mark or other trademark that is similar to a Mark in any jurisdiction. Upon termination or expiration of the Agreement, Merchant will promptly destroy all Collateral.\r\n	\r\n6. Confidentiality. ''Confidential Information'' means (a) any business or technical nonpublic information of Merchant or splick-it, including but not limited to any information relating to either party''s products or services, (b) any other information of Merchant or splick-it that is specifically designated by the disclosing party as confidential or proprietary or which a receiving party should recognize, by its nature, as being confidential, and (c) the terms and conditions of the Agreement, except that the definition of Confidential Information will not include information that (i) is in or enters the public domain without breach of the Agreement through no fault of the receiving party, (ii) the receiving party was demonstrably in possession of prior to first receiving it from the disclosing party, (iii) the receiving party can demonstrate was developed by the receiving party independently and without use of or reference to the disclosing party Confidential Information, or (iv) the receiving party receives from a third party without restriction on disclosure and without breach of a nondisclosure obligation. Each party will maintain the Confidential Information of the other party in strict confidence until it falls under one of the exceptions (i) - (iv) listed above, and will exercise no less than reasonable care with respect to the handling and protection of such Confidential Information. Each party will use the Confidential Information of the other party only during the Term and only to perform obligations or exercise rights under the Agreement, and will disclose such Confidential Information only to its employees and independent contractors as is reasonably required in connection with the exercise of its rights and obligations under the Agreement (and only subject to binding use and disclosure restrictions at least as protective as those set forth herein executed in writing by such employees or independent contractors). splick-it hereby designates the splick-it Solution, including the splick-it System, and End User Data, as splick-it Confidential Information. The terms and conditions of the Agreement will be deemed Confidential Information of both parties.  Notwithstanding the foregoing, no disclosure of any information by either party to Franchisor will be deemed a breach of the provisions of this Section 6.\r\n	\r\n7. Warranties and Disclaimers.\r\n7.1 Merchant Warranty. Merchant warrants that the Merchant Responsibilities will be performed in a professional manner in accordance with industry standards, and with reasonable skill and care.\r\n7.2 splick-it Disclaimer Warranty. Unless otherwise set forth herein:  (a)  THE SPLICK-IT SOLUTION IS PROVIDED ON AN ''AS IS'' AND ''AS AVAILABLE'' BASIS, WITHOUT WARRANTY OF ANY KIND; (b) SPLICK-IT EXPRESSLY DISCLAIMS ALL WARRANTIES OF ANY KIND, WHETHER EXPRESS, IMPLIED, OR STATUTORY, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR USE OR PURPOSE, TITLE, OR NON-INFRINGEMENT; (c)  SPLICK-IT MAKES NO WARRANTY THAT THE SERVICES WILL MEET MERCHANT''S REQUIREMENTS, OR THAT THE SERVICES WILL BE UNINTERRUPTED, TIMELY, SECURE, OR ERROR FREE; and (d) SPLICK-IT MAKES NO WARRANTY REGARDING ANY DEALINGS WITH OR TRANSACTIONS ENTERED INTO WITH ANY OTHER PARTIES THROUGH THE SERVICES.\r\n7.3 Disclaimers. Merchant acknowledges and agrees that (i) the availability of SMS, mobile data networks, and the Internet may affect Merchant''s ability to use the Services, (ii) delivery of SMS messages, internet access and availability of web-based browsing is not guaranteed, (iii) the Services may differ depending on the carrier with whom an End User maintains an account and that carrier''s ability to support the Services, (iv) splick-it is not responsible for the act or omission of any carrier (including failure to deliver any communication to splick-it or a provider of any product in timely fashion), any limitations imposed by such carrier, or such carrier''s ability or inability to support the Services, (v) splick-it is not responsible for any communications failure resulting from any limitations of the Internet, (vi) splick-it is not responsible for any error made by Merchant in using the Services, and (vii) splick-it is not responsible and will not be liable for any inability to access or use the Services, or any errors, non-conformities, or other problems with the Services, arising from, related to, or caused in whole or in part by any event, circumstance, act or omission outside of splick-it''s control.\r\n7.4 splick-it Warranty. splick-it warrants that the Services will be performed in a professional manner in accordance with industry standards, and with reasonable skill and care and splick-it will be responsible for any act or omission of any agent of splick-it. splick-it further warrants that the Services and splick-it Solution, and the use thereof by Merchant in accordance with the provisions of the Agreement, will not infringe upon any intellectual property rights of any third party. splick-it further warrants that splick-it will comply with the provisions of any and all privacy policies provided by splick-it to any consumer.  splick-it  further warrants as follows:\r\n(A)	As used in this Section 7.4, the following terms have the meaning set forth below:\r\n(i)	''PCI-DSS'' will mean the Payment Card Industry Data Security Standard, version 1.2, released October 2008, as such may be amended, modified or supplemented from time to time.\r\n(ii)	''Cardholder Data'' will have the meaning provided in the PCI-DSS, which will include all data elements described therein.\r\n(iii)	''Sensitive Authentication Data'' will have the meaning provided in the PCI-DSS, which will include all data elements described therein.\r\n(B)	In connection with its performance of the Services hereunder, splick-it  acknowledges and warrants that:  (i) splick-it  is responsible for the security of any and all Cardholder Data that splick-it , at any time, stores, processes, transmits, or possesses; and (ii) notwithstanding the foregoing, splick-it  will not store any Sensitive Authentication Data after such data has been used for the process of authorizing a payment, regardless of whether such Sensitive Authentication Data is encrypted or not.\r\n(C)	No less than once each calendar year, splick-it will obtain, at its sole cost and expense, validation of compliance of the Services with the PCI-DSS by a Qualified Security Assessor (''QSA'') (as defined in the PCI-DSS) (''Third Party PCI Validation''). Upon receipt of such Third Party PCI Validation, if requested by Merchant, splick-it will provide Merchant with a copy of the Report On Compliance (''ROC'') or Certificate of Merchant Compliance (''CMC'') provided to splick-it by the QSA as a result of such Third Party PCI Validation.  In addition, splick-it will, upon Merchant''s reasonable request, provide Merchant with such information and documentation as is reasonably necessary to confirm splick-it ''s continued compliance with the PCI-DSS during the intervening period between each annual Third Party PCI Validation.\r\n(D)	splick-it  will diligently monitor such systems, networks, servers, applications, security procedures, and operations, and all components thereof, that store, transmit or possess any Cardholder Data (''PCI Environment'') during the intervening period between Third Party Validations to ensure continued compliance with the PCI-DSS.  In the event of any intervening failure by the PCI Environment to maintain compliance with the PCI-DSS, splick-it will promptly provide Merchant with information regarding such failure along with a detailed description of the actions and steps that splick-it intends to take to correct such failure (''Remediation Plan'') and a projected date upon which splick-it expects to have completed such Remediation Plan.  Notwithstanding the foregoing, splick-it  acknowledges and agrees that it is solely responsible for monitoring its PCI Environment and ensuring continuous compliance with the PCI-DSS and that nothing contained in the Agreement will be construed as an assumption on the part of Merchant of any responsibility for splick-it ''s obligations in connection therewith.\r\n(E)	splick-it  further agrees and warrants that in the event: (i) splick-it  fails to obtain or maintain a Third Party PCI Validation or fails to deliver the ROC or CMC to Merchant as required herein (''Potential PCI Noncompliance''), or (ii) of any (1) breach of security with respect to any Cardholder Data in its possession, (2) unauthorized possession of the Cardholder Data in its possession, and/or (3) any unauthorized use or knowledge of the Cardholder Data in its possession (each a ''Cardholder Data Security Breach''), Merchant, Franchisor, a Payment Card Industry (''PCI'') representative and/or a PCI approved third party (together or individually, the ''PCI Auditor''), may conduct a thorough review of splick-it ''s books, records, files, computer processors, equipment, systems, physical and electronic log files, and facilities relating to the Services for purposes of investigating the Cardholder Data Security Breach or Potential PCI Noncompliance and validating compliance with the PCI-DSS (the ''Audit'').  splick-it further agrees that it will provide the PCI Auditor with full cooperation and access to allow them to complete the Audit.  In the event that any such Audit identifies any failure of the splick-it facilities, policies, processes, procedures, or practices to comply with the PCI-DSS, splick-it will promptly repair and/or remedy any such issues to bring the splick-it into compliance with the PCI-DSS and deliver written notice of such remedy to Merchant.  Notwithstanding the foregoing, splick-it  agrees and acknowledges that nothing contained herein will be construed as an assumption by Merchant of any responsibility or liability for splick-it ''s compliance obligations and splick-it  will remain, and hereby agrees to be, solely liable and responsible for its own compliance and for any costs, liabilities, damages or expenses incurred by any party, including Merchant and Franchisor, arising out of any failure by splick-it  to provide its ROC or CMC or out of any Cardholder Data Security Breach.\r\n(F)	splick-it  will (i) immediately notify Merchant in writing of any Cardholder Data Security Breach, and (ii) promptly furnish Merchant full details of such Cardholder Data Security Breach and cooperate with Merchant in any action or proceeding as may be deemed necessary by Merchant to protect the Cardholder Data.\r\n\r\n8. Limitation of Liability. EXCEPT FOR liability arising from BREACHES OF SECTION 2, 3 or 6, or unless liability arises under either party''s indemnification obligations set forth herein:\r\n(A)	IN NO EVENT WILL EITHER PARTY BE LIABLE FOR ANY INDIRECT, SPECIAL, PUNITIVE, OR CONSEQUENTIAL DAMAGES OF ANY KIND OR NATURE WHATSOEVER, SUFFERED BY THE OTHER PARTY OR ANY THIRD PARTY, INCLUDING, WITHOUT LIMITATION, LOST PROFITS, BUSINESS INTERRUPTIONS OR OTHER ECONOMIC LOSS ARISING OUT OF OR RELATED TO THIS AGREEMENT OR ANY USE OF OR FAILURE TO BE ABLE TO USE THE SPLICK-IT SYSTEM, SERVICES OR DATABASES;\r\n(B)	SPLICK-IT WILL NOT BE LIABLE FOR ANY DAMAGES ARISING OUT OF OR RELATED TO (i) THE ACCURACY OR COMPLETENESS OF INFORMATION SUPPLIED BY MERCHANT OR END USERS; OR (ii) FOR PRODUCTS PROVIDED BY MERCHANT TO END USERS ORDERING THROUGH THE SPLICK-IT SYSTEM, WHETHER SUFFERED BY MERCHANT OR ANY THIRD PARTY;\r\n(C)	either party''s total aggregate liability for any damages arising out of or related to the Agreement will not exceed the Fees actually accrued by splick-it hereunder during the 30 days preceding the conduct giving rise to the claim;\r\n(D)	the existence of one or more claims will not enlarge these limitations on liability; and\r\n(E)	the parties acknowledges that splick-it?s pricing reflects this allocation of risk and the limitation of liability specified in this Section will apply regardless of whether any limited or exclusive remedy specified in the Agreement fails of its essential purpose.\r\n\r\n9. Term. The term of the Agreement will end on October 30, 2011.  The parties agree that the Agreement has been entered into for purposes of working with Franchisor to determine whether the Services should be made available to all QuiznosÂ® restaurants and that, if Franchisor so determines and so advises the parties in writing, the term of the Agreement will be extended to November 30, 2011 to allow Franchisor and splick-it the opportunity to roll the Services out to all QuiznosÂ® restaurants without interrupting service to Merchant or to End Users.  Upon the termination of the Agreement for any reason, all licenses granted to Merchant will immediately terminate, and any amounts owed by Merchant through the date of termination will become due and payable. Upon termination or expiration of the Agreement, Merchant and its Authorized Users will immediately cease using the splick-it System, splick-it Service and the splick-it Confidential Information and will return to splick-it the Receiving Device and destroy any splick-it Confidential Information and Collateral. Notwithstanding the termination of the Agreement for any reason, the rights and duties of the parties under Sections 4 and 6 - 12 will survive such termination and remain in full force and effect.  Any provision intended by its nature to survive the termination of the Agreement will survive the termination of the Agreement.  Merchant may terminate the Agreement at any time without cause and without penalty upon written notice to splick-it.  Franchisor may terminate the Agreement, without cause and without penalty to Franchisor or Merchant, at any time upon written notice to Merchant and splick-it.\r\n	\r\n10. End User Data; Privacy Policies. ''End User Data'' means any information provided by an End User to splick-it, information otherwise submitted by an End User through the splick-it Solution, regardless of medium, or other information collected by splick-it related to an End User?s use of the splick-it Solution, including, without limitation, account information, billing or credit information, information about an End User?s usage of the splick-it Solution, and personally identifiable information. The parties agree that all End User Data will be owned by splick-it, and to the extent Merchant has now or in the future obtains any right to End User Data, Merchant hereby irrevocably assigns to splick-it all right, title and interest in and to the End User Data including all related intellectual property rights.  Notwithstanding any other provision of the Agreement, Merchant agrees that, unless expressly authorized in writing by splick-it, end user Data will be used by Merchant only for the specific use contemplated in connection with Merchant''s use of the splick-it System. splick-it makes no representation or warranty as to the accuracy or completeness of the End User Data. Merchant will implement adequate and reasonable technical, physical, and procedural safeguards to protect the End User Data from unauthorized use or disclosure.\r\n\r\n11. Miscellaneous. The parties are independent contractors and nothing in the Agreement will be construed to constitute either party as the agent of the other party for any purpose whatsoever. The Agreement, and any attached Schedules set forth the entire agreement between the parties and supersede any and all prior agreements or representations, written or oral, of the parties with respect to the subject matter of the Agreement. The Agreement may only be amended by a subsequent written agreement executed by both parties. No provisions printed on any invoice will supersede the provisions of the Agreement. No failure or delay by either party in exercising any right hereunder will operate as a waiver thereof. Merchant will not assign the Agreement without the prior written consent of splick-it, which consent will not be unreasonably withheld. The Agreement will be interpreted and construed under the laws of Colorado and any dispute between the parties or their officers, directors, employees, shareholders, members, managers or authorized agents will be governed by and determined in accordance with the substantive law of the State of Colorado, which laws will prevail in the event of any conflict of law.  Each party hereby waives any right it may have to a trial by jury for any disputes arising from the Agreement or the parties'' relationship created hereby.  In the event of any dispute between the parties based upon an alleged breach or default in their respective obligations to be fulfilled pursuant to the Agreement, the prevailing party therein will be entitled to recover reasonable attorney fees (including the cost of in-house counsel) and court costs from the non-prevailing party.  The parties agree that any breach of either party?s obligations under Sections 2 and 6 may result in irreparable injury to the other party for which there is no adequate remedy at law. Therefore, in the event of any breach or threatened breach of such obligations, the nonbreaching party will be entitled to seek equitable relief in addition to its other available legal remedies in a court of competent jurisdiction. If any provision of the Agreement is found invalid or unenforceable by an arbitrator or court of competent jurisdiction, the remaining portions will remain in full force and effect. All notices required by the Agreement to be given to splick-it will be (i) addressed to splick-it at the addresses set forth below or at such other address as splick-it may designate in writing from time to time, (ii) in writing, and (iii) deemed given three business days after being mailed, postage prepaid via certified U.S. Mail.  All notices required by the Agreement to be given to Merchant will be (i) addressed to Merchant at Merchant''s restaurant location or at such other address as Merchant may designate in writing from time to time, (ii) in writing, and (iii) deemed given three business days after being mailed, postage prepaid via certified U.S. Mail.\r\n	\r\n12.  Publicity.  Neither party will issue any press releases or public announcements or marketing materials regarding the subject matter hereof or the relationship between the parties unless such press release or public announcement has been approved in advance in writing by the other party and by Franchisor.\r\n\r\n13.  Insurance.  splick-it  will maintain the following insurance throughout the term of the Agreement :  (a) Commercial General Liability insurance including operations, premises, products, completed operations, independent contractors, contractual and personal injury:  limits of $1 million bodily injury and property damage combined; and (b) Errors & Omissions / Professional Liability insurance with $1 million each claim limit covering all professional services provided to Client.\r\n\r\n14.  Franchisor.  splick-it  understands and agrees that QuiznosÂ® restaurants are independently owned and operated by franchise owners that operate the restaurants pursuant to franchise agreements between the franchise owners and TQSC II LLC or its affiliates (collectively ''Franchisor'').\r\n\r\n15.  Third Party Information.  splick-it warrants that its performance under the Agreement will not violate any agreement or contractual right with any other person or entity.  splick-it  understands and agrees that splick-it  will not use, rely upon or disclose any trade secrets or other proprietary or confidential documents or information belonging to third parties (collectively ''Third Party Information'') which splick-it  may have in splick-it  possession or may otherwise have access to.  Additionally, splick-it will not undertake any task on Merchant''s behalf that would result in splick-it ''s use, reliance upon or disclosure of any Third Party Information.\r\n\r\n16.  Indemnification.  Notwithstanding the provisions of Sections 7.3 and 17, splick-it  hereby indemnifies Merchant and Franchisor and their respective affiliates and all of the shareholders, directors, officers, members, managers, employees and agents of all of the foregoing from and against and harm or claim that arises from:  (i) any breach by splick-it  of any of its representations or warranties herein; (ii) any negligent act or omission or act of malfeasance by splick-it  or any agent of splick-it ; and/or (iii) splick-it ''s or its agents'' possession, storage, transmission or use of Cardholder Data or use by the splick-it  or its agents of any Sensitive Authentication Data beyond that permitted by the PCI-DSS, or failure by splick-it  or its agents to delete such Sensitive Authentication Data in accordance with the PCI-DSS, regardless of whether due to negligence or malfeasance.\r\n\r\n17.  Force Majeure.  If the performance of the Agreement, except the making of payments hereunder, is prevented or interfered with by a Force Majeure (any act or condition whatsoever beyond the reasonable control of and not occasioned by the fault or negligence of the affected party, including but not limited to, Internet or telecommunications failures), the party so affected will be excused from such performance to the extent of such prevention or interference.\r\n\r\nsplick-it address:\r\n\r\nsplick-it, Inc.\r\nAttn:  Tom Plunkett\r\n1405 Arapahoe Ave.\r\nBoulder, CO  80302\r\nSchedule 1\r\nto Terms & Conditions\r\n	\r\nMerchant Responsibilities\r\n\r\nA. Merchant may elect to receive orders via a fax machine or via an on-line application (e.g., Windows Application Report Printing Service), or Merchant may elect to obtain a Receiving Device from splick-it in accordance with Section 3 of these terms and conditions.  Merchant is solely responsible for all third party services and charges, including telephone, broadband, or other data services that are necessary for Merchant to receive SMS messages from the splick-it System. If Merchant elects to use a Receiving Device, standard text rates will apply and Merchant will train employees on how to use the Receiving Device and not allow employees to use the Receiving Device to send or receive non-splick-it-related text messages. \r\nB. Merchant will provide ACH banking information necessary to receive splick-it payments and to pay applicable splick-it fees.\r\nC. Merchant may, from time to time, receive End User complaints, questions, or concerns with Merchant?s products, services, or prices. If Merchant believes the End User dispute was caused by the splick-it System, Merchant may contact splick-it, and the parties will cooperate in good faith to resolve the dispute.\r\nD. Merchant will perform the following tasks and responsibilities while the Agreement is in effect: (i) process splick-it orders (orders coming through via the splick-it System) with the utmost in speed, accuracy and quality, ensuring that orders are made and either ready for pick up at time quoted to End User; (ii) allocate space within the Merchant''s premises for the splick-it System hardware and/or software and ensure that the system is secure from theft and damage; (iii) if applicable, link the splick-it System to a reliable, high-speed Internet connection (not wireless or dial-up); (iv) create a well-demarcated mobile pick up area, which allows End Users to bypass the traditional line when retrieving their mobile pickup orders; and (v) ensure that a splick-it System-trained employee is on premises at all times and that all such employees have reviewed the splick-it training materials.\r\nE. Merchant will (i) allocate space within the Merchant''s premises for mobile marketing materials, including window decal(s) on entrances, etc.; (ii) display any of the Collateral provided to Merchant by splick-it; (iii) promote the splick-it mobile solution to customers by handing out promotional materials and information to all customers at the point of sale and in delivery bags.  splick-it will pay for and provide the aforementioned marketing materials and collateral material to Merchant.', 'Y', '0000-00-00 00:00:00', '2011-11-29 03:47:32', 'N');
INSERT INTO `Lookup` VALUES(3418, 'credit_type', 'S', 'Reversal after paid to merchant', 'Y', '0000-00-00 00:00:00', '2012-02-10 18:59:52', 'N');
INSERT INTO `Lookup` VALUES(3420, 'credit_type', 'G', 'Reversal within current  billing cycle', 'Y', '0000-00-00 00:00:00', '2012-02-10 18:59:52', 'N');
INSERT INTO `Lookup` VALUES(3422, 'merchant_menu_type', 'delivery', 'Delivery Menu', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3424, 'merchant_menu_type', 'pickup', 'Pickup Menu ', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3426, 'status', 'F', 'Failed', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3428, 'cc_processor', 'I', 'InspirePay (Splickit Default)', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3430, 'cc_processor', 'M', 'Mercury Payments', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3432, 'inc_trans_pay_cycle', 'W', 'Weekly', 'Y', '0000-00-00 00:00:00', '2012-02-03 17:41:31', 'N');
INSERT INTO `Lookup` VALUES(3434, 'inc_trans_pay_cycle', 'b', 'Bi-Monthly', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3436, 'inc_trans_pay_cycle', 'M', 'Monthly', 'Y', '0000-00-00 00:00:00', '2012-02-03 17:41:31', 'N');
INSERT INTO `Lookup` VALUES(3438, 'cat_id', 'b', 'Breakfast', 'Y', '0000-00-00 00:00:00', '2012-02-07 21:04:41', 'N');
INSERT INTO `Lookup` VALUES(3440, 'moes_fbc', '1', 'S. Florida', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3442, 'moes_fbc', '2', 'Company Stores', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3444, 'moes_fbc', '3', 'N. Florida / S. GA', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3446, 'moes_fbc', '4', 'Mid West - North', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3448, 'moes_fbc', '5', 'North Carolina', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3450, 'moes_fbc', '6', 'ATL', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3452, 'moes_fbc', '7', 'Mid Atlantic', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3454, 'moes_fbc', '8', 'Upsate NY / E. Ohio', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3456, 'moes_fbc', '9', 'Northeast', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3458, 'moes_fbc', '10', 'ATL / E. Tenn', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3460, 'moes_fbc', '11', 'Midwest South', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3462, 'moes_fbc', '12', 'South Carolina', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3464, 'order_del_type', 'WS', 'Windows Service', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3470, 'order_del_type', 'O', 'OPIE', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3472, 'message_type', 'I', 'Information Only', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3474, 'message_type', 'A', 'Activate Printer', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3476, 'credit_type', 'P', 'Partial reversal within current  billing cycle', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3478, 'credit_type', 'A', 'Partial reversal after paid to merchant', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3480, 'order_del_type', 'IB', 'Auto Repower Device Call', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3482, 'yes_no_LTO', 'Y', 'Yes', 'Y', '2010-08-13 17:18:13', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3484, 'yes_no_LTO', 'N', 'No', 'Y', '2010-08-13 17:18:13', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3486, 'yes_no_LTO', 'L', 'LTO', 'Y', '2010-08-13 17:18:13', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3488, 'locale', 'Dummy', 'Place Holder. Should always be 0.00', 'Y', '2010-07-14 16:26:11', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3489, 'order_del_type', 'CC', 'Call Center', 'Y', '2010-07-13 21:41:15', '2010-07-14 20:58:56', 'N');
INSERT INTO `Lookup` VALUES(3490, 'payment_cycle', 'W', 'Weekly', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3492, 'payment_cycle', 'M', 'Monthly', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3493, 'modifier_type', 'I', 'Interdependent', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3494, 'order_del_type', 'W2', 'Winapp2`', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3495, 'min_max', '21', '21', 'Y', '2012-07-03 21:08:29', '2012-07-03 21:08:29', 'N');
INSERT INTO `Lookup` VALUES(3496, 'min_max', '22', '22', 'Y', '2012-07-03 21:08:29', '2012-07-03 21:08:29', 'N');
INSERT INTO `Lookup` VALUES(3497, 'min_max', '23', '23', 'Y', '2012-07-03 21:08:29', '2012-07-03 21:08:29', 'N');
INSERT INTO `Lookup` VALUES(3498, 'min_max', '24', '24', 'Y', '2012-07-03 21:08:29', '2012-07-03 21:08:29', 'N');
INSERT INTO `Lookup` VALUES(3499, 'min_max', '25', '25', 'Y', '2012-07-03 21:08:29', '2012-07-03 21:08:29', 'N');
INSERT INTO `Lookup` VALUES(3500, 'min_max', '26', '26', 'Y', '2012-07-03 21:08:29', '2012-07-03 21:08:29', 'N');
INSERT INTO `Lookup` VALUES(3501, 'min_max', '27', '27', 'Y', '2012-07-03 21:08:29', '2012-07-03 21:08:29', 'N');
INSERT INTO `Lookup` VALUES(3502, 'min_max', '28', '28', 'Y', '2012-07-03 21:08:29', '2012-07-03 21:08:29', 'N');
INSERT INTO `Lookup` VALUES(3503, 'min_max', '29', '29', 'Y', '2012-07-03 21:08:29', '2012-07-03 21:08:29', 'N');
INSERT INTO `Lookup` VALUES(3504, 'min_max', '30', '30', 'Y', '2012-07-03 21:08:29', '2012-07-03 21:08:29', 'N');
INSERT INTO `Lookup` VALUES(3505, 'min_max', '31', '31', 'Y', '2012-07-03 21:08:29', '2012-07-03 21:08:29', 'N');
INSERT INTO `Lookup` VALUES(3506, 'min_max', '32', '32', 'Y', '2012-07-03 21:08:29', '2012-07-03 21:08:29', 'N');
INSERT INTO `Lookup` VALUES(3507, 'min_max', '33', '33', 'Y', '2012-07-03 21:08:29', '2012-07-03 21:08:29', 'N');
INSERT INTO `Lookup` VALUES(3508, 'min_max', '34', '34', 'Y', '2012-07-03 21:08:29', '2012-07-03 21:08:29', 'N');
INSERT INTO `Lookup` VALUES(3509, 'min_max', '35', '35', 'Y', '2012-07-03 21:08:29', '2012-07-03 21:08:29', 'N');
INSERT INTO `Lookup` VALUES(3510, 'min_max', '36', '36', 'Y', '2012-07-03 21:08:29', '2012-07-03 21:08:29', 'N');
INSERT INTO `Lookup` VALUES(3530, 'message_template', 'FP', '/order_templates/fax/execute_order_fax_PP3.htm', 'Y', '2012-10-19 09:12:59', '2012-10-19 09:26:20', 'N');
INSERT INTO `Lookup` VALUES(3531, 'message_template', 'EP', '/order_templates/fax/execute_order_fax_PP3.htm', 'Y', '2012-10-19 09:12:59', '2012-10-19 09:26:20', 'N');
INSERT INTO `Lookup` VALUES(3532, 'message_template', 'E', '/order_templates/email/execute_order_email.htm', 'Y', '2012-10-19 09:12:59', '2012-10-19 09:26:20', 'N');
INSERT INTO `Lookup` VALUES(3534, 'message_template', 'IA', '/utility_templates/twilio_outbound_response_ia.xml', 'Y', '2012-10-19 09:12:59', '2012-10-19 09:25:45', 'N');
INSERT INTO `Lookup` VALUES(3539, 'message_template', 'I', '/order_templates/ivr/execute_order_ivr.xml', 'Y', '2012-10-19 09:27:17', '2012-10-19 09:27:17', 'N');
INSERT INTO `Lookup` VALUES(3540, 'message_template', 'IB', '/utility_templates/twilio_outbound_response_ib.xml', 'Y', '2012-10-19 09:27:17', '2012-10-19 09:27:17', 'N');
INSERT INTO `Lookup` VALUES(null, 'message_template', 'IC', '/utility_templates/twilio_outbound_response_ic.xml', 'Y', '2012-10-19 09:27:17', '2012-10-19 09:27:17', 'N');
INSERT INTO `Lookup` VALUES(3541, 'message_template', 'WT', '/utility_templates/windows_service_configure.xml', 'Y', '2012-10-19 09:27:17', '2012-10-19 09:27:17', 'N');
INSERT INTO `Lookup` VALUES(3543, 'message_template', 'ED', '/utility_templates/accounting/email_daily_report.html', 'Y', '2012-10-19 09:27:17', '2012-10-19 09:27:17', 'N');
INSERT INTO `Lookup` VALUES(3544, 'message_template', 'EE', '/order_templates/email/execute_order_email_exceptions.htm', 'Y', '2012-10-19 20:20:57', '2012-10-19 20:20:57', 'N');
INSERT INTO `Lookup` VALUES(3545, 'status', 'Z', 'checkoutDataCall', 'Y', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup` VALUES(3546, 'message_template', 'WM', '/order_templates/windows_service/execute_order_windows_matred.xml', 'Y', '2012-10-19 09:12:59', '2013-07-17 13:16:20', 'N');
INSERT INTO `Lookup` VALUES(3547, 'message_template', 'WMR', '/order_templates/windows_service/execute_order_windows_matred_refund.xml', 'Y', '2012-10-19 09:12:59', '2013-07-17 13:16:20', 'N');
INSERT INTO `Lookup` VALUES(null, 'message_template', 'B', '/order_templates/brink/execute_order_brink.xml', 'Y', now(), now(), 'N');
INSERT INTO `Lookup` VALUES(null, 'message_template', 'BC', '/order_templates/brink/calculate_order_brink.xml', 'Y', now(), now(), 'N');
INSERT INTO `Lookup` VALUES(null, 'message_template', 'V', '/order_templates/vivonet/place_order.txt', 'Y', now(), now(), 'N');
INSERT INTO `Lookup` VALUES(null, 'message_template', 'U', '/order_templates/foundry/execute_order_foundry.xml', 'Y', now(), now(), 'N');
INSERT INTO `Lookup` VALUES(null, 'message_template', 'UE', '/order_templates/foundry/execute_order_foundry_epl.xml', 'Y', now(), now(), 'N');
INSERT INTO `Lookup` VALUES(null, 'nutritional_label','cholesterol','mg','Y','0000-00-00 00:00:00','0000-00-00 00:00:00','N');
INSERT INTO `Lookup` VALUES(null, 'nutritional_label','dietary_fiber','g','Y','0000-00-00 00:00:00','0000-00-00 00:00:00','N');
INSERT INTO `Lookup` VALUES(null, 'nutritional_label','protein','g','Y','0000-00-00 00:00:00','0000-00-00 00:00:00','N');
INSERT INTO `Lookup` VALUES(null, 'nutritional_label','saturated_fat','g','Y','0000-00-00 00:00:00','0000-00-00 00:00:00','N');
INSERT INTO `Lookup` VALUES(null, 'nutritional_label','sodium','mg','Y','0000-00-00 00:00:00','0000-00-00 00:00:00','N');
INSERT INTO `Lookup` VALUES(null, 'nutritional_label','sugars','g','Y','0000-00-00 00:00:00','0000-00-00 00:00:00','N');
INSERT INTO `Lookup` VALUES(null, 'nutritional_label','total_carborhydrate','g','Y','0000-00-00 00:00:00','0000-00-00 00:00:00','N');
INSERT INTO `Lookup` VALUES(null, 'nutritional_label','total_fat','g','Y','0000-00-00 00:00:00','0000-00-00 00:00:00','N');
INSERT INTO `Lookup` VALUES(null, 'nutritional_label','trans_fat','g','Y','0000-00-00 00:00:00','0000-00-00 00:00:00','N');
INSERT INTO `Lookup` VALUES(null, 'nutritional_label','calories','cal','Y','0000-00-00 00:00:00','0000-00-00 00:00:00','N');
INSERT INTO `Lookup` VALUES(null, 'nutritional_label','calories_from_fat','cal','Y','0000-00-00 00:00:00','0000-00-00 00:00:00','N');
INSERT INTO `Lookup` VALUES(null, 'delivery_price_type','driving','driving','Y',NOW(),NOW(),'N');
INSERT INTO `Lookup` VALUES(null, 'delivery_price_type','zip','zip','Y',NOW(),NOW(),'N');
INSERT INTO `Lookup` VALUES(null, 'delivery_price_type','polygon','polygon','Y',NOW(),NOW(),'N');
-- ----------------------------
--  Table structure for `Lookup_Master`
-- ----------------------------
DROP TABLE IF EXISTS `Lookup_Master`;
CREATE TABLE `Lookup_Master` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type_id_field` varchar(100) NOT NULL,
  `description` varchar(100) NOT NULL,
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`id`),
  KEY `OTHER` (`type_id_field`)
) ENGINE=InnoDB AUTO_INCREMENT=107 DEFAULT CHARSET=utf8 COMMENT='To maintain group list of reference informations';

INSERT INTO `Lookup_Master` VALUES(2, 'ach_type', 'Uses for ACH information', '2010-07-13 21:10:54', '2010-07-14 21:07:42', 'N');
INSERT INTO `Lookup_Master` VALUES(4, 'address_type', 'Type of Address associated to Merchant', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(6, 'answer_value', 'Reporting Parameter', '2010-07-13 21:38:50', '2010-07-14 21:07:43', 'N');
INSERT INTO `Lookup_Master` VALUES(8, 'billing_type', 'Merchant billing method', '2010-07-13 21:10:54', '2010-07-14 21:07:45', 'N');
INSERT INTO `Lookup_Master` VALUES(10, 'card_type', 'Type of Credit Card', '2010-07-13 21:10:54', '2010-07-14 21:07:45', 'N');
INSERT INTO `Lookup_Master` VALUES(12, 'cat_id', 'Menu Type Category', '2010-11-01 06:00:00', '2010-11-01 06:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(14, 'contract_term', 'Length of contact is months', '2010-08-13 17:16:27', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(16, 'country', 'Country field drop down list', '2010-07-13 21:10:54', '2010-07-14 21:07:46', 'N');
INSERT INTO `Lookup_Master` VALUES(18, 'date_range', 'Reporting Parameter', '2010-07-13 21:38:50', '2010-07-14 20:32:59', 'N');
INSERT INTO `Lookup_Master` VALUES(20, 'day_of_week', 'Day of Week (Sunday thru Saturday)', '2010-07-13 21:10:54', '2010-07-14 20:32:59', 'N');
INSERT INTO `Lookup_Master` VALUES(22, 'deal_type', 'Merchant Agreement Fee Description', '2010-08-13 15:12:58', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(24, 'hour_type', 'Business Hours', '2010-07-14 19:44:55', '2010-07-14 20:32:59', 'N');
INSERT INTO `Lookup_Master` VALUES(26, 'last_two_weeks_only', 'Reporting Parameter', '2010-07-13 21:38:13', '2010-07-14 20:32:59', 'N');
INSERT INTO `Lookup_Master` VALUES(28, 'locale', 'Taxing Authority', '2010-07-14 20:35:18', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(30, 'logical_delete', 'non physical deletion of record', '2010-07-14 20:35:18', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(32, 'menu_type', 'Categories to Organize a Menu', '2010-07-13 21:10:54', '2010-07-14 21:07:47', 'N');
INSERT INTO `Lookup_Master` VALUES(34, 'merchant_type', 'Reporting Parameter', '2010-07-13 21:38:13', '2010-07-14 20:33:39', 'N');
INSERT INTO `Lookup_Master` VALUES(36, 'message_type', 'Category of Customer or Merchant Communications', '2010-07-14 22:16:53', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(38, 'min_max', 'Select value range', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(40, 'mobile_app_type', 'Mobile Application Platform', '2010-07-14 22:40:40', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(42, 'modifier_type', 'Modifier Group Type', '0000-00-00 00:00:00', '2010-09-04 16:39:43', 'N');
INSERT INTO `Lookup_Master` VALUES(44, 'month', 'Calendar Month', '2011-03-21 06:00:00', '2011-03-21 06:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(46, 'order_del_type', 'Merchant Order Delivery method', '2010-07-13 21:10:54', '2010-07-14 21:07:47', 'N');
INSERT INTO `Lookup_Master` VALUES(48, 'order_type', 'Transaction Type', '2010-07-14 22:20:14', '2010-07-14 22:20:45', 'N');
INSERT INTO `Lookup_Master` VALUES(50, 'pymt_sch', 'Merchant Payment Schedule', '2010-08-13 17:17:09', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(52, 'report_group', 'Merchant Group Category for Reports', '2010-07-13 21:10:54', '2010-07-14 21:07:48', 'N');
INSERT INTO `Lookup_Master` VALUES(54, 'splickit_team_orders', 'Reporting Parameter', '2010-07-13 21:47:40', '2010-07-14 20:33:39', 'N');
INSERT INTO `Lookup_Master` VALUES(56, 'state', 'State and Province drop down list', '2010-07-13 21:10:54', '2010-07-14 21:07:49', 'N');
INSERT INTO `Lookup_Master` VALUES(58, 'status', 'Order Status', '2010-07-14 22:31:09', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(60, 'str_type', 'Store Category', '2010-07-13 21:10:54', '2010-07-14 21:07:49', 'N');
INSERT INTO `Lookup_Master` VALUES(62, 'time_zone', 'Time Zone', '2010-07-13 21:35:34', '2010-07-14 21:07:50', 'N');
INSERT INTO `Lookup_Master` VALUES(64, 'topic', 'Feedback topic ', '2010-07-13 21:10:54', '2010-07-14 21:07:51', 'N');
INSERT INTO `Lookup_Master` VALUES(66, 'trans_fee_payee', 'Payer for Splickit Transaction Fee', '2010-08-13 17:16:27', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(68, 'trans_fee_type', 'Transaction Fee type drop down list', '2010-07-13 21:10:54', '2010-07-14 21:07:51', 'N');
INSERT INTO `Lookup_Master` VALUES(70, 'yes_no', 'Yes No Dropdown', '2010-08-13 17:17:49', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(72, 'zero_dallor_orders', 'Zero Dollar Indicator for Reports', '2010-07-13 21:10:54', '2010-07-14 20:33:39', 'N');
INSERT INTO `Lookup_Master` VALUES(74, 'current_status', 'Current Status', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(76, 'object_type', 'Object Type (Menu_Change_Schedule)', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(79, 'time_increments', 'Time Increments (Delivery)', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(81, 'holiday', 'Holiday Store Status', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(85, 'splickit_agreements', 'Splickit Merchant Agreement Language', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(88, 'credit_type', 'Credit Type for Order Reversals', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(90, 'merchant_menu_type', 'Merchant Menu Type', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(92, 'cc_processor', 'CC Processor', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(94, 'inc_trans_pay_cycle', 'Transaction Payment Period', '0000-00-00 00:00:00', '2012-02-03 17:40:07', 'N');
INSERT INTO `Lookup_Master` VALUES(100, 'yes_no_LTO', 'Special Active dropdown for LTO objects', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(102, 'payment_cycle', 'Merchant Payments', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(103, 'order_delivery_template', 'Custom format for order delivery', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(104, 'custom_order_delivery_template', '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(105, 'utility_message_template', '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(106, 'message_template', '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');
INSERT INTO `Lookup_Master` VALUES(107, 'delivery_price_type', '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', 'N');


-- ----------------------------
--  Table structure for `Menu`
-- ----------------------------
DROP TABLE IF EXISTS `Menu`;
CREATE TABLE `Menu` (
  `menu_id` int(11) NOT NULL AUTO_INCREMENT,
  `external_menu_id` varchar(100) DEFAULT NULL,
  `name` varchar(150) NOT NULL,
  `description` varchar(255) NOT NULL,
  `last_menu_change` int(11) NOT NULL,
  `version` decimal(10,2) NOT NULL DEFAULT '3.00',
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`menu_id`)
) ENGINE=InnoDB AUTO_INCREMENT=102552 DEFAULT CHARSET=utf8;

INSERT INTO `Menu` VALUES(0, NULL, 'System Default', 'System Default', 2013, 2.00, '2011-06-12 14:54:01', '2012-07-22 09:01:01', 'N');
Update Menu set menu_id = 0 WHERE menu_id = LAST_INSERT_ID();

-- ----------------------------
--  Table structure for `Menu_Change_Schedule`
-- ----------------------------
DROP TABLE IF EXISTS `Menu_Change_Schedule`;
CREATE TABLE `Menu_Change_Schedule` (
  `menu_change_id` int(11) NOT NULL AUTO_INCREMENT,
  `menu_id` int(11) NOT NULL,
  `merchant_id` int(11) NOT NULL DEFAULT '0',
  `object_type` varchar(50) NOT NULL,
  `object_id` int(11) NOT NULL,
  `replace_id` int(11) DEFAULT NULL,
  `day_of_week` int(11) NOT NULL,
  `current_status` varchar(3) NOT NULL DEFAULT 'Off',
  `start` time NOT NULL DEFAULT '00:01:00',
  `stop` time NOT NULL DEFAULT '23:59:00',
  `active` char(1) NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`menu_change_id`),
  UNIQUE KEY `merchant_menu` (`merchant_id`,`menu_id`),
  KEY `menu_id` (`menu_id`),
  KEY `merchant_id` (`merchant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=753 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `Menu_Combo`
-- ----------------------------
DROP TABLE IF EXISTS `Menu_Combo`;
CREATE TABLE `Menu_Combo` (
  `combo_id` int(11) NOT NULL AUTO_INCREMENT,
  `menu_id` int(11) NOT NULL,
  `combo_name` varchar(50) NOT NULL,
  `combo_description` varchar(255) DEFAULT NULL,
  `combo_external_id` varchar(100) DEFAULT NULL,
  `active` char(1) NOT NULL DEFAULT 'Y',
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`combo_id`),
  KEY `combo_external_id` (`combo_external_id`),
  KEY `menu_id` (`menu_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1002 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `Menu_Combo_Association`
-- ----------------------------
DROP TABLE IF EXISTS `Menu_Combo_Association`;
CREATE TABLE `Menu_Combo_Association` (
  `combo_association_id` int(11) NOT NULL AUTO_INCREMENT,
  `combo_id` int(11) NOT NULL,
  `kind_of_object` varchar(20) NOT NULL,
  `object_id` int(11) NOT NULL,
  `external_object_id` varchar(100) DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`combo_association_id`),
  KEY `combo_id` (`combo_id`,`object_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1004 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `Menu_Combo_Price`
-- ----------------------------
DROP TABLE IF EXISTS `Menu_Combo_Price`;
CREATE TABLE `Menu_Combo_Price` (
  `combo_price_id` int(11) NOT NULL AUTO_INCREMENT,
  `combo_id` int(11) NOT NULL,
  `external_id` varchar(50) DEFAULT NULL,
  `merchant_id` int(11) NOT NULL,
  `price` decimal(10,2) NOT NULL,
  `active` char(1) NOT NULL DEFAULT 'Y',
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`combo_price_id`),
  KEY `combo_id` (`combo_id`,`merchant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1164 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `Menu_Message`
-- ----------------------------
DROP TABLE IF EXISTS `Menu_Message`;
CREATE TABLE `Menu_Message` (
  `menu_message_id` int(11) NOT NULL AUTO_INCREMENT,
  `message_text` varchar(255) NOT NULL,
  `fail_type` varchar(10) NOT NULL COMMENT 'fatal or informative',
  `match_type` varchar(10) NOT NULL COMMENT 'any or all',
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`menu_message_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `Menu_PosId_Price_Map`
-- ----------------------------
DROP TABLE IF EXISTS `Menu_PosId_Price_Map`;
CREATE TABLE `Menu_PosId_Price_Map` (
  `map_id` int(11) NOT NULL AUTO_INCREMENT,
  `merchant_id` int(11) NOT NULL,
  `external_id` varchar(255) NOT NULL COMMENT 'this is the id in the POS system',
  `item_name` varchar(100) DEFAULT NULL,
  `price` decimal(10,2) NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`map_id`),
  KEY `merchant_id` (`merchant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `Menu_Type`
-- ----------------------------
DROP TABLE IF EXISTS `Menu_Type`;
CREATE TABLE `Menu_Type` (
  `menu_type_id` int(11) NOT NULL AUTO_INCREMENT,
  `external_menu_type_id` varchar(255) DEFAULT NULL,
  `menu_id` int(11) NOT NULL,
  `menu_type_name` varchar(40) NOT NULL,
  `menu_type_description` varchar(255) NOT NULL,
  `cat_id` char(1) NOT NULL DEFAULT 'E',
  `priority` int(11) NOT NULL,
  `active` char(1) NOT NULL DEFAULT 'Y',
  `start_time` time NOT NULL DEFAULT '00:00:00',
  `end_time` time NOT NULL DEFAULT '23:59:59',
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`menu_type_id`),
  KEY `fk_MT_merchant_id` (`menu_id`),
  KEY `cat_id` (`cat_id`),
  KEY `external_menu_type_id` (`external_menu_type_id`),
  CONSTRAINT `Menu_Type_ibfk_1` FOREIGN KEY (`cat_id`) REFERENCES `Lookup` (`type_id_value`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `Menu_Type_ibfk_2` FOREIGN KEY (`menu_id`) REFERENCES `Menu` (`menu_id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=45453 DEFAULT CHARSET=utf8 COMMENT='To maintain list of sections in a merchant menu';

INSERT INTO `Menu_Type` VALUES(0, NULL, 0, 'System Default', 'System Default', 'E', 0, 'Y', '00:00:00', '23:59:59', '0000-00-00 00:00:00', '2010-11-02 03:45:35', 'N');
UPDATE Menu_Type SET menu_type_id = 0 WHERE menu_type_id = LAST_INSERT_ID();

-- ----------------------------
--  Table structure for `Merchant_User`
-- ----------------------------
DROP TABLE IF EXISTS `Merchant_User`;
CREATE TABLE `Merchant_User` (
  `merchant_user_id` int(11) NOT NULL AUTO_INCREMENT,
  `first_name` varchar(100) NOT NULL DEFAULT '',
  `last_name` varchar(100) NOT NULL DEFAULT '',
  `company_name` varchar(150) NOT NULL,
  `address1` varchar(100) NOT NULL DEFAULT '',
  `address2` varchar(100) DEFAULT '',
  `city` varchar(100) NOT NULL,
  `state` char(2) NOT NULL DEFAULT '0',
  `zip` varchar(100) NOT NULL DEFAULT '',
  `country` varchar(100) NOT NULL DEFAULT 'US',
  `email` varchar(100) NOT NULL DEFAULT '',
  `password` varchar(100) NOT NULL DEFAULT 'welcome',
  `phone_no` varchar(100) NOT NULL DEFAULT '',
  `fax_no` varchar(100) DEFAULT '',
  `tc_acceptance` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`merchant_user_id`),
  KEY `state` (`state`),
  KEY `country` (`country`),
  CONSTRAINT `Merchant_User_ibfk_1` FOREIGN KEY (`state`) REFERENCES `Lookup` (`type_id_value`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `Merchant_User_ibfk_2` FOREIGN KEY (`country`) REFERENCES `Lookup` (`type_id_value`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=11234 DEFAULT CHARSET=utf8 COMMENT='To maintain master merchant user accounts';

INSERT INTO `Merchant_User` VALUES(0, 'System Default', 'System Default', 'System Default', 'System Default', 'System Default', 'System Default', '0', '', '0', '', '', '', '', '0000-00-00 00:00:00', '0000-00-00 00:00:00', '2010-09-04 19:16:00', 'N');
UPDATE Merchant_User SET merchant_user_id = 0 WHERE merchant_user_id = LAST_INSERT_ID();

-- ----------------------------
--  Table structure for `Merchant`
-- ----------------------------
DROP TABLE IF EXISTS `Merchant`;
CREATE TABLE `Merchant` (
  `merchant_id` int(11) NOT NULL AUTO_INCREMENT,
  `merchant_user_id` int(11) NOT NULL DEFAULT '0',
  `merchant_external_id` varchar(50) DEFAULT NULL,
  `numeric_id` int(11) DEFAULT NULL,
  `alphanumeric_id` varchar(20) DEFAULT NULL,
  `rewardr_programs` varchar(255) DEFAULT NULL,
  `shop_email` varchar(100) NOT NULL,
  `brand_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `display_name` varchar(100) DEFAULT NULL,
  `address1` varchar(100) NOT NULL DEFAULT '',
  `address2` varchar(100) DEFAULT '',
  `city` varchar(100) NOT NULL DEFAULT '',
  `state` char(2) NOT NULL,
  `zip` varchar(100) NOT NULL DEFAULT '',
  `country` varchar(100) NOT NULL DEFAULT 'US',
  `lat` decimal(10,6) NOT NULL DEFAULT '0.000000',
  `lng` decimal(10,6) NOT NULL DEFAULT '0.000000',
  `EIN_SS` varchar(20) CHARACTER SET ucs2 DEFAULT NULL,
  `description` text,
  `phone_no` varchar(100) NOT NULL DEFAULT '',
  `fax_no` varchar(100) DEFAULT '',
  `twitter_handle` varchar(100) DEFAULT NULL,
  `time_zone` varchar(10) NOT NULL,
  `cross_street` varchar(1000) DEFAULT NULL,
  `trans_fee_type` char(1) NOT NULL DEFAULT 'F',
  `trans_fee_rate` decimal(10,2) NOT NULL DEFAULT '0.00',
  `show_tip` char(1) NOT NULL DEFAULT 'Y',
  `donate` decimal(10,0) NOT NULL DEFAULT '0',
  `cc_processor` char(1) DEFAULT NULL,
  `merchant_type` char(1) DEFAULT 'N',
  `active` char(1) NOT NULL DEFAULT 'N',
  `ordering_on` char(1) NOT NULL DEFAULT 'Y',
  `show_search` char(1) NOT NULL DEFAULT 'Y',
  `lead_time` int(11) NOT NULL DEFAULT '15',
  `immediate_message_delivery` char(1) NOT NULL DEFAULT 'N',
  `delivery` char(1) NOT NULL DEFAULT 'N',
  `advanced_ordering` char(1) NOT NULL DEFAULT 'N',
  `order_del_type` varchar(10) NOT NULL DEFAULT 'T',
  `order_del_addr` varchar(100) DEFAULT NULL,
  `order_del_addr2` varchar(100) DEFAULT NULL,
  `payment_cycle` char(1) NOT NULL DEFAULT 'W',
  `minimum_iphone_version` decimal(10,3) NOT NULL DEFAULT '2.040',
  `minimum_android_version` decimal(10,3) NOT NULL DEFAULT '2.040',
  `live_dt` date NOT NULL,
  `facebook_caption_link` varchar(255) DEFAULT NULL COMMENT 'link to stores web site',
  `custom_order_message` varchar(100) DEFAULT NULL,
  `custom_menu_message` varchar(255) DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`merchant_id`),
  UNIQUE KEY `numeric_id` (`numeric_id`),
  UNIQUE KEY `numeric_alpha_id` (`numeric_id`,`alphanumeric_id`) USING BTREE,
  KEY `fk_M_merchant_user_id` (`merchant_user_id`),
  KEY `country` (`country`),
  KEY `state` (`state`),
  KEY `trans_fee_type` (`trans_fee_type`),
  KEY `order_del_type` (`order_del_type`),
  CONSTRAINT `fk_M_merchant_user_id` FOREIGN KEY (`merchant_user_id`) REFERENCES `Merchant_User` (`merchant_user_id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `Merchant_ibfk_5` FOREIGN KEY (`state`) REFERENCES `Lookup` (`type_id_value`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `Merchant_ibfk_6` FOREIGN KEY (`country`) REFERENCES `Lookup` (`type_id_value`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `Merchant_ibfk_7` FOREIGN KEY (`trans_fee_type`) REFERENCES `Lookup` (`type_id_value`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=104291 DEFAULT CHARSET=utf8 DELAY_KEY_WRITE=1 COMMENT='To maintain merchant accounts';

INSERT INTO `Merchant` VALUES(0, 0, '1', 46665620, '829uz81o950j', NULL, 'System Default', 100, 'System Default', 'System Default', 'System Default', 'System Default', 'System Default', 'CO', '', 'US', 0.000000, 0.000000, NULL, '', '', '', NULL, '', '', 'F', 0.25, 'Y', 0, 'I', 'I', 'N', 'Y', 'Y', 0, 'N', 'N', 'N', '0', '9999999999', NULL, 'W', 2.041, 2.040, '0000-00-00', NULL, NULL, NULL, '0000-00-00 00:00:00', '2013-01-12 18:11:08', 'N');
UPDATE Merchant SET merchant_id = 0 WHERE merchant_id = LAST_INSERT_ID();

-- ----------------------------
--  Table structure for `Merchant_Advanced_Ordering_Info`
-- ----------------------------
DROP TABLE IF EXISTS `Merchant_Advanced_Ordering_Info`;
CREATE TABLE `Merchant_Advanced_Ordering_Info` (
  `merchant_advanced_ordering_id` int(11) NOT NULL AUTO_INCREMENT,
  `merchant_id` int(11) NOT NULL,
  `active` char(1) NOT NULL DEFAULT 'Y',
  `minimum_order` decimal(10,2) NOT NULL DEFAULT '0.00',
  `max_days_out` int(11) NOT NULL DEFAULT '1' COMMENT 'maximum number of days in advance',
  `increment` int(11) NOT NULL DEFAULT '30' COMMENT 'in minutes',
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  `logical_delete` varchar(10) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`merchant_advanced_ordering_id`),
  UNIQUE KEY `merchant_id` (`merchant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1000 DEFAULT CHARSET=utf8 COMMENT='To maintain merchant advanced ordering info';

-- ----------------------------
--  Table structure for `Merchant_Delivery_Info`
-- ----------------------------
DROP TABLE IF EXISTS `Merchant_Delivery_Info`;
CREATE TABLE `Merchant_Delivery_Info` (
  `merchant_delivery_id` int(11) NOT NULL AUTO_INCREMENT,
  `merchant_id` int(11) NOT NULL,
  `active` char(1) NOT NULL DEFAULT 'Y',
  `minimum_order` decimal(10,2) NOT NULL DEFAULT '0.00',
  `max_distance` int(11) DEFAULT '0' COMMENT 'deprecated',
  `zip_codes` varchar(255) DEFAULT NULL COMMENT 'set to true if merchant uses zip codes',
  `delivery_cost` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'deprecated',
  `max_days_out` int(11) NOT NULL DEFAULT '1' COMMENT 'maximum number of days in advance',
  `minimum_delivery_time` int(11) NOT NULL DEFAULT '45' COMMENT 'in minutes',
  `delivery_increment` int(11) NOT NULL DEFAULT '30' COMMENT 'in minutes',
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `logical_delete` varchar(10) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`merchant_delivery_id`),
  UNIQUE KEY `merchant_id` (`merchant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5261 DEFAULT CHARSET=utf8 COMMENT='To maintain merchant delivery info';

-- ----------------------------
--  Table structure for `Merchant_Delivery_Price_Distance`
-- ----------------------------
DROP TABLE IF EXISTS `Merchant_Delivery_Price_Distance`;
CREATE TABLE `Merchant_Delivery_Price_Distance` (
  `map_id` int(11) NOT NULL AUTO_INCREMENT,
  `merchant_id` int(11) NOT NULL,
  `distance_up_to` decimal(10,2) NOT NULL DEFAULT '1.00',
  `zip_codes` varchar(255) NOT NULL COMMENT 'comma separated list',
  `price` decimal(10,2) NOT NULL DEFAULT '1.00',
  `notes` varchar(255) NOT NULL DEFAULT '',
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`map_id`),
  UNIQUE KEY `merch_dist` (`merchant_id`,`distance_up_to`),
  UNIQUE KEY `merch_dist_up_to` (`merchant_id`,`distance_up_to`),
  KEY `merchant_id` (`merchant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=5603 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `Merchant_FPN_Map`
-- ----------------------------
DROP TABLE IF EXISTS `Merchant_FPN_Map`;
CREATE TABLE `Merchant_FPN_Map` (
  `map_id` int(11) NOT NULL AUTO_INCREMENT,
  `merchant_id` int(11) NOT NULL,
  `fpn_merchant_key` varchar(255) NOT NULL,
  `fpn_merchant_pin` varchar(255) NOT NULL,
  `merchant_rock_comm_id` varchar(255) NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`map_id`),
  UNIQUE KEY `merchant_id` (`merchant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1002 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `Merchant_Menu_Map`
-- ----------------------------
DROP TABLE IF EXISTS `Merchant_Menu_Map`;
CREATE TABLE `Merchant_Menu_Map` (
  `map_id` int(11) NOT NULL AUTO_INCREMENT,
  `merchant_id` int(11) NOT NULL,
  `menu_id` int(11) NOT NULL,
  `merchant_menu_type` varchar(10) NOT NULL DEFAULT 'pickup',
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`map_id`),
  UNIQUE KEY `merchant_id` (`merchant_id`,`menu_id`,`merchant_menu_type`),
  KEY `menu_id` (`menu_id`),
  CONSTRAINT `Merchant_Menu_Map_ibfk_1` FOREIGN KEY (`menu_id`) REFERENCES `Menu` (`menu_id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `Merchant_Menu_Map_ibfk_2` FOREIGN KEY (`merchant_id`) REFERENCES `Merchant` (`merchant_id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=4133 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `Merchant_Mercury_Map`
-- ----------------------------
DROP TABLE IF EXISTS `Merchant_Mercury_Map`;
CREATE TABLE `Merchant_Mercury_Map` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `merchant_id` int(11) NOT NULL,
  `dbaName` varchar(45) DEFAULT NULL,
  `GlobalMerchantID` varchar(20) DEFAULT NULL,
  `dbaAddress` varchar(31) DEFAULT NULL,
  `dbaCity` varchar(16) DEFAULT NULL,
  `dbaState` varchar(2) DEFAULT NULL,
  `dbaZip` int(5) DEFAULT NULL,
  `dbaPhone` varchar(13) DEFAULT NULL,
  `legalName` varchar(26) DEFAULT NULL,
  `legalAddress` varchar(26) DEFAULT NULL,
  `legalCity` varchar(13) DEFAULT NULL,
  `TerminalName` varchar(44) DEFAULT NULL,
  `NickName` varchar(6) DEFAULT NULL,
  `TerminalID` varchar(20) DEFAULT NULL,
  `Equipment` varchar(10) DEFAULT NULL,
  `TermNote` varchar(10) DEFAULT NULL,
  `TerminalClass` varchar(10) DEFAULT NULL,
  `WebServicePassword` varchar(10) DEFAULT NULL,
  `AcceptGift` int(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `GlobalMerchantID` (`GlobalMerchantID`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `Merchant_Message_History`
-- ----------------------------
DROP TABLE IF EXISTS `Merchant_Message_History`;
CREATE TABLE `Merchant_Message_History` (
  `map_id` int(11) NOT NULL AUTO_INCREMENT,
  `merchant_id` int(11) NOT NULL DEFAULT '0',
  `order_id` int(11) DEFAULT NULL,
  `message_format` char(5) NOT NULL DEFAULT '',
  `message_delivery_addr` varchar(255) NOT NULL DEFAULT '',
  `next_message_dt_tm` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `from_email` varchar(100) NOT NULL DEFAULT '',
  `sent_dt_tm` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `stamp` varchar(50) DEFAULT NULL,
  `locked` char(1) NOT NULL DEFAULT 'N',
  `viewed` char(1) DEFAULT NULL COMMENT 'To indicate when the opie message has been read',
  `message_type` char(2) DEFAULT NULL COMMENT 'an X means its the billing message',
  `tries` int(2) NOT NULL DEFAULT '0',
  `info` varchar(510) DEFAULT NULL,
  `message_text` text,
  `response` text,
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`map_id`),
  KEY `fk_CS_merchant_id` (`merchant_id`),
  KEY `viewed` (`viewed`),
  KEY `order_id` (`order_id`),
  KEY `merchant_id` (`merchant_id`),
  KEY `next_message_dt_tm` (`next_message_dt_tm`),
  KEY `locked` (`locked`)
) ENGINE=InnoDB AUTO_INCREMENT=295544 DEFAULT CHARSET=utf8 COMMENT='History of all communications to all devices';

-- ----------------------------
--  Table structure for `Merchant_Message_Map`
-- ----------------------------
DROP TABLE IF EXISTS `Merchant_Message_Map`;
CREATE TABLE `Merchant_Message_Map` (
  `map_id` int(11) NOT NULL AUTO_INCREMENT,
  `merchant_id` int(11) NOT NULL,
  `message_format` char(5) NOT NULL DEFAULT '',
  `delivery_addr` varchar(50) NOT NULL,
  `delay` int(11) NOT NULL DEFAULT '0',
  `message_type` char(2) NOT NULL DEFAULT 'O' COMMENT 'set this to x if this is the billing message',
  `info` varchar(255) DEFAULT NULL,
  `message_text` text COMMENT 'static message to be sent',
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`map_id`),
  KEY `fk_MMM_merchant_id` (`merchant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=13911 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `Merchant_Payment_Type`
-- ----------------------------
DROP TABLE IF EXISTS `Merchant_Payment_Type`;
CREATE TABLE `Merchant_Payment_Type` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `merchant_id` int(11) NOT NULL,
  `payment_type` varchar(50) NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `merchant_payment` (`merchant_id`,`payment_type`),
  KEY `merchant_id` (`merchant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4546 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `Merchant_Preptime_Info`
-- ----------------------------
DROP TABLE IF EXISTS `Merchant_Preptime_Info`;
CREATE TABLE `Merchant_Preptime_Info` (
  `merchant_preptime_info_id` int(11) NOT NULL AUTO_INCREMENT,
  `merchant_id` int(11) NOT NULL,
  `entree_preptime_seconds` int(11) NOT NULL COMMENT 'in seconds',
  `concurrently_able_to_make` int(11) NOT NULL DEFAULT '1',
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`merchant_preptime_info_id`),
  KEY `merchant_id` (`merchant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1159 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `Merchant_Printer_Swap_Map`
-- ----------------------------
DROP TABLE IF EXISTS `Merchant_Printer_Swap_Map`;
CREATE TABLE `Merchant_Printer_Swap_Map` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `merchant_id` int(11) NOT NULL,
  `new_sms_no` varchar(20) NOT NULL,
  `live` char(1) NOT NULL DEFAULT 'N',
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `merchant_id` (`merchant_id`),
  UNIQUE KEY `new_sms_no` (`new_sms_no`),
  KEY `live` (`live`)
) ENGINE=InnoDB AUTO_INCREMENT=1000 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `Merchant_Temp`
-- ----------------------------
DROP TABLE IF EXISTS `Merchant_Temp`;
CREATE TABLE `Merchant_Temp` (
  `merchant_id` int(11) NOT NULL AUTO_INCREMENT,
  `merchant_user_id` int(11) NOT NULL DEFAULT '0',
  `merchant_external_id` varchar(50) DEFAULT NULL,
  `shop_email` varchar(100) NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `display_name` varchar(100) DEFAULT NULL,
  `address1` varchar(100) NOT NULL DEFAULT '',
  `address2` varchar(100) NOT NULL DEFAULT '',
  `city` varchar(100) NOT NULL DEFAULT '',
  `state` char(2) NOT NULL,
  `zip` varchar(100) NOT NULL DEFAULT '',
  `country` varchar(100) NOT NULL,
  `lat` decimal(10,6) NOT NULL DEFAULT '0.000000',
  `lng` decimal(10,6) NOT NULL DEFAULT '0.000000',
  `description` text NOT NULL,
  `phone_no` varchar(100) NOT NULL DEFAULT '',
  `fax_no` varchar(100) NOT NULL DEFAULT '',
  `twitter_handle` varchar(100) DEFAULT NULL,
  `time_zone` varchar(10) NOT NULL,
  `cross_street` varchar(1000) NOT NULL,
  `trans_fee_type` char(1) NOT NULL DEFAULT 'F',
  `trans_fee_rate` decimal(10,2) NOT NULL DEFAULT '0.25',
  `show_tip` char(1) NOT NULL DEFAULT 'Y',
  `donate` decimal(10,0) NOT NULL DEFAULT '0',
  `merchant_type` char(1) DEFAULT 'F',
  `active` char(1) NOT NULL DEFAULT 'N',
  `show_search` char(1) NOT NULL DEFAULT 'Y',
  `lead_time` int(11) NOT NULL DEFAULT '5',
  `order_del_type` varchar(10) NOT NULL DEFAULT 'T',
  `order_del_addr` varchar(100) NOT NULL DEFAULT '',
  `order_del_addr2` varchar(100) DEFAULT NULL,
  `minimum_iphone_version` decimal(10,3) NOT NULL DEFAULT '2.040',
  `minimum_android_version` decimal(10,3) NOT NULL DEFAULT '2.040',
  `facebook_caption_link` varchar(255) DEFAULT NULL COMMENT 'link to stores web site',
  `custom_order_message` varchar(100) DEFAULT NULL COMMENT 'this will be appended to the order confirm.',
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`merchant_id`),
  KEY `fk_M_merchant_user_id` (`merchant_user_id`),
  KEY `country` (`country`),
  KEY `state` (`state`),
  KEY `trans_fee_type` (`trans_fee_type`),
  KEY `order_del_type` (`order_del_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='To maintain merchant accounts';

-- ----------------------------
--  Table structure for `Merchant_temp2`
-- ----------------------------
DROP TABLE IF EXISTS `Merchant_temp2`;
CREATE TABLE `Merchant_temp2` (
  `merchant_id` int(11) NOT NULL DEFAULT '0',
  `merchant_user_id` int(11) NOT NULL DEFAULT '0',
  `merchant_external_id` varchar(50) DEFAULT NULL,
  `numeric_id` int(11) DEFAULT NULL,
  `alphanumeric_id` varchar(20) DEFAULT NULL,
  `rewardr_programs` varchar(255) DEFAULT NULL,
  `shop_email` varchar(100) NOT NULL,
  `brand_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `display_name` varchar(100) DEFAULT NULL,
  `address1` varchar(100) NOT NULL DEFAULT '',
  `address2` varchar(100) DEFAULT '',
  `city` varchar(100) NOT NULL DEFAULT '',
  `state` char(2) NOT NULL,
  `zip` varchar(100) NOT NULL DEFAULT '',
  `country` varchar(100) NOT NULL DEFAULT 'US',
  `lat` decimal(10,6) NOT NULL DEFAULT '0.000000',
  `lng` decimal(10,6) NOT NULL DEFAULT '0.000000',
  `EIN_SS` varchar(20) CHARACTER SET ucs2 DEFAULT NULL,
  `description` text,
  `phone_no` varchar(100) NOT NULL DEFAULT '',
  `fax_no` varchar(100) DEFAULT '',
  `twitter_handle` varchar(100) DEFAULT NULL,
  `time_zone` varchar(10) NOT NULL,
  `cross_street` varchar(1000) DEFAULT NULL,
  `trans_fee_type` char(1) NOT NULL DEFAULT 'F',
  `trans_fee_rate` decimal(10,2) NOT NULL DEFAULT '0.00',
  `show_tip` char(1) NOT NULL DEFAULT 'Y',
  `donate` decimal(10,0) NOT NULL DEFAULT '0',
  `cc_processor` char(1) DEFAULT NULL,
  `merchant_type` char(1) DEFAULT 'N',
  `active` char(1) NOT NULL DEFAULT 'N',
  `ordering_on` char(1) NOT NULL DEFAULT 'Y',
  `show_search` char(1) NOT NULL DEFAULT 'Y',
  `lead_time` int(11) NOT NULL DEFAULT '15',
  `immediate_message_delivery` char(1) NOT NULL DEFAULT 'N',
  `delivery` char(1) NOT NULL DEFAULT 'N',
  `advanced_ordering` char(1) NOT NULL DEFAULT 'N',
  `order_del_type` varchar(10) NOT NULL DEFAULT 'T',
  `order_del_addr` varchar(100) DEFAULT NULL,
  `order_del_addr2` varchar(100) DEFAULT NULL,
  `payment_cycle` char(1) NOT NULL DEFAULT 'W',
  `minimum_iphone_version` decimal(10,3) NOT NULL DEFAULT '2.040',
  `minimum_android_version` decimal(10,3) NOT NULL DEFAULT '2.040',
  `live_dt` date NOT NULL,
  `facebook_caption_link` varchar(255) DEFAULT NULL COMMENT 'link to stores web site',
  `custom_order_message` varchar(100) DEFAULT NULL,
  `custom_menu_message` varchar(255) DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `logical_delete` char(1) NOT NULL DEFAULT 'N'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `Message`
-- ----------------------------
DROP TABLE IF EXISTS `Message`;
CREATE TABLE `Message` (
  `message_id` int(11) NOT NULL AUTO_INCREMENT,
  `message_name` varchar(25) NOT NULL DEFAULT '',
  `description` varchar(250) NOT NULL DEFAULT '',
  `subject` varchar(250) NOT NULL DEFAULT '',
  `body` longtext NOT NULL,
  `sql2` text NOT NULL,
  `message_seq` int(11) NOT NULL DEFAULT '0',
  `message_type` char(1) NOT NULL DEFAULT '',
  `from_email` varchar(100) NOT NULL DEFAULT '',
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`message_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1025 DEFAULT CHARSET=utf8 COMMENT='To maintain  list of all communications to all devices';

-- ----------------------------
--  Table structure for `Modifier_Group`
-- ----------------------------
DROP TABLE IF EXISTS `Modifier_Group`;
CREATE TABLE `Modifier_Group` (
  `modifier_group_id` int(11) NOT NULL AUTO_INCREMENT,
  `external_modifier_group_id` varchar(255) DEFAULT NULL,
  `menu_id` int(11) NOT NULL,
  `modifier_group_name` varchar(50) NOT NULL,
  `modifier_description` varchar(255) NOT NULL,
  `modifier_type` char(5) NOT NULL DEFAULT 'T',
  `active` char(1) NOT NULL DEFAULT 'Y',
  `priority` int(2) NOT NULL DEFAULT '100',
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`modifier_group_id`),
  KEY `merchant_id` (`menu_id`),
  KEY `modifer_type` (`modifier_type`),
  KEY `external_modifier_group_id` (`external_modifier_group_id`),
  CONSTRAINT `Modifier_Group_ibfk_1` FOREIGN KEY (`menu_id`) REFERENCES `Menu` (`menu_id`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=330994 DEFAULT CHARSET=utf8 COMMENT='To maintain list of modifier groups';

-- ----------------------------
--  Table structure for `Modifier_Item`
-- ----------------------------
DROP TABLE IF EXISTS `Modifier_Item`;
CREATE TABLE `Modifier_Item` (
  `modifier_item_id` int(11) NOT NULL AUTO_INCREMENT,
  `external_modifier_item_id` varchar(255) DEFAULT NULL,
  `modifier_group_id` int(11) NOT NULL,
  `modifier_item_name` varchar(55) NOT NULL,
  `modifier_item_print_name` varchar(50) NOT NULL COMMENT 'for ticket printer delivery',
  `modifier_item_max` int(11) NOT NULL DEFAULT '2',
  `priority` int(2) NOT NULL DEFAULT '1',
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`modifier_item_id`),
  KEY `modifier_id` (`modifier_group_id`),
  KEY `external_modifier_item_id` (`external_modifier_item_id`),
  CONSTRAINT `fk_MdI_modifier_group_id` FOREIGN KEY (`modifier_group_id`) REFERENCES `Modifier_Group` (`modifier_group_id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=2281407 DEFAULT CHARSET=utf8 COMMENT='To maintain list of modifier items';

-- ----------------------------
--  Table structure for `Modifier_Size_Map`
-- ----------------------------
DROP TABLE IF EXISTS `Modifier_Size_Map`;
CREATE TABLE `Modifier_Size_Map` (
  `modifier_size_id` int(11) NOT NULL AUTO_INCREMENT,
  `merchant_id` int(11) NOT NULL DEFAULT '0',
  `external_id` varchar(255) DEFAULT NULL,
  `modifier_item_id` int(11) NOT NULL,
  `size_id` int(11) NOT NULL DEFAULT '0',
  `modifier_price` decimal(10,3) NOT NULL,
  `active` char(1) NOT NULL DEFAULT 'Y',
  `priority` int(11) NOT NULL DEFAULT '0',
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`modifier_size_id`),
  UNIQUE KEY `merchant_id` (`modifier_item_id`,`size_id`,`merchant_id`),
  KEY `size_id` (`size_id`),
  KEY `modifier_item_id` (`modifier_item_id`),
  KEY `external_id` (`external_id`),
  CONSTRAINT `fk_MSM_modifier_item_id` FOREIGN KEY (`modifier_item_id`) REFERENCES `Modifier_Item` (`modifier_item_id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `fk_MSM_size_id` FOREIGN KEY (`size_id`) REFERENCES `Sizes` (`size_id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=4290143 DEFAULT CHARSET=utf8 COMMENT='To associate Size and Modifer_Item tables';

-- ----------------------------
--  Table structure for `Open_Menu_Status`
-- ----------------------------
DROP TABLE IF EXISTS `Open_Menu_Status`;
CREATE TABLE `Open_Menu_Status` (
  `open_menu_id` varchar(255) NOT NULL,
  `last_updated` int(11) NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`open_menu_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `Order_Detail`
-- ----------------------------
DROP TABLE IF EXISTS `Order_Detail`;
CREATE TABLE `Order_Detail` (
  `order_id` int(11) NOT NULL DEFAULT '0',
  `order_detail_id` int(11) NOT NULL AUTO_INCREMENT,
  `item_size_id` int(11) NOT NULL DEFAULT '0',
  `external_id` varchar(50) DEFAULT NULL,
  `menu_type_name` varchar(50) DEFAULT NULL,
  `size_name` varchar(25) NOT NULL DEFAULT 'size',
  `size_print_name` varchar(25) DEFAULT NULL,
  `item_name` varchar(50) NOT NULL DEFAULT 'item name',
  `item_print_name` varchar(50) DEFAULT NULL,
  `name` varchar(50) DEFAULT NULL,
  `note` varchar(250) DEFAULT NULL,
  `quantity` int(11) NOT NULL DEFAULT '0',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00',
  `item_total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `item_total_w_mods` decimal(10,2) DEFAULT NULL,
  `item_tax` decimal(10,2) DEFAULT NULL,
  `created` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`order_detail_id`),
  KEY `fk_OD_order_id` (`order_id`),
  KEY `item_size_id` (`item_size_id`),
  CONSTRAINT `fk_OD_order_id` FOREIGN KEY (`order_id`) REFERENCES `Orders` (`order_id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=583044 DEFAULT CHARSET=utf8 COMMENT='History of all placed order details';

-- ----------------------------
--  Table structure for `Order_Detail_Modifier`
-- ----------------------------
DROP TABLE IF EXISTS `Order_Detail_Modifier`;
CREATE TABLE `Order_Detail_Modifier` (
  `order_detail_id` int(11) NOT NULL DEFAULT '0',
  `order_detail_mod_id` int(11) NOT NULL AUTO_INCREMENT,
  `modifier_size_id` int(11) NOT NULL DEFAULT '0',
  `external_id` varchar(50) DEFAULT NULL,
  `modifier_item_id` int(11) NOT NULL,
  `modifier_item_priority` int(11) NOT NULL COMMENT 'for use in print order',
  `modifier_group_id` int(11) NOT NULL DEFAULT '0',
  `modifier_group_name` varchar(50) DEFAULT NULL,
  `mod_name` varchar(100) NOT NULL DEFAULT '',
  `mod_print_name` varchar(55) DEFAULT NULL,
  `modifier_type` char(2) NOT NULL DEFAULT 'T',
  `comes_with` char(1) NOT NULL DEFAULT 'N',
  `hold_it` char(1) NOT NULL,
  `mod_quantity` int(11) NOT NULL DEFAULT '0',
  `mod_price` decimal(10,3) NOT NULL DEFAULT '0.000',
  `mod_total_price` decimal(10,3) NOT NULL DEFAULT '0.000',
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`order_detail_mod_id`),
  KEY `mod_sizeprice_id` (`modifier_size_id`),
  KEY `fk_ODM_order_detail_id` (`order_detail_id`),
  CONSTRAINT `fk_ODM_order_detail_id` FOREIGN KEY (`order_detail_id`) REFERENCES `Order_Detail` (`order_detail_id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=1045278 DEFAULT CHARSET=utf8 COMMENT='History of all placed order detail modifiers';

-- ----------------------------
--  Table structure for `Orders`
-- ----------------------------
DROP TABLE IF EXISTS `Orders`;
CREATE TABLE `Orders` (
  `order_id` int(11) NOT NULL AUTO_INCREMENT,
  `stamp` varchar(100) DEFAULT NULL,
  `merchant_id` int(11) NOT NULL DEFAULT '0',
  `order_dt_tm` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `user_id` int(11) NOT NULL DEFAULT '0',
  `pickup_dt_tm` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `order_amt` decimal(10,2) NOT NULL DEFAULT '0.00',
  `promo_code` varchar(50) DEFAULT NULL,
  `promo_id` int(11) DEFAULT NULL,
  `promo_amt` decimal(10,2) NOT NULL DEFAULT '0.00',
  `total_tax_amt` decimal(10,2) NOT NULL DEFAULT '0.00',
  `trans_fee_amt` decimal(10,2) NOT NULL DEFAULT '0.00',
  `delivery_amt` decimal(10,2) NOT NULL DEFAULT '0.00',
  `tip_amt` decimal(10,2) NOT NULL DEFAULT '0.00',
  `customer_donation_amt` decimal(10,2) NOT NULL DEFAULT '0.00',
  `grand_total` decimal(10,2) NOT NULL DEFAULT '0.00',
  `grand_total_to_merchant` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'does NOT include: tip,trans_fee,cust_donation',
  `cash` char(1) DEFAULT NULL,
  `merchant_donation_amt` decimal(10,2) NOT NULL DEFAULT '0.00',
  `note` varchar(250) DEFAULT NULL,
  `status` char(1) NOT NULL DEFAULT '',
  `order_qty` int(11) NOT NULL DEFAULT '0',
  `payment_file` varchar(50) DEFAULT NULL,
  `order_type` char(1) DEFAULT NULL,
  `phone_no` varchar(18) DEFAULT NULL,
  `user_delivery_location_id` int(11) DEFAULT NULL,
  `requested_delivery_time` varchar(50) DEFAULT NULL,
  `device_type` varchar(25) DEFAULT NULL,
  `app_version` varchar(10) NOT NULL,
  `skin_id` int(4) NOT NULL DEFAULT '0',
  `distance_from_store` decimal(10,2) NOT NULL,
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`order_id`),
  KEY `merchant_id` (`merchant_id`),
  KEY `fk_O_user_id` (`user_id`),
  KEY `order_type` (`order_type`),
  KEY `status` (`status`),
  KEY `order_dt_tm` (`order_dt_tm`),
  KEY `created` (`created`),
  KEY `user_id` (`user_id`),
  KEY `skin_id` (`skin_id`),
  KEY `pickup_dt_tm` (`pickup_dt_tm`),
  CONSTRAINT `fk_O_user_id` FOREIGN KEY (`user_id`) REFERENCES `User` (`user_id`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `Orders_ibfk_2` FOREIGN KEY (`order_type`) REFERENCES `Lookup` (`type_id_value`) ON DELETE NO ACTION ON UPDATE NO ACTION,
  CONSTRAINT `Orders_ibfk_3` FOREIGN KEY (`status`) REFERENCES `Lookup` (`type_id_value`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=280439 DEFAULT CHARSET=utf8 COMMENT='History of all placed orders';

-- ----------------------------
--  Table structure for `Promo`
-- ----------------------------
DROP TABLE IF EXISTS `Promo`;
CREATE TABLE `Promo` (
  `promo_id` int(11) NOT NULL AUTO_INCREMENT,
  `promo_key_word` varchar(20) NOT NULL,
  `description` varchar(255) NOT NULL,
  `promo_type` int(11) NOT NULL DEFAULT '1',
  `public` char(1) NOT NULL DEFAULT 'Y' COMMENT 'Y is everyone gets it, N is must be unlocked',
  `offer` char(1) NOT NULL DEFAULT 'N',
  `points` int(11) NOT NULL DEFAULT '0',
  `payor_merchant_user_id` int(11) NOT NULL COMMENT '1=splickit, 2=merchant user',
  `reallocate` char(1) NOT NULL DEFAULT 'N',
  `valid_on_first_order_only` char(1) NOT NULL DEFAULT 'N',
  `start_date` date DEFAULT NULL COMMENT 'The first day it is valid',
  `end_date` date DEFAULT NULL COMMENT 'The last day this promo is valid',
  `max_use` int(2) NOT NULL,
  `max_redemptions` int(11) NOT NULL DEFAULT '0',
  `max_dollars_to_spend` decimal(10,2) NOT NULL DEFAULT '0.00',
  `current_number_of_redemptions` int(11) NOT NULL DEFAULT '0',
  `current_dollars_spent` decimal(10,2) NOT NULL DEFAULT '0.00',
  `active` char(1) NOT NULL DEFAULT 'Y',
  `show_in_app` char(1) NOT NULL DEFAULT 'Y',
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`promo_id`),
  KEY `promo_key_word` (`promo_key_word`)
) ENGINE=InnoDB AUTO_INCREMENT=201 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `Promo_Key_Word_Map`
-- ----------------------------
DROP TABLE IF EXISTS `Promo_Key_Word_Map`;
CREATE TABLE `Promo_Key_Word_Map` (
  `map_id` int(11) NOT NULL AUTO_INCREMENT,
  `promo_id` int(11) NOT NULL,
  `promo_key_word` varchar(100) NOT NULL,
  `brand_id` int(11) NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`map_id`),
  KEY `promo_id` (`promo_id`,`promo_key_word`),
  KEY `brand_id` (`brand_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1051 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `Promo_Merchant_Map`
-- ----------------------------
DROP TABLE IF EXISTS `Promo_Merchant_Map`;
CREATE TABLE `Promo_Merchant_Map` (
  `map_id` int(11) NOT NULL AUTO_INCREMENT,
  `promo_id` int(11) NOT NULL,
  `merchant_id` int(11) NOT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `max_discount_per_order` decimal(10,2) DEFAULT NULL COMMENT 'deprecated. now set in type 1 map',
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`map_id`),
  KEY `promo_id` (`promo_id`,`merchant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2456 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `Promo_Message_Map`
-- ----------------------------
DROP TABLE IF EXISTS `Promo_Message_Map`;
CREATE TABLE `Promo_Message_Map` (
  `map_id` int(11) NOT NULL AUTO_INCREMENT,
  `promo_id` int(11) NOT NULL,
  `message1` varchar(200) DEFAULT NULL COMMENT 'Congratulations.  Everything satisfied',
  `message2` varchar(200) DEFAULT NULL COMMENT 'Almost. 1 satisfied but not 2',
  `message3` varchar(200) DEFAULT NULL COMMENT 'Almost. 2 satisfied but not 1',
  `message4` varchar(200) DEFAULT NULL COMMENT 'qualified, but needs to add the promo item(s)',
  `message5` varchar(200) DEFAULT NULL COMMENT 'correct code but no Q,1 or 2',
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`map_id`),
  KEY `promo_id` (`promo_id`)
) ENGINE=InnoDB AUTO_INCREMENT=89 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `Promo_Type1_Amt_Map`
-- ----------------------------
DROP TABLE IF EXISTS `Promo_Type1_Amt_Map`;
CREATE TABLE `Promo_Type1_Amt_Map` (
  `map_id` int(11) NOT NULL AUTO_INCREMENT,
  `promo_id` int(11) NOT NULL,
  `qualifying_amt` decimal(10,2) NOT NULL,
  `promo_amt` decimal(10,2) NOT NULL,
  `percent_off` int(11) NOT NULL,
  `max_amt_off` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'only used for a percent off promo',
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`map_id`),
  KEY `promo_id` (`promo_id`)
) ENGINE=InnoDB AUTO_INCREMENT=51 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `Promo_Type2_Item_Map`
-- ----------------------------
DROP TABLE IF EXISTS `Promo_Type2_Item_Map`;
CREATE TABLE `Promo_Type2_Item_Map` (
  `map_id` int(11) NOT NULL AUTO_INCREMENT,
  `promo_id` int(11) NOT NULL,
  `qualifying_object` varchar(20) NOT NULL COMMENT 'a ''0'' indicates all objects qualify',
  `promo_item_1` varchar(20) DEFAULT NULL,
  `promo_item_2` varchar(20) DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`map_id`),
  KEY `promo_id` (`promo_id`)
) ENGINE=InnoDB AUTO_INCREMENT=38 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `Promo_Type3_User_Map`
-- ----------------------------
DROP TABLE IF EXISTS `Promo_Type3_User_Map`;
CREATE TABLE `Promo_Type3_User_Map` (
  `map_id` int(11) NOT NULL AUTO_INCREMENT,
  `promo_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `executed` char(1) NOT NULL DEFAULT 'N',
  `end_date` date NOT NULL DEFAULT '2100-01-01',
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`map_id`),
  UNIQUE KEY `promo_id_2` (`promo_id`,`user_id`),
  KEY `promo_id` (`promo_id`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `Promo_User_Map`
-- ----------------------------
DROP TABLE IF EXISTS `Promo_User_Map`;
CREATE TABLE `Promo_User_Map` (
  `map_id` int(11) NOT NULL AUTO_INCREMENT,
  `promo_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `times_used` int(11) NOT NULL DEFAULT '0',
  `times_allowed` int(11) NOT NULL DEFAULT '0',
  `end_date` date NOT NULL DEFAULT '2100-01-01' COMMENT 'The first day this is no longer valid',
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`map_id`),
  UNIQUE KEY `promo_id_2` (`promo_id`,`user_id`),
  KEY `promo_id` (`promo_id`,`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=14505 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `Property`
-- ----------------------------
DROP TABLE IF EXISTS `Property`;
CREATE TABLE `Property` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL,
  `value` varchar(255) NOT NULL,
  `comments` varchar(255) DEFAULT NULL,
  `created` datetime NOT NULL,
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=93 DEFAULT CHARSET=utf8;

INSERT INTO `Property` VALUES(1, 'use_qwert', 'false', NULL, '2011-02-04 15:49:08', '2011-11-03 18:43:06');
INSERT INTO `Property` VALUES(2, 'log_level', '5', NULL, '0000-00-00 00:00:00', '2013-03-07 14:01:50');
INSERT INTO `Property` VALUES(3, 'ordering_shutdown', 'false', NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00');
INSERT INTO `Property` VALUES(4, 'system_shutdown', 'false', NULL, '0000-00-00 00:00:00', '2012-05-01 21:52:47');
INSERT INTO `Property` VALUES(5, 'fax_SAR', 'getFaxKey', NULL, '2011-06-27 12:45:43', '2011-06-27 16:45:47');
INSERT INTO `Property` VALUES(6, 'test_addr_fax', '1234567890', NULL, '0000-00-00 00:00:00', '2012-04-02 01:23:14');
INSERT INTO `Property` VALUES(7, 'test_addr_email', 'adam@dummy.com', NULL, '0000-00-00 00:00:00', '2012-08-24 14:04:46');
INSERT INTO `Property` VALUES(8, 'test_addr_ivr', '9999999999', NULL, '0000-00-00 00:00:00', '2012-04-06 03:35:15');
INSERT INTO `Property` VALUES(9, 'test_addr_sms', '1234567890', NULL, '0000-00-00 00:00:00', '2012-07-31 16:51:00');
INSERT INTO `Property` VALUES(10, 'test_addr_emailticket', 'adam@dummy.com', NULL, '2011-07-07 19:55:45', '2012-08-24 14:04:37');
INSERT INTO `Property` VALUES(12, 'gprs_log_level', '4', NULL, '2011-07-15 10:25:20', '2012-07-11 15:55:05');
INSERT INTO `Property` VALUES(13, 'windowsservice_log_level', '1', NULL, '2011-07-18 10:44:57', '2012-07-19 22:53:19');
INSERT INTO `Property` VALUES(15, 'fax_log_level', '1', NULL, '2011-07-18 10:45:04', '2011-11-11 01:11:46');
INSERT INTO `Property` VALUES(17, 'log_level_admin', '5', NULL, '0000-00-00 00:00:00', '2012-10-29 13:52:41');
INSERT INTO `Property` VALUES(19, 'log_level_message_manager', '5', NULL, '0000-00-00 00:00:00', '2012-10-17 16:26:49');
INSERT INTO `Property` VALUES(21, 'gprs_time_span', '5', NULL, '2011-08-25 09:47:23', '2011-11-11 01:13:20');
INSERT INTO `Property` VALUES(23, 'email_log_level', '1', NULL, '2011-07-15 10:25:20', '2012-04-26 19:07:25');
INSERT INTO `Property` VALUES(25, 'ping_log_level', '1', NULL, '2011-07-15 10:25:20', '2012-05-07 22:19:34');
INSERT INTO `Property` VALUES(27, 'text_log_level', '3', NULL, '2011-11-14 10:59:02', '2012-07-06 17:49:24');
INSERT INTO `Property` VALUES(29, 'test_addr_text', '1234567890', NULL, '2011-11-14 10:59:02', '2012-07-11 15:33:31');
INSERT INTO `Property` VALUES(33, 'global_rewardr_active', 'N', NULL, '0000-00-00 00:00:00', '2012-07-31 15:39:39');
INSERT INTO `Property` VALUES(35, 'rewardr_server', 'rewardr-test.splickit.com', NULL, '2011-11-14 17:34:45', '2012-01-06 17:39:37');
INSERT INTO `Property` VALUES(37, 'rewardr_log_level', '1', NULL, '0000-00-00 00:00:00', '2012-01-20 19:24:02');
INSERT INTO `Property` VALUES(39, 'alert_list_email', 'adam@dummy.com', NULL, '0000-00-00 00:00:00', '2012-03-08 01:28:45');
INSERT INTO `Property` VALUES(41, 'alert_list_sms', '1234567890', NULL, '2012-01-17 10:44:44', '2012-03-08 01:28:45');
INSERT INTO `Property` VALUES(43, 'alert_list_sms_test', '', NULL, '2012-01-17 10:46:14', '2012-11-08 11:48:40');
INSERT INTO `Property` VALUES(45, 'opie_log_level', '3', NULL, '2012-01-17 12:36:58', '2012-01-17 19:37:00');
INSERT INTO `Property` VALUES(46, 'server', 'laptop', NULL, '2012-01-26 17:33:26', '2012-07-31 16:50:05');
INSERT INTO `Property` VALUES(48, 'default_server_timezone', 'America/Denver', NULL, '2012-02-14 14:49:34', '2013-05-21 06:54:52');
INSERT INTO `Property` VALUES(50, 'default_server_timezone_offset', '-7', NULL, '0000-00-00 00:00:00', '2013-05-21 06:54:46');
INSERT INTO `Property` VALUES(54, 'minimum_iphone_version', '3.0', NULL, '2012-03-04 17:26:14', '2012-03-08 00:54:38');
INSERT INTO `Property` VALUES(56, 'minimum_android_version', '2.3', NULL, '2012-03-04 17:26:14', '2012-03-05 18:08:55');
INSERT INTO `Property` VALUES(59, 'test_addr_windowsservice', '123456', NULL, '2012-03-09 16:01:40', '2012-03-10 06:01:42');
INSERT INTO `Property` VALUES(60, 'use_primary_sms', 'true', NULL, '0000-00-00 00:00:00', '2013-10-09 11:59:50');
INSERT INTO `Property` VALUES(61, 'gprs_tunnel_merchant_shutdown', 'false', NULL, '0000-00-00 00:00:00', '2013-10-09 11:59:01');
INSERT INTO `Property` VALUES(62, 'menu_log_level', '3', NULL, '0000-00-00 00:00:00', '2012-08-19 18:24:50');
INSERT INTO `Property` VALUES(64, 'test_addr_text_firm7', '1234567890', NULL, '0000-00-00 00:00:00', '2013-01-03 16:22:46');
INSERT INTO `Property` VALUES(67, 'text_response_monitor_on', 'true', NULL, '0000-00-00 00:00:00', '2012-08-24 21:48:54');
INSERT INTO `Property` VALUES(65, 'mandrill_key', 'getMandrilKey', NULL, '2012-08-15 21:19:14', '2012-08-16 09:50:17');
INSERT INTO `Property` VALUES(66, 'monitor_sms_number', '1234567890', NULL, '2012-08-23 15:24:55', '2012-08-23 15:24:55');
INSERT INTO `Property` VALUES(69, 'test_email_messages_on', 'false', NULL, '0000-00-00 00:00:00', '2013-09-30 12:17:07');
INSERT INTO `Property` VALUES(70, 'test_sms_messages_on', 'false', NULL, '0000-00-00 00:00:00', '2013-01-09 17:16:34');
INSERT INTO `Property` VALUES(71, 'call_center_on', 'true', NULL, '0000-00-00 00:00:00', '2013-10-09 11:59:04');
INSERT INTO `Property` VALUES(72, 'email_string_error', 'adam@dummy.com', NULL, '0000-00-00 00:00:00', '2013-01-09 17:17:09');
INSERT INTO `Property` VALUES(79, 'ordering_off_message', 'This merchant is not currently accepting mobile/online orders. Please try again soon.', NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00');
INSERT INTO `Property` VALUES(73, 'email_string_error_testing', 'adam@dummy.com;tarek@dummy.com', NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00');
INSERT INTO `Property` VALUES(74, 'email_string_support', 'adam@dummy.com', NULL, '0000-00-00 00:00:00', '2013-01-09 17:17:09');
INSERT INTO `Property` VALUES(78, 'worker_message_load', '100', NULL, '0000-00-00 00:00:00', '2013-10-09 11:59:42');
INSERT INTO `Property` VALUES(75, 'message_priority', '2', NULL, '0000-00-00 00:00:00', '2013-10-09 11:59:42');
INSERT INTO `Property` VALUES(76, 'engineering_alert_list_sms', '3038844083', NULL, '2013-01-08 07:52:22', '2013-01-08 07:52:22');
INSERT INTO `Property` VALUES(77, 'test_mandril_fail', 'false', NULL, '0000-00-00 00:00:00', '2013-10-09 11:58:56');
INSERT INTO `Property` VALUES(80, 'use_alternate_fax', 'false', NULL, '0000-00-00 00:00:00', '2013-10-09 11:58:51');
INSERT INTO `Property` VALUES(81, 'jm_username', 'splickit', NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00');
INSERT INTO `Property` VALUES(82, 'jm_password', 'Spelunk$5marble', NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00');
INSERT INTO `Property` VALUES(83, 'duplicate_order_test', 'false', NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00');
INSERT INTO `Property` VALUES(84, 'remote_ping_server', 'pweb01', NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00');
INSERT INTO `Property` VALUES(85, 'ignore_duplicate_order', 'true', NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00');
INSERT INTO `Property` VALUES(86, 'global_throttling', 'true', NULL, '2013-05-29 13:32:40', '2013-10-09 11:59:17');
INSERT INTO `Property` VALUES(87, 'gprs_total_merchant_shutdown', 'false', NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00');
INSERT INTO `Property` VALUES(88, 'testable_user_log_level', '5', NULL, '2013-06-25 07:58:36', '0000-00-00 00:00:00');
INSERT INTO `Property` VALUES(89, 'shadow_printer_on', 'N', NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00');
INSERT INTO `Property` VALUES(90, 'shadow_printer_number', '9784216777', NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00');
INSERT INTO `Property` VALUES(91, 'use_merchant_caching', 'true', 'set to true to use merchant caching', '2013-08-11 09:10:46', '2013-10-09 11:59:14');
INSERT INTO `Property` VALUES(92, 'fax_service_list', 'Phaxio,Faxage', 'comma separated list of fax providers.', '0000-00-00 00:00:00', '2013-10-09 11:58:51');
INSERT INTO `Property` VALUES(93, 'gprs_late_threshold_in_seconds', '240', 'the point at which the late message logic is executed', '0000-00-00 00:00:00', '2013-10-09 11:58:51');
INSERT INTO `Property` VALUES(94, 'protocol_domain', 'http://localhost:8888', 'the string to prepend onto call backs and links', now(),'0000-00-00 00:00:00');
INSERT INTO `Property` VALUES(95, 'script_two_time_seconds', '480', 'the point at which a script 2 is sent for a late message', now(), '0000-00-00 00:00:00');
INSERT INTO `Property` VALUES(96, 'inspire_pay_ordering_on', 'true', NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00');
INSERT INTO `Property` VALUES(97, 'mercury_pay_ordering_on', 'true', NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00');
INSERT INTO `Property` VALUES(98, 'fpn_ordering_on', 'true', NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00');
INSERT INTO `Property` VALUES(101, 'cc_processor_ordering_off_message', 'Sorry, but due to a network issue, we cannot deliver orders to this merchant at this time. Please try again shortly.', NULL, '0000-00-00 00:00:00', '0000-00-00 00:00:00');
INSERT INTO `Property` VALUES(102, 'epsonprinter_log_level', '6', NULL, '2011-07-15 10:25:20', '2012-04-26 19:07:25');
INSERT INTO `Property` VALUES(103, 'new_shadow_device_on', 'true', NULL, '2011-07-15 10:25:20', '2012-04-26 19:07:25');
INSERT INTO `Property` VALUES(104, 'new_shadow_message_type', 'SUW', NULL, '2011-07-15 10:25:20', '2012-04-26 19:07:25');
INSERT INTO `Property` VALUES(105, 'new_shadow_message_frequency', '1', NULL, '2011-07-15 10:25:20', '2012-04-26 19:07:25');
INSERT INTO `Property` VALUES(106, 'use_mock_calls', 'true', NULL, '2011-07-15 10:25:20', '2012-04-26 19:07:25');
INSERT INTO `Property` VALUES(107, 'do_not_call_out_to_aws', 'true', NULL, '2011-07-15 10:25:20', '2012-04-26 19:07:25');
INSERT INTO `Property` VALUES(108, 'long_query_threshold', '5', NULL, '2011-07-15 10:25:20', '2012-04-26 19:07:25');
INSERT INTO `Property` VALUES(109, 'google_geocode_key', 'getKeyFromGoogle', 'api key for google geocode', '2011-07-15 10:25:20', '2012-04-26 19:07:25');
INSERT INTO `Property` VALUES(110, 'system_shutdown_message', 'We''re currently upgrading our software and the servers are off line for a few minutes. We''ll be back up shortly. Sorry for the inconvenience.', 'message', '2011-07-15 10:25:20', '2012-04-26 19:07:25');
INSERT INTO `Property` VALUES(111, 'check_cvv', 'false', NULL, '2011-07-15 10:25:20', '2012-04-26 19:07:25');
insert into `Property` (name, value, created) values ('property_table_loaded', 'true', '2015-02-23 13:00:00');


-- ----------------------------
--  Table structure for `Push_SQL_Query`
-- ----------------------------
DROP TABLE IF EXISTS `Push_SQL_Query`;
CREATE TABLE `Push_SQL_Query` (
  `sql_id` int(11) NOT NULL AUTO_INCREMENT,
  `query_name` varchar(50) NOT NULL,
  `skin` varchar(50) NOT NULL,
  `description` varchar(255) NOT NULL,
  `sql_text` text NOT NULL,
  `message_text` varchar(255) NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`sql_id`)
) ENGINE=InnoDB AUTO_INCREMENT=116 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `Sizes`
-- ----------------------------
DROP TABLE IF EXISTS `Sizes`;
CREATE TABLE `Sizes` (
  `size_id` int(11) NOT NULL AUTO_INCREMENT,
  `menu_type_id` int(11) NOT NULL,
  `external_size_id` varchar(255) DEFAULT NULL,
  `size_name` varchar(100) NOT NULL DEFAULT '',
  `size_print_name` varchar(50) DEFAULT NULL,
  `description` varchar(100) NOT NULL,
  `priority` int(11) NOT NULL,
  `active` char(1) NOT NULL DEFAULT 'Y',
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`size_id`),
  KEY `menu_type_id` (`menu_type_id`),
  CONSTRAINT `fk_MT_menu_type_id` FOREIGN KEY (`menu_type_id`) REFERENCES `Menu_Type` (`menu_type_id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=91310 DEFAULT CHARSET=utf8 COMMENT='To maintain list of sizes';

INSERT INTO `Sizes` VALUES(0, 0, NULL, 'System Default', NULL, 'System Default', 0, 'Y', '0000-00-00 00:00:00', '2011-10-07 22:48:30', 'N');
Update Sizes set size_id = 0 WHERE size_id = LAST_INSERT_ID();

-- ----------------------------
--  Table structure for `Skin`
-- ----------------------------
DROP TABLE IF EXISTS `Skin`;
CREATE TABLE `Skin` (
  `skin_id` int(11) NOT NULL AUTO_INCREMENT,
  `skin_name` varchar(25) NOT NULL,
  `skin_description` varchar(255) NOT NULL,
  `brand_id` int(11) DEFAULT NULL,
  `mobile_app_type` varchar(100) NOT NULL DEFAULT 'B',
  `external_identifier` varchar(50) NOT NULL,
  `custom_skin_message` varchar(255) DEFAULT NULL COMMENT 'message to be displayed on load of session in skin',
  `welcome_letter_file` varchar(255) DEFAULT NULL,
  `in_production` char(1) NOT NULL DEFAULT 'N',
  `rewardr_active` char(1) NOT NULL DEFAULT 'N',
  `web_ordering_active` char(1) NOT NULL DEFAULT 'N',
  `donation_active` char(1) NOT NULL DEFAULT 'N',
  `donation_organization` varchar(255) DEFAULT NULL,
  `facebook_thumbnail_link` varchar(255) DEFAULT NULL COMMENT 'App store link for this skin',
  `facebook_thumbnail_url` varchar(255) DEFAULT NULL COMMENT 'url to our image for this skin',
  `android_marketplace_link` varchar(255) DEFAULT NULL,
  `twitter_handle` varchar(64) DEFAULT NULL,
  `iphone_certificate_file_name` varchar(50) NOT NULL,
  `current_iphone_version` varchar(10) NOT NULL DEFAULT '3.0.0',
  `current_android_version` varchar(10) NOT NULL DEFAULT '2.3.0',
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`skin_id`),
  KEY `moblie_device_type` (`mobile_app_type`),
  CONSTRAINT `Skin_ibfk_1` FOREIGN KEY (`mobile_app_type`) REFERENCES `Lookup` (`type_id_value`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=251 DEFAULT CHARSET=utf8 COMMENT='To maintian list of merchant branded mobile application skin';

INSERT INTO `Skin` VALUES(1, 'Splickit', 'Our Splickit Branded Iphone App', 400, 'A', 'com.splickit.order', 'the custom skin message', '', 'Y', 'Y', 'Y', 'Y', 'Community Food Share', 'http://itunes.apple.com/us/app/splick-it/id375047368?mt=8', 'http://www.splickit.com/icons/icon_splickit2.png', 'http://www.splickit.com/android', 'splickit', 'com.splickit.order.pem', '3.0.0', '2.3.0', '2010-07-14 22:37:43', '2012-09-21 13:15:24', 'N');

-- ----------------------------
--  Table structure for `Skin_Images_Map`
-- ----------------------------
DROP TABLE IF EXISTS `Skin_Images_Map`;
CREATE TABLE `Skin_Images_Map` (
  `map_id` int(11) NOT NULL AUTO_INCREMENT,
  `skin_id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `primary_color_hex` varchar(10) NOT NULL,
  `primary_color_name` varchar(20) NOT NULL,
  `secondary_color_hex` varchar(10) NOT NULL,
  `secondary_color_name` varchar(20) NOT NULL,
  `icon_image` varchar(50) NOT NULL,
  `divider_image_one` varchar(50) NOT NULL,
  `divider_image_two` varchar(50) NOT NULL,
  `hi_res_icon` varchar(50) NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`map_id`)
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `Skin_Merchant_Map`
-- ----------------------------
DROP TABLE IF EXISTS `Skin_Merchant_Map`;
CREATE TABLE `Skin_Merchant_Map` (
  `map_id` int(11) NOT NULL AUTO_INCREMENT,
  `skin_id` int(11) NOT NULL,
  `merchant_id` int(11) NOT NULL,
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`map_id`),
  UNIQUE KEY `idx_skin_merchant` (`skin_id`,`merchant_id`) USING BTREE,
  KEY `fk_SMM_skin_id` (`skin_id`),
  KEY `fk_SMM_merchant_id` (`merchant_id`),
  CONSTRAINT `fk_SMM_merchant_id` FOREIGN KEY (`merchant_id`) REFERENCES `Merchant` (`merchant_id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `fk_SMM_message_id` FOREIGN KEY (`skin_id`) REFERENCES `Skin` (`skin_id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=9846 DEFAULT CHARSET=utf8 COMMENT='To associate Skin and Merchant tables';

-- ----------------------------
--  Table structure for `Tax`
-- ----------------------------
DROP TABLE IF EXISTS `Tax`;
CREATE TABLE `Tax` (
  `tax_id` int(11) NOT NULL AUTO_INCREMENT,
  `merchant_id` int(11) NOT NULL DEFAULT '0',
  `tax_group` int(11) NOT NULL DEFAULT '1',
  `locale` varchar(40) NOT NULL,
  `locale_description` varchar(100) NOT NULL DEFAULT ' ',
  `rate` decimal(10,4) NOT NULL DEFAULT '0.0000',
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`tax_id`),
  KEY `fk_T_merchant_id` (`merchant_id`),
  KEY `locale` (`locale`),
  CONSTRAINT `fk_T_merchant_id` FOREIGN KEY (`merchant_id`) REFERENCES `Merchant` (`merchant_id`) ON DELETE CASCADE ON UPDATE NO ACTION,
  CONSTRAINT `Tax_ibfk_1` FOREIGN KEY (`locale`) REFERENCES `Lookup` (`type_id_value`) ON DELETE NO ACTION ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=8149 DEFAULT CHARSET=utf8 COMMENT='To list all local sales taxes required';

-- ----------------------------
--  Table structure for `Temp_Merchant`
-- ----------------------------
DROP TABLE IF EXISTS `Temp_Merchant`;
CREATE TABLE `Temp_Merchant` (
  `merchant_id` int(11) NOT NULL DEFAULT '0',
  `merchant_user_id` int(11) NOT NULL DEFAULT '0',
  `merchant_external_id` varchar(50) DEFAULT NULL,
  `numeric_id` int(11) DEFAULT NULL,
  `alphanumeric_id` varchar(20) DEFAULT NULL,
  `rewardr_programs` varchar(255) DEFAULT NULL,
  `shop_email` varchar(100) NOT NULL,
  `brand_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `display_name` varchar(100) DEFAULT NULL,
  `address1` varchar(100) NOT NULL DEFAULT '',
  `address2` varchar(100) DEFAULT '',
  `city` varchar(100) NOT NULL DEFAULT '',
  `state` char(2) NOT NULL,
  `zip` varchar(100) NOT NULL DEFAULT '',
  `country` varchar(100) NOT NULL DEFAULT 'US',
  `lat` decimal(10,6) NOT NULL DEFAULT '0.000000',
  `lng` decimal(10,6) NOT NULL DEFAULT '0.000000',
  `EIN_SS` varchar(20) CHARACTER SET ucs2 DEFAULT NULL,
  `description` text,
  `phone_no` varchar(100) NOT NULL DEFAULT '',
  `fax_no` varchar(100) DEFAULT '',
  `twitter_handle` varchar(100) DEFAULT NULL,
  `time_zone` varchar(10) NOT NULL,
  `cross_street` varchar(1000) DEFAULT NULL,
  `trans_fee_type` char(1) NOT NULL DEFAULT 'F',
  `trans_fee_rate` decimal(10,2) NOT NULL DEFAULT '0.00',
  `show_tip` char(1) NOT NULL DEFAULT 'Y',
  `donate` decimal(10,0) NOT NULL DEFAULT '0',
  `cc_processor` char(1) DEFAULT NULL,
  `merchant_type` char(1) DEFAULT 'N',
  `active` char(1) NOT NULL DEFAULT 'N',
  `ordering_on` char(1) NOT NULL DEFAULT 'Y',
  `show_search` char(1) NOT NULL DEFAULT 'Y',
  `lead_time` int(11) NOT NULL DEFAULT '15',
  `immediate_message_delivery` char(1) NOT NULL DEFAULT 'N',
  `delivery` char(1) NOT NULL DEFAULT 'N',
  `advanced_ordering` char(1) NOT NULL DEFAULT 'N',
  `order_del_type` varchar(10) NOT NULL DEFAULT 'T',
  `order_del_addr` varchar(100) DEFAULT NULL,
  `order_del_addr2` varchar(100) DEFAULT NULL,
  `payment_cycle` char(1) NOT NULL DEFAULT 'W',
  `minimum_iphone_version` decimal(10,3) NOT NULL DEFAULT '2.040',
  `minimum_android_version` decimal(10,3) NOT NULL DEFAULT '2.040',
  `live_dt` date NOT NULL,
  `facebook_caption_link` varchar(255) DEFAULT NULL COMMENT 'link to stores web site',
  `custom_order_message` varchar(100) DEFAULT NULL,
  `custom_menu_message` varchar(255) DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `logical_delete` char(1) NOT NULL DEFAULT 'N'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `User`
-- ----------------------------
DROP TABLE IF EXISTS `User`;
CREATE TABLE `User` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `uuid` varchar(40) DEFAULT NULL,
  `account_hash` varchar(50) DEFAULT NULL,
  `last_four` int(4) NULL default NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `password` varchar(100) NOT NULL,
  `contact_no` varchar(100) DEFAULT NULL,
  `device_id` varchar(50) DEFAULT NULL,
  `balance` decimal(10,2) NOT NULL DEFAULT '0.00',
  `credit_limit` decimal(10,2) NOT NULL DEFAULT '-1.00',
  `trans_fee_override` decimal(10,2) DEFAULT NULL,
  `flags` varchar(10) NOT NULL DEFAULT '1000000001',
  `referrer` varchar(100) DEFAULT NULL,
  `last_order_merchant_id` int(11) DEFAULT NULL,
  `skin_name` varchar(50) NOT NULL,
  `skin_id` int(11) NOT NULL,
  `device_type` varchar(25) NOT NULL,
  `app_version` decimal(10,3) DEFAULT NULL,
  `bad_login_count` int(1) NOT NULL DEFAULT '1',
  `orders` int(11) NOT NULL DEFAULT '0' COMMENT 'holds the total number of orders to date',
  `points_lifetime` int(11) NOT NULL DEFAULT '0',
  `points_current` int(11) NOT NULL DEFAULT '0',
  `rewardr_participation` char(1) NOT NULL DEFAULT 'Y',
  `custom_message` varchar(50) DEFAULT NULL,
  `segment` char(1) CHARACTER SET ucs2 NOT NULL DEFAULT 'C',
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `logical_delete` varchar(10) NOT NULL DEFAULT 'N',
  `loyalty_number_x` varchar(255) NOT NULL,
  PRIMARY KEY (`user_id`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `uuid` (`uuid`),
  KEY `idx_last_name` (`last_name`),
  KEY `idx_first_name` (`first_name`) USING BTREE,
  KEY `points_lifetime` (`points_lifetime`,`points_current`),
  KEY `created` (`created`),
  KEY `skin_name` (`skin_name`),
  KEY `skin_id` (`skin_id`),
  KEY `flags` (`flags`)
) ENGINE=InnoDB AUTO_INCREMENT=288236 DEFAULT CHARSET=utf8 COMMENT='To maintain user accounts';

INSERT INTO `User` VALUES(1000, "1234-abcde-56789-fghij", NULL, 0, 'First', 'Last', 'default@dummy.com', "$2a$10$f4ea2dd5f8351e7bba386uEd8bIbR7tVtYoJ2T0dBkcJVWT2YOOTm", NULL, NULL, 0.00, -1.00, NULL, '1000000001', '', NULL, 'com.splickit.order', 1, 'system', 0.000, 1, 0, 0, 0, 'N', NULL, 'D', NOW(), NOW(), 'N', '');
INSERT INTO `User` VALUES(1, NULL, NULL, 0, 'Admin', 'Admin', 'admin', '$2a$10$cfc7398db15f617922f76ed0k81mfuYi5GgXvUqZVNLp0wkK7a4pe', NULL, NULL, 0.00, -1.00, NULL, '1000000001', '', NULL, 'com.splickit.order', 1, 'iphone', 0.000, 1, 0, 0, 0, 'N', NULL, 'D', '2010-04-24 14:38:24', '2013-08-05 12:22:57', 'N', '');
INSERT INTO `User` VALUES(9999, NULL, NULL, 0, 'Admin', 'Admin', 'nouser', '$2a$10$4b10d4c110ae71683653cOspUva2l3.3bmEj/2SXvXzeRcXW9fLGC', NULL, NULL, 0.00, -1.00, NULL, '1000000001', '', NULL, 'com.splickit.order', 1, 'iphone', 0.000, 1, 0, 0, 0, 'N', NULL, 'D', '2010-04-24 14:38:24', '2012-05-04 03:37:50', 'N', '');

-- ----------------------------
--  Table structure for `User_Brand_Points_Map`
-- ----------------------------
DROP TABLE IF EXISTS `User_Brand_Points_Map`;
CREATE TABLE `User_Brand_Points_Map` (
  `map_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `brand_id` int(11) NOT NULL,
  `loyalty_number` varchar(255) NOT NULL,
  `points` int(11) NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`map_id`),
  KEY `user_id` (`user_id`,`brand_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2051 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `User_Delivery_Location`
-- ----------------------------
DROP TABLE IF EXISTS `User_Delivery_Location`;
CREATE TABLE `User_Delivery_Location` (
  `user_addr_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `address1` varchar(100) NOT NULL DEFAULT '',
  `address2` varchar(100) DEFAULT '',
  `city` varchar(100) NOT NULL DEFAULT '',
  `state` char(2) NOT NULL,
  `zip` varchar(100) NOT NULL DEFAULT '',
  `lat` decimal(10,6) NOT NULL DEFAULT '0.000000',
  `lng` decimal(10,6) NOT NULL DEFAULT '0.000000',
  `phone_no` varchar(100) NOT NULL DEFAULT '',
  `instructions` varchar(255) DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `logical_delete` varchar(10) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`user_addr_id`)
) ENGINE=InnoDB AUTO_INCREMENT=16162 DEFAULT CHARSET=utf8 COMMENT='To maintain user delivery locations';

INSERT INTO `User_Delivery_Location` VALUES(100, 1,'Default','Deleted Address, Call Customer',null,'XX','XX','00000',0,0,'0','Call Customer',NOW(),NOW(),'N');
-- ----------------------------
--  Table structure for `User_Messaging_Setting_Map`
-- ----------------------------
DROP TABLE IF EXISTS `User_Messaging_Setting_Map`;
CREATE TABLE `User_Messaging_Setting_Map` (
  `map_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `skin_id` int(11) NOT NULL,
  `messaging_type` varchar(25) NOT NULL,
  `device_type` varchar(50) NOT NULL,
  `device_id` varchar(50) NOT NULL,
  `token` varchar(255) NOT NULL,
  `active` char(1) NOT NULL DEFAULT 'Y',
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`map_id`),
  KEY `skin_id` (`skin_id`),
  KEY `device_id` (`device_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=115391 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `User_Skin_Donation`
-- ----------------------------
DROP TABLE IF EXISTS `User_Skin_Donation`;
CREATE TABLE `User_Skin_Donation` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `skin_id` int(11) NOT NULL,
  `donation_active` char(1) NOT NULL DEFAULT 'Y',
  `donation_type` char(1) NOT NULL DEFAULT 'R' COMMENT 'R is roundup, F is fixed',
  `donation_amt` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT 'if fixed type, how much for each order',
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`,`skin_id`),
  KEY `user_id_2` (`user_id`),
  KEY `skin_id` (`skin_id`)
) ENGINE=InnoDB AUTO_INCREMENT=100 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `User_Social`
-- ----------------------------
DROP TABLE IF EXISTS `User_Social`;
CREATE TABLE `User_Social` (
  `social_map_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) DEFAULT NULL,
  `created` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` timestamp NULL DEFAULT NULL,
  `facebook_key` varchar(255) DEFAULT NULL,
  `twitter_secret` varchar(255) DEFAULT NULL,
  `twitter_token` varchar(255) DEFAULT NULL,
  `twitter_user_id` varchar(255) DEFAULT NULL,
  `facebook_user_id` varchar(255) DEFAULT NULL,
  `twitter_screen_name` varchar(255) DEFAULT NULL,
  `twitter_consumer_key` varchar(255) NOT NULL DEFAULT '2eU7vBV0W86IKnXrXzPkQ',
  `twitter_consumer_secret` varchar(255) NOT NULL DEFAULT 'b1Q1utMzpYY1KqiBUmNaoOnqkKc5u36gpDKxiHSQ',
  `logical_delete` char(1) NOT NULL,
  PRIMARY KEY (`social_map_id`),
  KEY `index_User_Social_on_splickit_id` (`user_id`),
  KEY `index_User_Social_on_facebook_user_id` (`facebook_user_id`),
  KEY `index_User_Social_on_twitter_user_id` (`twitter_user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2442 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `Zip_Lookup`
-- ----------------------------
DROP TABLE IF EXISTS `Zip_Lookup`;
CREATE TABLE `Zip_Lookup` (
  `zip_tz_id` int(11) NOT NULL AUTO_INCREMENT,
  `zip` varchar(5) NOT NULL,
  `city` varchar(50) NOT NULL,
  `state` varchar(2) NOT NULL,
  `lat` decimal(10,6) NOT NULL,
  `lng` decimal(10,6) NOT NULL,
  `time_zone_offset` int(11) NOT NULL,
  `dst` int(11) NOT NULL,
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`zip_tz_id`),
  UNIQUE KEY `zip` (`zip`),
  KEY `state` (`state`)
) ENGINE=InnoDB AUTO_INCREMENT=42138 DEFAULT CHARSET=utf8;

INSERT INTO `Zip_Lookup` VALUES(12053, '30302', 'Atlanta', 'GA', 33.750000, -84.400000, -5, 1, '0000-00-00 00:00:00', '2013-02-04 10:46:53');
INSERT INTO `Zip_Lookup` VALUES(35075, '80302', 'Boulder', 'CO', 40.038629, -105.371668, -7, 1, '0000-00-00 00:00:00', '2013-02-04 10:46:54');
INSERT INTO `Zip_Lookup` VALUES(3596, '10038', 'New York', 'NY', 40.707150, -74.002102, -5, 1, '0000-00-00 00:00:00', '2013-02-04 10:46:54');
INSERT INTO `Zip_Lookup` VALUES(2777, '07090', 'Westfield', 'NJ', 40.707150, -76.002102, -5, 1, '0000-00-00 00:00:00', '2013-02-04 10:46:54');

-- ----------------------------
--  Table structure for `adm_AS_merchant_status`
-- ----------------------------
DROP TABLE IF EXISTS `adm_AS_merchant_status`;
CREATE TABLE `adm_AS_merchant_status` (
  `section` bigint(20) NOT NULL DEFAULT '0',
  `merchant_id` int(11) NOT NULL DEFAULT '0',
  `field1` longtext CHARACTER SET latin1 NOT NULL,
  `field2` longtext CHARACTER SET latin1,
  `field3` varchar(300) CHARACTER SET latin1 DEFAULT NULL,
  `field4` varchar(12) CHARACTER SET latin1 DEFAULT NULL,
  KEY `section` (`section`),
  KEY `merchant_id` (`merchant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `adm_AS_welcome_letter`
-- ----------------------------
DROP TABLE IF EXISTS `adm_AS_welcome_letter`;
CREATE TABLE `adm_AS_welcome_letter` (
  `object_id` int(11) NOT NULL DEFAULT '0',
  `shop_email` varchar(100) NOT NULL,
  `username` varchar(20) CHARACTER SET latin1 DEFAULT NULL,
  `sus_login_password` varchar(20) CHARACTER SET latin1 DEFAULT NULL,
  `url` varchar(52) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `adm_ach`
-- ----------------------------
DROP TABLE IF EXISTS `adm_ach`;
CREATE TABLE `adm_ach` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `fk_id` int(11) NOT NULL,
  `merchant_id` varchar(14) NOT NULL,
  `name_on_acct` varchar(256) NOT NULL,
  `routing` varchar(15) NOT NULL,
  `account` varchar(15) NOT NULL,
  `acct_holder_type` varchar(14) NOT NULL DEFAULT 'Business',
  `acct_type` varchar(14) NOT NULL DEFAULT 'Checking',
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `acct_address` varchar(256) NOT NULL,
  `acct_city` varchar(256) NOT NULL,
  `acct_st` char(2) NOT NULL,
  `acct_zip` varchar(10) NOT NULL,
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `logical_delete` varchar(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=8090 DEFAULT CHARSET=utf8 COMMENT='To record the merchant ACH Bank Account info';

-- ----------------------------
--  Table structure for `adm_merchant_email`
-- ----------------------------
DROP TABLE IF EXISTS `adm_merchant_email`;
CREATE TABLE `adm_merchant_email` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `merchant_id` varchar(14) NOT NULL,
  `name` varchar(254) DEFAULT 'Merchant',
  `email` varchar(254) NOT NULL,
  `daily` varchar(1) NOT NULL DEFAULT 'N',
  `weekly` varchar(1) NOT NULL DEFAULT 'N',
  `admin` varchar(1) NOT NULL DEFAULT 'N',
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  `logical_delete` varchar(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`id`),
  UNIQUE KEY `merchant_email` (`merchant_id`,`email`)
) ENGINE=InnoDB AUTO_INCREMENT=5296 DEFAULT CHARSET=utf8 COMMENT='To record email associated with a Merchant Location';

-- ----------------------------
--  Table structure for `adm_order_reversal`
-- ----------------------------
DROP TABLE IF EXISTS `adm_order_reversal`;
CREATE TABLE `adm_order_reversal` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `credit_type` char(1) NOT NULL,
  `order_id` int(11) NOT NULL,
  `amount` decimal(32,2) NOT NULL DEFAULT '0.00',
  `invoice` varchar(34) DEFAULT NULL,
  `note` text,
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `order_id` (`order_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2763 DEFAULT CHARSET=utf8 COMMENT='To maintain orders that need to be reversed';

-- ----------------------------
--  Table structure for `adm_statement`
-- ----------------------------
DROP TABLE IF EXISTS `adm_statement`;
CREATE TABLE `adm_statement` (
  `X` varchar(1) NOT NULL DEFAULT '',
  `generation` varchar(41) DEFAULT NULL,
  `invoice` varchar(34) DEFAULT NULL,
  `merchant_id` int(14) unsigned NOT NULL,
  `merchant_external_id` varchar(50) DEFAULT NULL,
  `period` varchar(86) DEFAULT NULL,
  `name` varchar(100) DEFAULT '',
  `address1` varchar(100) DEFAULT '',
  `address2` varchar(100) DEFAULT '',
  `city` varchar(100) DEFAULT '',
  `state` char(2) DEFAULT NULL,
  `zip` varchar(100) DEFAULT '',
  `inc_trans_pay_cycle` char(1) DEFAULT 'W',
  `order_qty` decimal(33,0) DEFAULT NULL,
  `order_cnt` decimal(21,0) DEFAULT NULL,
  `order_amt` decimal(32,2) DEFAULT '0.00',
  `total_tax_amt` decimal(32,2) DEFAULT '0.00',
  `tip_amt` decimal(32,2) DEFAULT '0.00',
  `promo_amt` decimal(32,2) DEFAULT '0.00',
  `trans_fee_amt` decimal(32,2) DEFAULT '0.00',
  `delivery_amt` decimal(32,2) DEFAULT '0.00',
  `grand_total` decimal(34,2) DEFAULT '0.00',
  `cc_fee_amt` decimal(42,2) DEFAULT NULL,
  `pymt_fee` decimal(10,2) DEFAULT '0.50',
  `customer_donation_amt` decimal(32,2) DEFAULT '0.00',
  `merch_trans_fee` decimal(32,2) DEFAULT NULL,
  `total_fees` decimal(58,2) DEFAULT NULL,
  `owner_cdt_purch` decimal(32,2) DEFAULT '0.00',
  `goodwill` decimal(54,2) NOT NULL DEFAULT '0.00',
  `net_proceeds` decimal(59,2) DEFAULT NULL,
  `payment_id` varchar(15) NOT NULL DEFAULT '',
  `weekly` longtext CHARACTER SET latin1,
  `email` longtext NOT NULL,
  `billed_comm_fee` int(1) NOT NULL DEFAULT '0',
  `previous_balance` decimal(48,2) NOT NULL DEFAULT '0.00',
  `payment_file` char(0) NOT NULL DEFAULT '',
  `payment` decimal(58,2) DEFAULT NULL,
  `balance` int(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`merchant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `adm_statement_detail`
-- ----------------------------
DROP TABLE IF EXISTS `adm_statement_detail`;
CREATE TABLE `adm_statement_detail` (
  `merchant_id` int(11) NOT NULL DEFAULT '0',
  `x` bigint(20) DEFAULT NULL,
  `days` varchar(107) DEFAULT NULL,
  `order_cnt` bigint(20) NOT NULL DEFAULT '0',
  `order_amt` decimal(32,2) DEFAULT NULL,
  `total_tax_amt` decimal(32,2) DEFAULT NULL,
  `promo_amt` decimal(32,2) DEFAULT NULL,
  `delivery_amt` decimal(32,2) DEFAULT NULL,
  `tip_amt` decimal(32,2) DEFAULT NULL,
  `grand_total` decimal(32,2) DEFAULT NULL,
  `cc_amt` decimal(41,2) DEFAULT NULL,
  `net_proceed` decimal(41,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `adm_winapp`
-- ----------------------------
DROP TABLE IF EXISTS `adm_winapp`;
CREATE TABLE `adm_winapp` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `merchant_id` int(11) NOT NULL DEFAULT '0',
  `username` varchar(100) NOT NULL DEFAULT '',
  `password` varchar(100) NOT NULL DEFAULT '',
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`id`),
  UNIQUE KEY `merchant_id` (`merchant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `android_test_push_data`
-- ----------------------------
DROP TABLE IF EXISTS `android_test_push_data`;
CREATE TABLE `android_test_push_data` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `message_title` varchar(100) NOT NULL,
  `message_text` varchar(255) NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=21352 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `customer_service_results`
-- ----------------------------
DROP TABLE IF EXISTS `customer_service_results`;
CREATE TABLE `customer_service_results` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `task` varchar(20) NOT NULL,
  `result` varchar(50) NOT NULL,
  `data` text NOT NULL,
  `merchant_id` int(11) NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`),
  KEY `order_id` (`order_id`,`task`,`merchant_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1864 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `db_message_log`
-- ----------------------------
DROP TABLE IF EXISTS `db_message_log`;
CREATE TABLE `db_message_log` (
  `log_id` int(11) NOT NULL AUTO_INCREMENT,
  `message_id` int(11) NOT NULL,
  `message_text` text,
  `stamp` varchar(50) DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`log_id`),
  KEY `message_id` (`message_id`)
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `gprs_printer_call_in_history`
-- ----------------------------
DROP TABLE IF EXISTS `gprs_printer_call_in_history`;
CREATE TABLE `gprs_printer_call_in_history` (
  `merchant_id` int(11) NOT NULL,
  `last_call_in` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`merchant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `gprs_printer_fails`
-- ----------------------------
DROP TABLE IF EXISTS `gprs_printer_fails`;
CREATE TABLE `gprs_printer_fails` (
  `merchant_id` int(11) NOT NULL,
  `active` char(1) NOT NULL DEFAULT 'Y',
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `merchant_id` (`merchant_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `hour_back`
-- ----------------------------
DROP TABLE IF EXISTS `hour_back`;
CREATE TABLE `hour_back` (
  `hour_id` int(11) NOT NULL DEFAULT '0',
  `merchant_id` int(11) NOT NULL DEFAULT '0',
  `hour_type` varchar(10) NOT NULL DEFAULT 'R',
  `day_of_week` varchar(10) NOT NULL DEFAULT '0',
  `open` time NOT NULL DEFAULT '00:00:00',
  `close` time NOT NULL DEFAULT '00:00:00',
  `day_open` char(1) NOT NULL DEFAULT 'Y',
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `logical_delete` char(1) NOT NULL DEFAULT 'N'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `ls_items_all`
-- ----------------------------
DROP TABLE IF EXISTS `ls_items_all`;
CREATE TABLE `ls_items_all` (
  `FK_ItemID` varbinary(85) DEFAULT NULL,
  `MenuType` varchar(13) DEFAULT NULL,
  `ItemName` varchar(98) DEFAULT NULL,
  `Size` varchar(254) CHARACTER SET latin1 DEFAULT NULL,
  `Price` decimal(10,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `ls_menuitems`
-- ----------------------------
DROP TABLE IF EXISTS `ls_menuitems`;
CREATE TABLE `ls_menuitems` (
  `FK_ItemID` varbinary(85) DEFAULT NULL,
  `ModifierGroupName` varchar(254) CHARACTER SET latin1 DEFAULT NULL,
  `MenuIngredientID` varchar(11) DEFAULT NULL,
  `Priority` int(11) DEFAULT NULL,
  `Price` decimal(10,2) DEFAULT NULL,
  `Size` varchar(254) CHARACTER SET latin1 DEFAULT NULL,
  `Required` bigint(20) DEFAULT NULL,
  `InventoryName` varchar(254) CHARACTER SET latin1 DEFAULT NULL,
  `DisplayName` varchar(254) CHARACTER SET latin1 DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `ls_menuitems_all`
-- ----------------------------
DROP TABLE IF EXISTS `ls_menuitems_all`;
CREATE TABLE `ls_menuitems_all` (
  `FK_ItemID` varbinary(85) DEFAULT NULL,
  `ModifierGroupName` varchar(254) CHARACTER SET latin1 DEFAULT NULL,
  `MenuIngredientID` varchar(11) DEFAULT NULL,
  `Priority` int(11) DEFAULT NULL,
  `Price` decimal(10,2) DEFAULT NULL,
  `Size` varchar(254) CHARACTER SET latin1 DEFAULT NULL,
  `Required` bigint(20) DEFAULT NULL,
  `InventoryName` varchar(254) CHARACTER SET latin1 DEFAULT NULL,
  `DisplayName` varchar(254) CHARACTER SET latin1 DEFAULT NULL,
  KEY `MGN_idx` (`ModifierGroupName`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `ls_menuitems_all_temp`
-- ----------------------------
DROP TABLE IF EXISTS `ls_menuitems_all_temp`;
CREATE TABLE `ls_menuitems_all_temp` (
  `FK_ItemID` varbinary(85) DEFAULT NULL,
  `ModifierGroupName` varchar(254) CHARACTER SET latin1 DEFAULT NULL,
  `MenuIngredientID` varchar(11) DEFAULT NULL,
  `Priority` int(11) DEFAULT NULL,
  `Price` decimal(10,3) DEFAULT NULL,
  `Size` varchar(254) CHARACTER SET latin1 DEFAULT NULL,
  `Required` bigint(20) DEFAULT NULL,
  `InventoryName` varchar(254) CHARACTER SET latin1 DEFAULT NULL,
  `DisplayName` varchar(254) CHARACTER SET latin1 DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `ls_td_menuitems`
-- ----------------------------
DROP TABLE IF EXISTS `ls_td_menuitems`;
CREATE TABLE `ls_td_menuitems` (
  `FK_ItemID` varbinary(85) DEFAULT NULL,
  `ModifierGroupName` varchar(254) CHARACTER SET latin1 DEFAULT NULL,
  `MenuIngredientID` varchar(11) DEFAULT NULL,
  `Priority` int(11) DEFAULT NULL,
  `Price` decimal(11,3) DEFAULT NULL,
  `Size` varchar(254) CHARACTER SET latin1 DEFAULT NULL,
  `Required` bigint(20) DEFAULT NULL,
  `InventoryName` varchar(254) CHARACTER SET latin1 DEFAULT NULL,
  `DisplayName` varchar(254) CHARACTER SET latin1 DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `merchant_list_request_location`
-- ----------------------------
DROP TABLE IF EXISTS `merchant_list_request_location`;
CREATE TABLE `merchant_list_request_location` (
  `lat` decimal(10,6) NOT NULL,
  `long` decimal(10,6) NOT NULL,
  `user_id` int(11) NOT NULL,
  `skin_id` int(11) NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `created` (`created`),
  KEY `skin_id` (`skin_id`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `request_times`
-- ----------------------------
DROP TABLE IF EXISTS `request_times`;
CREATE TABLE `request_times` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stamp` varchar(100) NOT NULL,
  `request_url` varchar(255) NOT NULL,
  `total_request_time` float(12,8) NOT NULL,
  `total_query_times` float(12,8) NOT NULL,
  `total_db_connection_time` float(12,8) NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`),
  KEY `request_url` (`request_url`),
  KEY `stamp` (`stamp`)
) ENGINE=InnoDB AUTO_INCREMENT=8504 DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `statements`
-- ----------------------------
DROP TABLE IF EXISTS `statements`;
CREATE TABLE `statements` (
  `merchant_id` int(11) NOT NULL DEFAULT '0',
  `order_amt` decimal(32,2) NOT NULL DEFAULT '0.00',
  `order_qty` decimal(32,0) NOT NULL DEFAULT '0',
  `total_tax_amt` decimal(32,2) NOT NULL DEFAULT '0.00',
  `tip_amt` decimal(32,2) NOT NULL DEFAULT '0.00',
  `promo_amt` decimal(32,2) NOT NULL DEFAULT '0.00',
  `order_cnt` bigint(20) NOT NULL DEFAULT '0',
  `customer_donation_amt` decimal(32,2) NOT NULL DEFAULT '0.00',
  `grand_total` decimal(32,2) NOT NULL DEFAULT '0.00',
  `trans_fee_amt` decimal(32,2) NOT NULL DEFAULT '0.00',
  `monthly_fee` double(19,2) NOT NULL DEFAULT '0.00',
  `pymt_Fee` decimal(10,2) NOT NULL DEFAULT '0.00',
  `cc_trans_Fee` decimal(10,2) NOT NULL DEFAULT '0.00',
  `cc_discount_Rate` decimal(10,4) NOT NULL DEFAULT '0.0000',
  `app_mtnc` double(19,2) NOT NULL DEFAULT '0.00',
  `mth_act` bigint(11) NOT NULL DEFAULT '0',
  `owner_cdt_purch` decimal(32,2) NOT NULL DEFAULT '0.00',
  `company` varchar(100) DEFAULT '',
  `address` varchar(201) DEFAULT '',
  `csz` varchar(204) DEFAULT '',
  `phone_no` varchar(100) NOT NULL DEFAULT '',
  `fax_no` varchar(100) NOT NULL DEFAULT '',
  `time_zone` varchar(10) NOT NULL,
  `cross_street` varchar(1000) NOT NULL,
  `mer_name` varchar(201) DEFAULT '',
  `company_name` varchar(150) DEFAULT NULL,
  `mer_address` varchar(201) DEFAULT '',
  `mer_csz` varchar(204) DEFAULT '',
  `mer_phone_no` varchar(100) DEFAULT '',
  `mer_fax_no` varchar(100) DEFAULT '',
  `email` varchar(100) DEFAULT '',
  `tc_acceptance` varchar(10) DEFAULT NULL,
  `lead_time` int(11) NOT NULL DEFAULT '5',
  `generation` varchar(73) DEFAULT NULL,
  `period` date DEFAULT '0000-00-00',
  `cc_fee_amt` decimal(40,2) NOT NULL DEFAULT '0.00',
  `total_fees` double(19,2) NOT NULL DEFAULT '0.00',
  `net_proceeds` double(19,2) NOT NULL DEFAULT '0.00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `tmp_activity_test`
-- ----------------------------
DROP TABLE IF EXISTS `tmp_activity_test`;
CREATE TABLE `tmp_activity_test` (
  `object_id` int(11) NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `user_creation_data`
-- ----------------------------
DROP TABLE IF EXISTS `user_creation_data`;
CREATE TABLE `user_creation_data` (
  `user_id` int(11) NOT NULL,
  `skin_id` int(11) NOT NULL,
  `lat` decimal(10,6) NOT NULL,
  `lng` decimal(10,6) NOT NULL,
  `dist_to_closest_skin_store` decimal(10,2) NOT NULL,
  `device_type` varchar(20) NOT NULL,
  `time_diff_till_first_order_days` int(11) DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`user_id`),
  KEY `skin_id` (`skin_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `user_no_cc_failure`
-- ----------------------------
DROP TABLE IF EXISTS `user_no_cc_failure`;
CREATE TABLE `user_no_cc_failure` (
  `user_id` int(11) NOT NULL,
  `skin_id` int(11) NOT NULL,
  `distance_to_store` decimal(10,2) DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `user_password_reset`
-- ----------------------------
DROP TABLE IF EXISTS `user_password_reset`;
CREATE TABLE `user_password_reset` (
  `reset_id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `token` varchar(255) NOT NULL,
  `retrieved` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00' ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`reset_id`)
) ENGINE=InnoDB AUTO_INCREMENT=705 DEFAULT CHARSET=latin1;

-- ----------------------------
--  Table structure for `utility_sql`
-- ----------------------------
DROP TABLE IF EXISTS `utility_sql`;
CREATE TABLE `utility_sql` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sql_name` varchar(100) NOT NULL,
  `sql_description` varchar(255) NOT NULL,
  `sql_text` text NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8;
INSERT INTO `utility_sql` VALUES(4, 'daily_report_part1', 'gets the items and modifiers that were ordered today', 'SELECT z.*  \r\nFROM (SELECT ''Name'',''Size_Name'',''Price'',''Qty'',''Revenue'' FROM Orders WHERE order_id = 0 LIMIT 1\r\nUNION\r\nSELECT item_name as name, size_name as size_name, price, SUM(quantity) AS Qty , SUM(b.item_total) AS Revenue\r\nFROM Orders U\r\nJOIN Order_Detail b ON b.order_id = U.order_id\r\nWHERE b.logical_delete = "N"\r\nAND U.status="E"\r\nAND U.merchant_id = ZZZZ\r\nAND pickup_dt_tm > ''OOOO'' AND pickup_dt_tm < ''CCCC''\r\nGROUP BY  item_name, price\r\nUNION\r\nSELECT a.mod_name as name,   NULL as size_name, a.mod_price as price, SUM(a.mod_quantity) AS Qty , SUM(a.mod_total_price) AS Revenue\r\nFROM Orders U\r\nJOIN Order_Detail b ON U.order_id = b.order_id\r\nJOIN Order_Detail_Modifier a ON b.order_detail_id = a.order_detail_id\r\nWHERE b.logical_delete = "N"\r\nAND a.mod_price !=0\r\nAND U.status="E"\r\nAND U.merchant_id  = ZZZZ\r\nAND pickup_dt_tm > ''OOOO'' AND pickup_dt_tm < ''CCCC''\r\nGROUP BY  a.mod_name) z', '2012-02-02 08:25:03', '0000-00-00 00:00:00', 'N');
INSERT INTO `utility_sql` VALUES(5, 'daily_report_part2', 'not sure', 'SELECT z.*  \r\nFROM (SELECT ''merchant id'',''external id'',''order id'',''name'',''order time'',''order amt'',''order qty'',''total tax'',''tip'',''promo amt'',''transaction fee'',''grand total'' FROM Orders WHERE order_id = 0 LIMIT 1 \r\nUNION \r\nSELECT c.merchant_id, m.merchant_external_id, c.order_id,   CONCAT(a.first_name," " , a.last_name) as name, DATE_FORMAT(c.order_dt_tm, "%l:%i %p" )  order_dt_tm,\r\n       c.order_amt as order_amt,\r\n       c.order_qty,\r\n       c.total_tax_amt, c.tip_amt,\r\n       c.promo_amt,\r\n       c.trans_fee_amt,\r\n       c.grand_total as grand_total\r\nFROM User a\r\nJOIN Orders c ON a.user_id = c.user_id\r\nJOIN Merchant m ON c.merchant_id = m.merchant_id\r\nWHERE c.logical_delete = "N"\r\nAND c.status="E"\r\nAND m.merchant_id  = ZZZZ\r\nAND pickup_dt_tm > ''OOOO'' AND pickup_dt_tm < ''CCCC'' \r\n\r\nUNION\r\n\r\nSELECT 9999999 as merchant_id, NULL as merchant_external_id, NULL as order_id,   ''Total'' as name, NULL as order_dt_tm,\r\n        SUM(c.order_amt) as order_amt,\r\n        SUM(c.order_qty) as order_qty,\r\n       SUM(c.total_tax_amt) as total_tax_amt,\r\n       SUM(c.tip_amt) as tip_amt,\r\n       SUM(c.promo_amt) as promo_amt,\r\n       SUM(trans_fee_amt) as trans_fee,\r\n       SUM(c.grand_total ) as  grand_total\r\nFROM User a\r\nJOIN Orders c ON a.user_id = c.user_id\r\nJOIN Merchant m ON c.merchant_id = m.merchant_id\r\nWHERE c.logical_delete = "N"\r\nAND c.status="E"\r\nAND m.merchant_id  = ZZZZ\r\nAND pickup_dt_tm > ''OOOO'' AND pickup_dt_tm < ''CCCC'') z', '2012-02-02 08:27:26', '2012-04-08 00:00:00', 'N');
INSERT INTO `utility_sql` VALUES(6, 'daily_report_part3', 'not sure', 'SELECT z.*  \r\nFROM (SELECT ''merchant id'',''merchant external id'',''order id'',''name'',''size name'',''price'',''qty'',''revenue'' FROM Orders WHERE order_id = 0 LIMIT 1 \r\nUNION \r\nSELECT U.merchant_id, merchant_external_id, U.order_id, a.mod_name as name,   NULL as size_name, a.mod_price as price, SUM(a.mod_quantity) AS Qty , SUM(a.mod_total_price) AS Revenue\r\nFROM Orders U\r\nJOIN Order_Detail b ON U.order_id = b.order_id\r\nJOIN Order_Detail_Modifier a ON b.order_detail_id = a.order_detail_id\r\nJOIN Merchant m ON m.merchant_id = U.merchant_id\r\nWHERE b.logical_delete = "N"\r\nAND a.order_detail_id = b.order_detail_id\r\nAND U.merchant_id=m.merchant_id\r\nAND a.mod_price !=0\r\nAND b.order_id = U.order_id\r\nAND U.status="E"\r\nAND m.merchant_id  = ZZZZ\r\nAND pickup_dt_tm > ''OOOO'' AND pickup_dt_tm < ''CCCC'' \r\nGROUP BY  U.merchant_id, U.order_id, a.mod_name, a.mod_price\r\nUNION\r\nSELECT  U.merchant_id, merchant_external_id, U.order_id, a.mod_name as name,   NULL as size_name, a.mod_price as price, SUM(a.mod_quantity) AS Qty , SUM(a.mod_total_price) AS Revenue\r\nFROM Orders U\r\nJOIN Order_Detail b ON U.order_id = b.order_id\r\nJOIN Merchant m ON m.merchant_id = U.merchant_id\r\nJOIN Order_Detail_Modifier a ON b.order_detail_id = a.order_detail_id\r\nWHERE   b.logical_delete = "N"\r\nAND U.status="E"\r\nAND m.merchant_id  = ZZZZ\r\nAND pickup_dt_tm > ''OOOO'' AND pickup_dt_tm < ''CCCC'' GROUP BY  U.merchant_id, U.order_id, a.mod_name, a.mod_price) z', '2012-02-02 08:27:26', '0000-00-00 00:00:00', 'N');

-- ----------------------------
--  Table structure for `xxrefund_data`
-- ----------------------------
DROP TABLE IF EXISTS `xxrefund_data`;
CREATE TABLE `xxrefund_data` (
  `order_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `message` varchar(100) NOT NULL,
  `processed` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `success` char(1) NOT NULL,
  `fail_reason` mediumtext NOT NULL,
  UNIQUE KEY `order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- ----------------------------
--  Table structure for `zzsys_user`
-- ----------------------------
DROP TABLE IF EXISTS `zzsys_user`;
CREATE TABLE `zzsys_user` (
  `zzsys_user_id` varchar(15) CHARACTER SET latin1 NOT NULL DEFAULT '',
  `sus_zzsys_user_group_id` varchar(15) CHARACTER SET latin1 NOT NULL DEFAULT '',
  `sus_name` varchar(50) CHARACTER SET latin1 DEFAULT NULL,
  `sus_login_name` varchar(20) CHARACTER SET latin1 DEFAULT NULL,
  `sus_login_password` varchar(20) CHARACTER SET latin1 DEFAULT NULL,
  `sys_added` datetime DEFAULT NULL,
  `sys_changed` datetime DEFAULT NULL,
  `sys_user_id` varchar(15) CHARACTER SET latin1 DEFAULT NULL,
  `merchant_id` int(11) DEFAULT NULL,
  PRIMARY KEY (`zzsys_user_id`),
  KEY `sus_zzsys_user_group_id` (`sus_zzsys_user_group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_general_ci;


-- ----------------------------
--  Table structure for `Device_Blacklist`
-- ----------------------------
DROP TABLE IF EXISTS `Device_Blacklist`;
CREATE TABLE `Device_Blacklist` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `device_id` varchar(100) CHARACTER SET utf8 NOT NULL,
  `created` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `logical_delete` varchar(1) CHARACTER SET utf8 NOT NULL DEFAULT 'N',
  PRIMARY KEY (`id`),
  UNIQUE KEY `device_id_UNIQUE` (`device_id`)
) ENGINE=InnoDB AUTO_INCREMENT=1000 DEFAULT CHARSET=latin1;



-- ----------------------------
--  View structure for `smawv_messages_to_send`
-- ----------------------------
DROP VIEW IF EXISTS `smawv_messages_to_send`;
CREATE ALGORITHM=UNDEFINED DEFINER=`itsquikadmin`@`%` SQL SECURITY DEFINER VIEW `smawv_messages_to_send` AS select `a`.`order_id` AS `order_id`,`b`.`merchant_id` AS `merchant_id`,`a`.`user_id` AS `user_id`,`a`.`status` AS `status`,`b`.`map_id` AS `map_id`,`b`.`message_format` AS `message_format`,`b`.`message_delivery_addr` AS `message_delivery_addr`,`b`.`next_message_dt_tm` AS `next_message_dt_tm`,`b`.`message_type` AS `message_type` from (`Orders` `a` join `Merchant_Message_History` `b`) where ((`a`.`status` in ('O','E','T')) and (`a`.`order_id` = `b`.`order_id`) and (`b`.`locked` in ('N','P')) and (`b`.`sent_dt_tm` = '0000-00-00 00:00:00')) order by `b`.`next_message_dt_tm` WITH CASCADED CHECK OPTION;

-- ----------------------------
--  View structure for `smawv_nb_7day_orders`
-- ----------------------------
DROP VIEW IF EXISTS `smawv_nb_7day_orders`;
CREATE OR REPLACE ALGORITHM=UNDEFINED DEFINER=`root`@`%` SQL SECURITY DEFINER VIEW `smawv_nb_7day_orders`
AS SELECT
  `A`.`status` AS `status`,
  `A`.`payment_file` AS `payment_file`,
  `B`.`order_id` AS `order_id`,
  `U`.`first_name` AS `first_name`,
  `U`.`last_name` AS `last_name`,
  `U`.`email` AS `email`,
  `M`.`name` AS `name`,
  `M`.`address1` AS `address1`,
  `M`.`phone_no` AS `phone_no`,
  `M`.`merchant_id` AS `merchant_id`,
  `M`.`merchant_external_id` AS `merchant_external_id`,
  `M`.`shop_email` AS `shop_email`,
  `B`.`order_dt_tm` AS `order_dt_tm`,
  `B`.`pickup_dt_tm` AS `pickup_dt_tm`,
  `B`.`promo_amt` AS `promo_amt`,
  `B`.`order_amt` AS `order_amt`,
  `B`.`total_tax_amt` AS `total_tax_amt`,
  `B`.`tip_amt` AS `tip_amt`,
  `B`.`customer_donation_amt` AS `customer_donation_amt`,
  `B`.`merchant_donation_amt` AS `merchant_donation_amt`,
  `B`.`trans_fee_amt` AS `trans_fee_amt`,
  `B`.`user_id` AS `user_id`,
  `B`.`promo_code` AS `promo_code`,
  `L`.`type_id_name` AS `type_id_name`,
  `K`.`type_id_name` AS `order_type`,
  `B`.`order_qty` AS `order_qty`,
  `B`.`grand_total` AS `grand_total`,
  `B`.`note` AS `note`,
  `B`.`delivery_amt` AS `delivery_amt`,
  `B`.`cash` AS `cash`
FROM (((((`Orders` `A` join `Orders` `B` on((`A`.`order_id` = `B`.`order_id`))) join `Merchant` `M` on((`B`.`merchant_id` = `M`.`merchant_id`))) left join `Lookup` `L` on(((`L`.`type_id_value` = `B`.`status`) and (`L`.`type_id_field` = 'status')))) left join `Lookup` `K` on(((`K`.`type_id_value` = `B`.`order_type`) and (`K`.`type_id_field` = 'order_type')))) join `User` `U` on((`U`.`user_id` = `B`.`user_id`))) where ((`A`.`created` > (now() - interval 7 day)) and ((`A`.`status` <> 'Y') or (`A`.`payment_file` is not null)));
-- ----------------------------
--  View structure for `smawv_nb_IMGM`
-- ----------------------------
DROP VIEW IF EXISTS `smawv_nb_IMGM`;
CREATE ALGORITHM=UNDEFINED DEFINER=`tdimachkie`@`%` SQL SECURITY DEFINER VIEW `smawv_nb_IMGM` AS select `Item_Modifier_Group_Map`.`map_id` AS `map_id`,`Item_Modifier_Group_Map`.`item_id` AS `item_id`,`Item_Modifier_Group_Map`.`merchant_id` AS `merchant_id`,`Item_Modifier_Group_Map`.`modifier_group_id` AS `modifier_group_id`,`Item_Modifier_Group_Map`.`display_name` AS `display_name`,`Item_Modifier_Group_Map`.`min` AS `min`,`Item_Modifier_Group_Map`.`max` AS `max`,`Item_Modifier_Group_Map`.`price_override` AS `price_override`,`Item_Modifier_Group_Map`.`price_max` AS `price_max`,`Item_Modifier_Group_Map`.`priority` AS `priority`,`Item_Modifier_Group_Map`.`logical_delete` AS `logical_delete` from `Item_Modifier_Group_Map` where (`Item_Modifier_Group_Map`.`merchant_id` = 0);

-- ----------------------------
--  View structure for `smawv_nb_ISM`
-- ----------------------------
DROP VIEW IF EXISTS `smawv_nb_ISM`;
CREATE ALGORITHM=UNDEFINED DEFINER=`itsquik`@`%` SQL SECURITY DEFINER VIEW `smawv_nb_ISM` AS select `Item_Size_Map`.`item_size_id` AS `item_size_id`,`Item_Size_Map`.`item_id` AS `item_id`,`Item_Size_Map`.`size_id` AS `size_id`,`Item_Size_Map`.`price` AS `price`,`Item_Size_Map`.`active` AS `active`,`Item_Size_Map`.`priority` AS `priority`,`Item_Size_Map`.`merchant_id` AS `merchant_id`,`Item_Size_Map`.`logical_delete` AS `logical_delete` from `Item_Size_Map` where (`Item_Size_Map`.`merchant_id` = 0);

-- ----------------------------
--  View structure for `smawv_nb_MSM`
-- ----------------------------
DROP VIEW IF EXISTS `smawv_nb_MSM`;
CREATE ALGORITHM=UNDEFINED DEFINER=`tdimachkie`@`%` SQL SECURITY DEFINER VIEW `smawv_nb_MSM` AS select `MSM`.`modifier_size_id` AS `modifier_size_id`,`MSM`.`modifier_item_id` AS `modifier_item_id`,`MSM`.`external_id` AS `external_id`,`MSM`.`size_id` AS `size_id`,`MSM`.`modifier_price` AS `modifier_price`,`MSM`.`active` AS `active`,`MSM`.`priority` AS `priority`,`MSM`.`merchant_id` AS `merchant_id`,`MSM`.`logical_delete` AS `logical_delete` from `Modifier_Size_Map` `MSM` where (`MSM`.`merchant_id` = 0);

-- ----------------------------
--  View structure for `smawv_nb_merchant_menu_map`
-- ----------------------------
DROP VIEW IF EXISTS `smawv_nb_merchant_menu_map`;
CREATE ALGORITHM=UNDEFINED DEFINER=`itsquikadmin`@`%` SQL SECURITY DEFINER VIEW `smawv_nb_merchant_menu_map` AS select distinct `MMM`.`merchant_id` AS `merchant_id`,`MMM`.`menu_id` AS `menu_id`,`MMM`.`merchant_menu_type` AS `merchant_menu_type` from `Merchant_Menu_Map` `MMM` where (`MMM`.`merchant_menu_type` = 'pickup');

-- ----------------------------
--  View structure for `smawv_nb_orders`
-- ----------------------------
DROP VIEW IF EXISTS `smawv_nb_orders`;
CREATE ALGORITHM=UNDEFINED DEFINER=`itsquik`@`%` SQL SECURITY DEFINER VIEW `smawv_nb_orders` AS select `O`.`order_id` AS `order_id`,concat(`U`.`first_name`,' ',`U`.`last_name`) AS `user`,`U`.`email` AS `email`,`M`.`name` AS `name`,`M`.`address1` AS `address1`,`M`.`phone_no` AS `phone_no`,`O`.`order_dt_tm` AS `order_dt_tm`,`O`.`pickup_dt_tm` AS `pickup_dt_tm`,`O`.`promo_amt` AS `promo_amt`,`O`.`order_amt` AS `order_amt`,`O`.`total_tax_amt` AS `total_tax_amt`,`O`.`tip_amt` AS `tip_amt`,`O`.`customer_donation_amt` AS `customer_donation_amt`,`O`.`merchant_donation_amt` AS `merchant_donation_amt`,`O`.`trans_fee_amt` AS `trans_fee_amt`,`O`.`user_id` AS `user_id`,`O`.`promo_code` AS `promo_code`,`L`.`type_id_name` AS `type_id_name`,`O`.`order_qty` AS `order_qty`,`O`.`grand_total` AS `grand_total`,`O`.`note` AS `note`,`O`.`delivery_amt` AS `delivery_amt` from (((`Orders` `O` join `Merchant` `M` on((`M`.`merchant_id` = `O`.`merchant_id`))) left join `Lookup` `L` on(((`L`.`type_id_value` = `O`.`status`) and (`L`.`type_id_field` = 'status')))) join `User` `U` on((`U`.`user_id` = `O`.`user_id`))) where (`O`.`created` > (now() - interval 30 day)) order by `O`.`order_id` desc;

-- ----------------------------
--  View structure for `smawv_order_details`
-- ----------------------------
DROP VIEW IF EXISTS `smawv_order_details`;
CREATE ALGORITHM=UNDEFINED DEFINER=`itsquikadmin`@`%` SQL SECURITY DEFINER VIEW `smawv_order_details` AS select `o`.`order_id` AS `order_id`,`m`.`name` AS `name`,`i`.`item_name` AS `item_name`,`s`.`size_name` AS `size_name`,`ism`.`price` AS `price`,`odm`.`mod_name` AS `mod_name`,`odm`.`mod_quantity` AS `mod_quantity` from ((((((`Merchant` `m` join `Orders` `o`) join `Item` `i`) join `Sizes` `s`) join `Order_Detail` `od`) join `Item_Size_Map` `ism`) join `Order_Detail_Modifier` `odm`) where ((`o`.`merchant_id` = `m`.`merchant_id`) and (`od`.`order_id` = `o`.`order_id`) and (`od`.`item_size_id` = `ism`.`item_size_id`) and (`ism`.`size_id` = `s`.`size_id`) and (`ism`.`item_id` = `i`.`item_id`) and (`odm`.`order_detail_id` = `od`.`order_detail_id`));

-- ----------------------------
--  Procedure structure for `SMAWSP_ADMIN_CREATE_SKIN`
-- ----------------------------
DROP PROCEDURE IF EXISTS `SMAWSP_ADMIN_CREATE_SKIN`;
delimiter ;;
CREATE DEFINER=`itsquikadmin`@`%` PROCEDURE `SMAWSP_ADMIN_CREATE_SKIN`(IN in_skin_identifier varchar(255), IN in_skin_name varchar(50))
BEGIN

	DECLARE xnew_skin_id int (11);

	BEGIN
	
		INSERT INTO `Skin` (`skin_id`, `skin_name`, `skin_description`, `mobile_app_type`, `external_identifier`, `in_production`, `facebook_thumbnail_link`, `facebook_thumbnail_url`, `android_marketplace_link`, `created`, `modified`, `logical_delete`) VALUES (NULL,in_skin_name,in_skin_name, 'I', in_skin_identifier, 'N', NULL, NULL, NULL, NOW(), '0000-00-00 00:00:00', 'N');

		SET xnew_skin_id = LAST_INSERT_ID();

		INSERT INTO `Skin_Merchant_Map` (`map_id`,`skin_id`,`merchant_id`,`created`,`modified`,`logical_delete`)							VALUES (NULL , xnew_skin_id, '10', NOW(), '0000-00-00 00:00:00', 'N'),
									(NULL , xnew_skin_id, '20', NOW(), '0000-00-00 00:00:00', 'N'),
									(NULL , xnew_skin_id, '30', NOW(), '0000-00-00 00:00:00', 'N'),
									(NULL , xnew_skin_id, '40', NOW(), '0000-00-00 00:00:00', 'N');
	END;
END
 ;;
delimiter ;

-- ----------------------------
--  Procedure structure for `SMAWSP_ADMIN_DEL_MENU`
-- ----------------------------
DROP PROCEDURE IF EXISTS `SMAWSP_ADMIN_DEL_MENU`;
delimiter ;;
CREATE DEFINER=`itsquikadmin`@`%` PROCEDURE `SMAWSP_ADMIN_DEL_MENU`(IN in_menu_id INT(11))
BEGIN
	
UPDATE Menu_Type SET logical_delete = 'Y' WHERE menu_id = in_menu_id;
UPDATE Modifier_Group SET logical_delete = 'Y' WHERE menu_id = in_menu_id;

DELETE FROM Menu_Combo_Price WHERE combo_id IN (SELECT combo_id FROM Menu_Combo WHERE menu_id = in_menu_id);
DELETE FROM Menu_Combo_Association WHERE combo_id IN (SELECT combo_id FROM Menu_Combo WHERE menu_id = in_menu_id);
DELETE FROM Menu_Combo WHERE menu_id = in_menu_id;

DELETE FROM Item_Modifier_Item_Map WHERE item_id IN (SELECT i.item_id FROM Item i, Menu_Type mt WHERE i.menu_type_id = mt.menu_type_id AND mt.menu_id = in_menu_id AND mt.logical_delete = 'Y');

DELETE FROM Item_Modifier_Group_Map WHERE item_id IN (SELECT i.item_id FROM Item i, Menu_Type mt WHERE i.menu_type_id = mt.menu_type_id AND mt.menu_id = in_menu_id AND mt.logical_delete = 'Y');

DELETE FROM Modifier_Size_Map WHERE modifier_item_id IN (SELECT mi.modifier_item_id FROM Modifier_Item mi, Modifier_Group mg WHERE mi.modifier_group_id = mg.modifier_group_id AND mg.menu_id = in_menu_id AND mg.logical_delete = 'Y');

DELETE FROM Modifier_Item WHERE modifier_group_id IN (SELECT mg.modifier_group_id FROM Modifier_Group mg WHERE mg.menu_id = in_menu_id AND mg.logical_delete = 'Y');

DELETE FROM Modifier_Group WHERE menu_id = in_menu_id AND logical_delete = 'Y';

DELETE FROM Item_Size_Map WHERE item_id IN (SELECT i.item_id FROM Item i, Menu_Type mt WHERE i.menu_type_id = mt.menu_type_id AND mt.menu_id = in_menu_id AND mt.logical_delete = 'Y');

DELETE FROM Item WHERE menu_type_id IN (SELECT menu_type_id FROM Menu_Type WHERE menu_id = in_menu_id AND logical_delete = 'Y');

DELETE FROM Sizes WHERE menu_type_id IN (SELECT menu_type_id FROM Menu_Type WHERE menu_id = in_menu_id AND logical_delete = 'Y');

DELETE FROM Menu_Type WHERE menu_id = in_menu_id AND logical_delete = 'Y';

END
 ;;
delimiter ;

-- ----------------------------
--  Procedure structure for `SMAWSP_ADMIN_DEL_MERCHANT`
-- ----------------------------
DROP PROCEDURE IF EXISTS `SMAWSP_ADMIN_DEL_MERCHANT`;
delimiter ;;
CREATE DEFINER=`itsquikadmin`@`%` PROCEDURE `SMAWSP_ADMIN_DEL_MERCHANT`(IN in_merchant_id int(11))
BEGIN

DECLARE xmenu_id int(1);

IF in_merchant_id > 1000 THEN

	SELECT menu_id INTO xmenu_id FROM Merchant_Menu_Map WHERE merchant_id = in_merchant_id AND merchant_menu_type = 'pickup';

	IF xmenu_id > 1000 THEN
		CALL SMAWSP_ADMIN_DEL_MERCHANT_PRICE_RECORDS(xmenu_id,in_merchant_id,'N');
	END IF;

	DELETE FROM Tax WHERE merchant_id = in_merchant_id;
	DELETE FROM `Hour` WHERE merchant_id = in_merchant_id;
	DELETE FROM Merchant_Menu_Map WHERE merchant_id = in_merchant_id;
	DELETE FROM Skin_Merchant_Map WHERE merchant_id = in_merchant_id;
	DELETE FROM Merchant_Message_Map WHERE merchant_id = in_merchant_id;
	DELETE FROM Merchant_Payment_Type WHERE merchant_id = in_merchant_id;
	DELETE FROM Holiday WHERE merchant_id = in_merchant_id;
	DELETE FROM Merchant WHERE merchant_id = in_merchant_id;
	DELETE FROM Merchant_Delivery_Info WHERE merchant_id = in_merchant_id;
	DELETE FROM Merchant_Delivery_Price_Distance WHERE merchant_id = in_merchant_id;
	DELETE FROM Merchant_FPN_Map WHERE merchant_id = in_merchant_id;
	DELETE FROM Merchant_Preptime_Info WHERE merchant_id = in_merchant_id;

END IF;

END
 ;;
delimiter ;

-- ----------------------------
--  Procedure structure for `SMAWSP_ADMIN_DEL_MERCHANT_PRICE_RECORDS`
-- ----------------------------
DROP PROCEDURE IF EXISTS `SMAWSP_ADMIN_DEL_MERCHANT_PRICE_RECORDS`;
delimiter ;;
CREATE DEFINER=`itsquikadmin`@`%` PROCEDURE `SMAWSP_ADMIN_DEL_MERCHANT_PRICE_RECORDS`(IN in_menu_id INT(11), IN in_merchant_id int(11),IN in_delete_all char(1))
BEGIN

IF in_delete_all = 'Y' THEN
	DELETE Item_Modifier_Group_Map FROM Item_Modifier_Group_Map JOIN Item ON Item_Modifier_Group_Map.item_id = Item.Item_id
	JOIN Menu_Type ON Item.menu_type_id = Menu_Type.menu_type_id WHERE Menu_Type.menu_id = in_menu_id AND Item_Modifier_Group_Map.merchant_id > 1000;

	DELETE Modifier_Size_Map FROM Modifier_Size_Map JOIN Modifier_Item ON Modifier_Item.modifier_item_id = Modifier_Size_Map.modifier_item_id
	JOIN Modifier_Group ON Modifier_Group.modifier_group_id = Modifier_Item.modifier_group_id WHERE Modifier_Group.menu_id = in_menu_id AND Modifier_Size_Map.merchant_id > 1000;

	DELETE Item_Size_Map FROM Item_Size_Map JOIN Item ON Item.item_id = Item_Size_Map.item_id
	JOIN Menu_Type ON Item.menu_type_id = Menu_Type.menu_type_id WHERE Menu_Type.menu_id = in_menu_id AND Item_Size_Map.merchant_id > 1000;

	DELETE Menu_Combo_Price FROm Menu_Combo_Price JOIN Menu_Combo ON Menu_Combo_Price.combo_id = Menu_Combo.combo_id WHERE Menu_Combo.menu_id = in_menu_id AND Menu_Combo_Price.merchant_id > 1000;

ELSE
	DELETE Item_Modifier_Group_Map FROM Item_Modifier_Group_Map JOIN Item ON Item_Modifier_Group_Map.item_id = Item.Item_id
	JOIN Menu_Type ON Item.menu_type_id = Menu_Type.menu_type_id WHERE Menu_Type.menu_id = in_menu_id AND Item_Modifier_Group_Map.merchant_id = in_merchant_id;

	DELETE Modifier_Size_Map FROM Modifier_Size_Map JOIN Modifier_Item ON Modifier_Item.modifier_item_id = Modifier_Size_Map.modifier_item_id
	JOIN Modifier_Group ON Modifier_Group.modifier_group_id = Modifier_Item.modifier_group_id WHERE Modifier_Group.menu_id = in_menu_id AND Modifier_Size_Map.merchant_id = in_merchant_id;

	DELETE Item_Size_Map FROM Item_Size_Map JOIN Item ON Item.item_id = Item_Size_Map.item_id
	JOIN Menu_Type ON Item.menu_type_id = Menu_Type.menu_type_id WHERE Menu_Type.menu_id = in_menu_id AND Item_Size_Map.merchant_id = in_merchant_id;

	DELETE Menu_Combo_Price FROm Menu_Combo_Price JOIN Menu_Combo ON Menu_Combo_Price.combo_id = Menu_Combo.combo_id WHERE Menu_Combo.menu_id = in_menu_id AND Menu_Combo_Price.merchant_id = in_merchant_id;

END IF;

END
 ;;
delimiter ;

-- ----------------------------
--  Procedure structure for `SMAWSP_ADMIN_IMGM_DELETER`
-- ----------------------------
DROP PROCEDURE IF EXISTS `SMAWSP_ADMIN_IMGM_DELETER`;
delimiter ;;
CREATE DEFINER=`itsquikadmin`@`%` PROCEDURE `SMAWSP_ADMIN_IMGM_DELETER`(IN in_modfier_group_id INT(11), OUT out_message VARCHAR(255))
BEGIN
	
	DECLARE x_imim_row_count INT(11) default 0;
	DECLARE x_imgm_row_count INT(11) default 0;

	DELETE FROM Item_Modifier_Item_Map WHERE modifier_item_id IN (SELECT modifier_item_id FROM Modifier_Item WHERE modifier_group_id = in_modfier_group_id);

	SELECT ROW_COUNT() INTO x_imim_row_count;

	DELETE FROM Item_Modifier_Group_Map WHERE modifier_group_id = in_modfier_group_id;

	SELECT ROW_COUNT() INTO x_imgm_row_count;

	SET out_message = CONCAT('IMI Rows affected is: ',x_imim_row_count,'   IMG Rows affected is: ', x_imgm_row_count);

END
 ;;
delimiter ;

-- ----------------------------
--  Procedure structure for `SMAWSP_ADMIN_IMIM_DELETER`
-- ----------------------------
DROP PROCEDURE IF EXISTS `SMAWSP_ADMIN_IMIM_DELETER`;
delimiter ;;
CREATE DEFINER=`itsquikadmin`@`%` PROCEDURE `SMAWSP_ADMIN_IMIM_DELETER`(IN in_modifier_item_id int(11), OUT out_message varchar(255))
BEGIN
	
	DECLARE xrow_count INT(11) default 0;
	DELETE FROM Item_Modifier_Item_Map WHERE modifier_item_id  = in_modifier_item_id;

	SELECT ROW_COUNT() INTO xrow_count;

	SET out_message = CONCAT('Rows affected is ',xrow_count);

END
 ;;
delimiter ;

-- ----------------------------
--  Procedure structure for `SMAWSP_CANCEL_ORDER_MESSAGES`
-- ----------------------------
DROP PROCEDURE IF EXISTS `SMAWSP_CANCEL_ORDER_MESSAGES`;
delimiter ;;
CREATE DEFINER=`itsquikadmin`@`%` PROCEDURE `SMAWSP_CANCEL_ORDER_MESSAGES`(IN inorder_id int(11))
BEGIN

	UPDATE Merchant_Message_History SET locked = 'C' WHERE order_id = inorder_id;

END
 ;;
delimiter ;

-- ----------------------------
--  Procedure structure for `SMAWSP_CLEAR_OLD_ERROR_LOG`
-- ----------------------------
DROP PROCEDURE IF EXISTS `SMAWSP_CLEAR_OLD_ERROR_LOG`;
delimiter ;;
CREATE DEFINER=`itsquikadmin`@`%` PROCEDURE `SMAWSP_CLEAR_OLD_ERROR_LOG`()
BEGIN
	DELETE FROM `Errors` WHERE created < DATE_SUB(now(), INTERVAL 7 DAY);
END
 ;;
delimiter ;

-- ----------------------------
--  Procedure structure for `SMAWSP_CREATE_COB_ACTIVITIES`
-- ----------------------------
DROP PROCEDURE IF EXISTS `SMAWSP_CREATE_COB_ACTIVITIES`;
delimiter ;;
CREATE DEFINER=`itsquikadmin`@`%` PROCEDURE `SMAWSP_CREATE_COB_ACTIVITIES`()
BEGIN

	INSERT INTO Activity_History (SELECT null,'CreateCOB',DATE_ADD(DATE_ADD(DATE(NOW()), INTERVAL c.close HOUR_SECOND), INTERVAL (-7-a.time_zone) HOUR ) AS doit_dt_tm,'0000-00-00 00:00:00'  AS executed_dt_tm,'N' as locked,CONCAT('merchant_id=',a.merchant_id) as info,NULL as text,0 as tries,NOW() as created,'0000-00-00 00:00:00' AS modified,'N' as logical_delete FROM Merchant a, Merchant_Message_Map b, `Hour` c WHERE a.active = 'Y' AND a.merchant_id = b.merchant_id AND a.merchant_id = c.merchant_id AND b.message_format LIKE 'G%' AND c.day_of_week = DAYOFWEEK(NOW()) AND c.day_open = 'Y' AND c.merchant_id > 1000 AND b.logical_delete = 'N' AND c.hour_type = 'R');

END
 ;;
delimiter ;

-- ----------------------------
--  Procedure structure for `SMAWSP_CREATE_COB_MESSAGES`
-- ----------------------------
DROP PROCEDURE IF EXISTS `SMAWSP_CREATE_COB_MESSAGES`;
delimiter ;;
CREATE DEFINER=`tdimachkie`@`%` PROCEDURE `SMAWSP_CREATE_COB_MESSAGES`()
BEGIN

	INSERT INTO Activity_History (SELECT null,'CreateCOB',DATE_ADD(DATE_ADD(DATE(NOW()), INTERVAL c.close HOUR_SECOND), INTERVAL (-7-a.time_zone) HOUR ) AS doit_dt_tm,'0000-00-00 00:00:00'  AS executed_dt_tm,'N' as locked,CONCAT('merchant_id=',a.merchant_id) as info,NULL as text,0 as tries,NOW() as created,'0000-00-00 00:00:00' AS modified,'N' as logical_delete FROM Merchant a, Merchant_Message_Map b, `Hour` c WHERE a.active = 'Y' AND a.merchant_id = b.merchant_id AND a.merchant_id = c.merchant_id AND b.message_format LIKE 'G%' AND c.day_of_week = DAYOFWEEK(NOW()) AND c.day_open = 'Y' AND c.merchant_id > 1000 AND b.logical_delete = 'N' AND c.hour_type = 'R');

END
 ;;
delimiter ;

-- ----------------------------
--  Procedure structure for `SMAWSP_CREATE_DAILY_REPORT_FILE`
-- ----------------------------
DROP PROCEDURE IF EXISTS `SMAWSP_CREATE_DAILY_REPORT_FILE`;
delimiter ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `SMAWSP_CREATE_DAILY_REPORT_FILE`(IN in_merchant_id int(11), IN in_days_back int(11))
BEGIN

SELECT M.merchant_id, numeric_id, name, address1, address2, city, state, zip, M.phone_no, fax_no, shop_email, tax_rate,  SUM(c.order_amt) as order_amt,  SUM(c.order_qty) as order_qty,  SUM(c.total_tax_amt) as total_tax_amt,  SUM(c.tip_amt) as tip_amt,  SUM(c.promo_amt) as promo_amt,  SUM(c.grand_total ) - SUM(trans_fee_amt) as  grand_total  INTO OUTFILE '/Users/radamnyc/code/smaw/report_files/test_it.csv' FIELDS TERMINATED BY ','  ENCLOSED BY '"'  LINES TERMINATED BY '
'  FROM  `Orders` c  JOIN Merchant M ON M.merchant_id = c.merchant_id  JOIN (  SELECT merchant_id, CONVERT(GROUP_CONCAT(tax_rate SEPARATOR '
'  ) USING  latin1) tax_rate  FROM (  SELECT merchant_id, CONCAT('Tax Group: ',tax_group,' Rate: ', tax_rate) tax_rate  FROM (  SELECT merchant_id, tax_group, SUM(rate) tax_rate FROM Tax GROUP BY merchant_id, tax_group  ) a  GROUP BY merchant_id  ) A  GROUP BY merchant_id  ) T ON T.merchant_id = c.merchant_id  WHERE c.logical_delete = 'N'  AND T.merchant_id=in_merchant_id  AND c.status='E'  AND c.order_amt !=0  AND c.merchant_id != 0  AND DATE_FORMAT(pickup_dt_tm , '%Y%m%d' ) = DATE_ADD(CURDATE(), INTERVAL- in_days_back DAY)  GROUP BY M.merchant_id;

END
 ;;
delimiter ;

-- ----------------------------
--  Procedure structure for `SMAWSP_CREATE_MORNING_MESSAGES`
-- ----------------------------
DROP PROCEDURE IF EXISTS `SMAWSP_CREATE_MORNING_MESSAGES`;
delimiter ;;
CREATE DEFINER=`itsquik`@`%` PROCEDURE `SMAWSP_CREATE_MORNING_MESSAGES`(IN daylight_savings int(1))
    DETERMINISTIC
BEGIN

/*
	using daylight_savings+a.time_zone  since time_zone is a negative number
*/

INSERT INTO Merchant_Message_History (SELECT null,a.merchant_id,NULL AS order_id,'P' as message_format,b.delivery_addr AS message_delivery_addr,DATE_SUB(DATE_SUB(DATE_ADD(DATE(NOW()), INTERVAL c.open HOUR_SECOND), INTERVAL (daylight_savings+a.time_zone) HOUR), INTERVAL 10 MINUTE) AS next_message_dt_tm,'' AS from_email,'0000-00-00 00:00:00'  AS sent_dt_tm,'N' AS locked, null AS viewed,'A' AS message_type,0 AS tries,'' AS info,NULL AS message_text, NOW() as created,'0000-00-00 00:00:00' AS modified,'N' AS logical_delete FROM Merchant a, Merchant_Message_Map b, `Hour` c WHERE a.active = 'Y' AND a.merchant_id = b.merchant_id AND a.merchant_id = c.merchant_id AND b.message_format = 'P' AND c.day_of_week = DAYOFWEEK(NOW()) AND c.day_open = 'Y' AND c.merchant_id > 1000 AND b.logical_delete = 'N' AND c.hour_type = 'R');

END
 ;;
delimiter ;

-- ----------------------------
--  Procedure structure for `SMAWSP_CREATE_ORDER`
-- ----------------------------
DROP PROCEDURE IF EXISTS `SMAWSP_CREATE_ORDER`;
delimiter ;;
CREATE DEFINER=`root`@`localhost` PROCEDURE `SMAWSP_CREATE_ORDER`(IN in_order_type varchar(10), IN in_user_id int(11), IN in_lead int(3), IN in_note VARCHAR(255), IN in_merchant_id int(11), IN in_sub_total DECIMAL(10,2), IN in_tip DECIMAL(10,2), IN in_promo_amt decimal(10,2), IN in_promo_tax_amt decimal(10,2), IN in_skin_id int(11), IN logit int(1), OUT out_return_id int(11), OUT out_message varchar(255))
BEGIN
	DECLARE xuser_found int(1);
	DECLARE xemail varchar(100);
	DECLARE xbalance DECIMAL(10,3) DEFAULT 0.0;
	DECLARE xlimit DECIMAL(10,3); -- this is the balance limit
	DECLARE xflags varchar(10);  -- flag field from the user record
	DECLARE xcustomer_donation_type CHAR(1) DEFAULT 'X';
	DECLARE xcustomer_donation_amt DECIMAL(10,3) DEFAULT 0.0;

	DECLARE xorder_id int(11);
	DECLARE xorder_detail_id int(11);
	DECLARE xitem_id int(11);
	DECLARE xitem_external_id VARCHAR(50) DEFAULT NULL;  -- used for sending to a POS integration

	DECLARE xmenu_id int(11);
	DECLARE xmenu_version DECIMAL(10,2);
	DECLARE xmenu_merchant_id int(11);
	DECLARE xmenu_item_owner int(11);  -- used to make sure the item is owned by the merchant that is identified in the order
	DECLARE xitem_quantity int(11);
	DECLARE xmenu_type_name VARCHAR(50);
	DECLARE xmenu_type_active CHAR(1);
	DECLARE xmenu_type_id int(11);
	DECLARE xsize_id int(11);
	DECLARE xsizeprice_id int(11);
	DECLARE xsize_name  VARCHAR(100);
	DECLARE xsize_print_name  VARCHAR(100);
	DECLARE xitem_name  VARCHAR(100);
	DECLARE xitem_active CHAR(1);
	DECLARE xitem_print_name  VARCHAR(100);
	DECLARE xitem_price  DECIMAL(10,3) DEFAULT 0.0;
	DECLARE xitem_tax_group INT(1) DEFAULT 1;
	DECLARE xitem_tax_rate DECIMAL(10,3) DEFAULT 0.0;
	DECLARE xitem_price_active CHAR(1);
	DECLARE xitem_sub_total  DECIMAL(10,3) DEFAULT 0.0;
	DECLARE xitem_nameofuser VARCHAR(100); -- used to attach a name to an order item
	DECLARE xitem_note VARCHAR(100); -- used to attach a note to a particular order item
	DECLARE xitem_points_used INT(11) DEFAULT 0;
	DECLARE xitem_amount_off_from_points  DECIMAL(10,2) DEFAULT 0.0;
	DECLARE xquantity int(11) DEFAULT 1; -- total numver of items in the order
	DECLARE xnote varchar(250) DEFAULT ''; -- note for entire order
	DECLARE xtrans_fee_amt DECIMAL(10,3) DEFAULT 0.25;  -- we will actually get this from teh merchant object
	DECLARE xtrans_fee_amt_override DECIMAL(10,3);  -- in this way we can set users trans fee as an override.
	DECLARE xsub_total DECIMAL(10,3) DEFAULT 0.0;

	DECLARE xtax_total_rate DECIMAL(10,3) DEFAULT 0.0;
	DECLARE xtax_total_amt DECIMAL(10,3) DEFAULT 0.0;
	DECLARE xtax_running_total_amt DECIMAL(10,3) DEFAULT 0.0;
	DECLARE xstatus CHAR(1) DEFAULT 'O';  -- order status
	DECLARE xgrand_total DECIMAL(10,3) DEFAULT 0.0;

	DECLARE xorder_type VARCHAR(10);
	DECLARE xpromo_code VARCHAR(50);
	DECLARE xpromo_id int(11);
	DECLARE xpromo_amt DECIMAL(10,3) DEFAULT 0.0;
	DECLARE xpromo_tax_amt DECIMAL(10,3) DEFAULT 0.0;

	-- skin stuff
	DECLARE xskin_donation_active CHAR(1) DEFAULT 'N';
	DECLARE xskin_in_production CHAR(1);

	DECLARE xmerchant_type CHAR(1); -- full or quick
	DECLARE xnumeric_id int(11);
	DECLARE xorder_del_type CHAR(1); -- P,T,F  etc.  we need this so that we know when to use the abbreviations.
	DECLARE xmerch_default_lead int(11);
	DECLARE xactive CHAR(1);  -- flag to determine if merchant is active
	DECLARE xmerchant_name VARCHAR(50);
	DECLARE xmerch_delete CHAR(1);  -- flag to determine if merchant is no longer in program
	DECLARE xmerch_donate_on CHAR(1) DEFAULT 'N';  --  indicates if the merchant is participating in the food bank donation program.
	DECLARE xmerch_donate_amt DECIMAL(10,3) DEFAULT 0.000;
	DECLARE xdelivery_active CHAR(1) DEFAULT 'N';
	DECLARE xdelivery_minimum DECIMAL(10,2) DEFAULT 0.00;
	DECLARE xdelivery_cost DECIMAL(10,2)	DEFAULT 0.00;
	DECLARE xdelivery_tax_amount DECIMAL(10,2) DEFAULT 0.00;
	DECLARE linebuster CHAR(1) DEFAULT 'N';

	DECLARE xtime_zone INT(11);
	DECLARE xtime_zone_offset INT(11);
	DECLARE xserver_offset INT(11);
	DECLARE xlocal_pickup_dt datetime DEFAULT NULL;
	DECLARE xlocal_order_dt datetime DEFAULT NULL;

-- CHANGE THIS?
	DECLARE xserver_pickup_dt timestamp;
	DECLARE xlocal_leadCon TIME;
	DECLARE xserver_leadCon TIME;
	
	DECLARE found int(11)  DEFAULT 0;
	DECLARE lto_found int(11)  DEFAULT 0;
	DECLARE row_count int(11) default  0;
	DECLARE xlogical_delete CHAR(1);
	DECLARE xtest varchar(5) default 'false'; -- DEPRICATED   used to indicate that this is a test message
	DECLARE xskip_hours int(1) default 0; -- used to indicate that this is a test message
	DECLARE xdbname varchar(20); -- used to ehlp when applying rules
	DECLARE xholiday_hours int(1) DEFAULT 0; -- used to indicate that these are holiday hours

	-- temp table stuff
	DECLARE xtemp_order_detail_id int(11);
	DECLARE xnum_of_temp_order_items int(11);
	DECLARE xtemp_order_item_mod_id int(11);
	DECLARE xcalced_order_total DECIMAL(10,3) DEFAULT 0.0; -- USING THIS TO TEST HTE APPS MATH ON ORDER CALC.  COULD BE USEFUL.
	DECLARE xcalced_order_sub_total DECIMAL(10,3) DEFAULT 0.0; -- USING THIS TO TEST HTE APPS MATH ON ORDER CALC.  COULD BE USEFUL.
	
	DECLARE xcash CHAR(1);

	DECLARE xmodifier_group_price_override DECIMAL (10,3);

	DECLARE xraw_stamp VARCHAR(255);
	DECLARE xserver VARCHAR(10);

	DECLARE xitem_count int(11);

	DECLARE orderItems CURSOR FOR SELECT temp_order_detail_id,sizeprice_id, quantity,name,note,points_used,amount_off_from_points FROM TempOrderItems;
	
	SELECT DATABASE() INTO xdbname;
	SELECT value INTO xserver FROM Property WHERE name = 'server';

	-- determine if this is a test
	IF LOCATE('skip hours',LOWER(in_note)) > 0 THEN
		SET xskip_hours = 1;
	END IF;

		INSERT INTO Errors values(null,CONCAT('NO STAMP',' Starting SMAWSP_CREATE_ORDER'),CONCAT('user:',in_user_id),'about to get temporary order data',CONCAT('Note:',in_note),now());
		-- get additional fields that were passed in through the TempOrders  table
		SELECT order_type,promo_code,promo_id,promo_amt,promo_tax_amt,delivery_amt,cash,stamp,trans_fee_amt,delivery_tax_amount
			INTO xorder_type,xpromo_code,xpromo_id,xpromo_amt,xpromo_tax_amt,xdelivery_cost,xcash,xraw_stamp,xtrans_fee_amt,xdelivery_tax_amount
			FROM TempOrders;

		SET logit = 1;
        
		SELECT count(*) INTO xitem_count FROM TempOrderItems;
         INSERT INTO Errors values(null,CONCAT(xraw_stamp,' Starting SMAWSP_CREATE_ORDER'),CONCAT('user:',in_user_id),CONCAT('with number of items:', xitem_count),CONCAT('Note:',in_note),now());

mainBlock:BEGIN

		-- get skin properties
		SELECT in_production, donation_active INTO xskin_in_production, xskin_donation_active FROM Skin WHERE skin_id = in_skin_id;

		-- Get user properties
		SELECT 1,balance,credit_limit,flags,trans_fee_override,email
		INTO xuser_found,xbalance,xlimit,xflags,xtrans_fee_amt_override,xemail
		FROM User
		WHERE user_id = in_user_id and logical_delete = 'N';

		IF xuser_found IS NULL THEN
				SET out_return_id = 100;
				SET out_message = 'SERIOUS_DATA_ERROR_USER_ID_DOES_NOT_EXIST';
				IF logit THEN
					INSERT INTO Errors values(null,CONCAT(xraw_stamp,' SERIOUS APP ERROR! THIS USER DOES NOT EXIST'),CONCAT('user:',in_user_id),'','',now());
				END IF;
				LEAVE mainBlock;
		END IF;

/*  Doing this in the controller now

		IF xcash = 'N' AND substring(xflags,1,2) != '1C' AND (in_sub_total > 0.00 OR in_tip > 0.00) THEN
				SET out_return_id = 150;
				SET out_message = 'NO_CREDIT_CARD_ON_FILE';
				IF logit THEN
					INSERT INTO Errors values(null,CONCAT(xraw_stamp,' USER DOES NOT HAVE A CREDIT CARD ON FILE'),CONCAT('user:',in_user_id),'','',now());
				END IF;
				LEAVE mainBlock;
		END IF;
*/
		-- SUCCESS WITH USER, AND BALANCE SO NOW GET ALL MERCHANT DATA TO CHECK ITEMS ARE ACTIVE, MERCHANT IS OPEN, AND HOW TO SEND THE ORDER

		SELECT m.name,m.time_zone,m.lead_time,m.active,m.merchant_type,m.order_del_type,m.donate,m.logical_delete,m.numeric_id
		INTO xmerchant_name, xtime_zone, xmerch_default_lead,xactive,xmerchant_type,xorder_del_type, xmerch_donate_on,xmerch_delete,xnumeric_id
		FROM Merchant m
		WHERE m.merchant_id = in_merchant_id;

		-- detemine if we need to get the merchant delivery info for xmerch_default_lead
		IF in_order_type = 'D' THEN
			SELECT minimum_delivery_time INTO xmerch_default_lead FROM Merchant_Delivery_Info WHERE merchant_id = in_merchant_id AND logical_delete = 'N';
		END IF;

		IF xmerchant_name IS NULL THEN
				-- serious data error. merchant doesn't exist
				SET out_return_id = 500;
				-- SET xmerchant_name = CONCAT('SORRY :( ', xmerchant_name,' is not accepting splick-it orders at this time.  Sorry for the inconvenience');
				SET out_message = 'SERIOUS_DATA_ERROR_MERCHANT_ID_DOES_NOT_EXIST';
				IF logit THEN
					INSERT INTO Errors values(null,CONCAT(xraw_stamp,' SERIOUS APP ERROR! THIS MERCHANT DOES NOT EXIST'),CONCAT('user:',in_user_id),CONCAT('merch:',in_merchant_id),'',now());
				END IF;
				LEAVE mainBlock;
		END IF;

		-- merchant has discontinued their association with splick-it
		IF xmerch_delete = 'Y' THEN
			SET out_return_id = 510;
			SET out_message = 'MERCHANT_DELETED';
			IF logit THEN
				INSERT INTO Errors values(null,CONCAT(xraw_stamp,' ERROR! THIS MERCHANT IS NO LONGER REGISTERED WITH SPLICKIT'),CONCAT('user:', in_user_id),CONCAT('merch:', in_merchant_id),'',now());
			END IF;
			LEAVE mainBlock;
		END IF;

		-- merchant could be innactive
		IF (xactive = 'N' AND in_user_id > 1000) THEN
			SET out_return_id = 520;
			SET out_message = 'MERCHANT_NOT_ACTIVE';
			IF logit THEN
				INSERT INTO Errors values(null,CONCAT(xraw_stamp,' ERROR! THIS MERCHANT IS NOT ACTIVE'),CONCAT('user:', in_user_id),CONCAT('merch:', in_merchant_id),'',now());
			END IF;
			LEAVE mainBlock;
		END IF;

		IF xemail = CONCAT(xnumeric_id,'_manager@dummy.com') THEN
			SET linebuster = 'Y';
			INSERT INTO Errors values(null,CONCAT(xraw_stamp,' LINE BUSTER!'),CONCAT('user:', in_user_id),CONCAT('merch:', in_merchant_id),'',now());
		END IF;

		-- get total tax from merchant (this is jsut a place holder now since items may have different tax rates)
		SELECT sum(rate) INTO xtax_total_rate FROM `Tax` WHERE merchant_id = in_merchant_id AND tax_group = 1 AND logical_delete = 'N';

		IF logit THEN
			INSERT INTO Errors values(null,CONCAT(xraw_stamp,' SUCCESS! WITH USER AND QUICK MERCHANT.'),CONCAT('user:', in_user_id),CONCAT('merch:',xmerchant_name),CONCAT('tax 1',xtax_total_rate),now());
		END IF;

		-- determine if there is a customer trans fee override
	-- 	IF xtrans_fee_amt_override IS NOT NULL THEN
	-- 		SET xtrans_fee_amt = xtrans_fee_amt_override;
	-- 	END IF;

		SELECT value INTO xserver_offset FROM Property WHERE name = 'default_server_timezone_offset';
		SET xtime_zone_offset = (60*60*(xtime_zone - xserver_offset));

		-- we now need to get the pickup time. since the server and shop could be in differenct TZ, we need to calculate what the pickup time is for both.
		-- local pickup time is for hte user and the merchant, server pickup time is so we know when to send messages out.
		
		-- rounding hack since phone time appears to be different or round differently
		IF SECOND(NOW()) > 0 THEN
			-- SET in_lead = in_lead+1;
			SET in_lead = in_lead;
		END IF;

		IF in_lead IS NOT NUll THEN
			-- user entered a lead time detemine if its greater than the merchants default, if not then set to default
			IF in_lead > xmerch_default_lead THEN
				SET xlocal_leadCon = SEC_TO_TIME(60*in_lead + xtime_zone_offset);
				SET xserver_leadCon = SEC_TO_TIME(60*in_lead);
			ELSE
				SET xlocal_leadCon = SEC_TO_TIME(60*xmerch_default_lead + xtime_zone_offset);
				SET xserver_leadCon = SEC_TO_TIME(60*xmerch_default_lead);
			END IF;
		ELSE
			-- there was no lead time sent so set it to the merchant default.
			SET xlocal_leadCon = SEC_TO_TIME(60*xmerch_default_lead + xtime_zone_offset);
			SET xserver_leadCon = SEC_TO_TIME(60*xmerch_default_lead);
		END IF;

		SET xlocal_order_dt = ADDTIME(now(),SEC_TO_TIME(xtime_zone_offset));
		SET xlocal_pickup_dt = ADDTIME(now(),xlocal_leadCon);
		SET xserver_pickup_dt = ADDTIME(now(),xserver_leadCon);

		IF logit THEN
			INSERT INTO Errors values(null,CONCAT(xraw_stamp,' Local Pickup time set'),CONCAT('user:', in_user_id),CONCAT('Date:',xlocal_pickup_dt),'',now());
			INSERT INTO Errors values(null,CONCAT(xraw_stamp,' Local Order time set'),CONCAT('user:', in_user_id),CONCAT('Date:',xlocal_order_dt),'',now());
			INSERT INTO Errors values(null,CONCAT(xraw_stamp,' Server pickup time set'),CONCAT('user:', in_user_id),CONCAT('Date:',xserver_pickup_dt),'',now());
		END IF;

	foundBlock:BEGIN
		-- calculate tax amount
		-- probably dont need to do this anymore since we reset the subtotal after the item loop.
		-- we are not figuring in teh promo amt here since dave already calulates is as part of the in_sub_total;

-- again these are just place holders for now.  we'll recalculate and update before making the order final.  in fact on older versions of the app, the subtotal thats calculated will be wrong because of incorrect use of promo amt
		SET xtax_total_amt = xtax_total_rate/100 * in_sub_total;
		SET xgrand_total = xtax_total_amt+in_sub_total+xtrans_fee_amt+xcustomer_donation_amt+in_tip;

		IF logit THEN
			INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' Values to send to create the order with'),CONCAT('user:',in_user_id),CONCAT(':MerchID ', in_merchant_id,':',xmerchant_name,':User',in_user_id,':',xlocal_pickup_dt,':',xserver_pickup_dt,':',xsub_total,':',xtrans_fee_amt,':',0.0,':GTotal ',xgrand_total,':',in_note,':',xstatus,':',xquantity),'',NOW());
		END IF;

/*		-- we are now setting the pickup_dt_tm to be alligned with eh server on GMT
		-- actually No we're not.  pickup and order time are the local times at the merchant since these are only used for display purposes.
		IF xdbname = 'smaw_test' THEN
			SET xlocal_pickup_dt = xserver_pickup_dt;
			SET xlocal_order_dt = NOW();
		END IF;
*/
		-- begin the actual creation of the order.  we do this as a transaction so we can roll it all back if there is any problem with any data insert (items, mods, users balance, messages, etc)
		-- START TRANSACTION;
-- HERE
			INSERT INTO `Orders` (`merchant_id`,`order_dt_tm`,`user_id`,`pickup_dt_tm`,`order_amt`,`total_tax_amt`,`trans_fee_amt`, `tip_amt`,`promo_amt`,`customer_donation_amt`,`grand_total`, `merchant_donation_amt`, `note`, `status`, `order_qty`,`order_type`,`skin_id`,`created`)
			VALUES (in_merchant_id, xlocal_order_dt,in_user_id,xlocal_pickup_dt,in_sub_total,xtax_total_amt,xtrans_fee_amt,in_tip,-xpromo_amt,xcustomer_donation_amt,xgrand_total, xmerch_donate_amt, in_note,'P',xquantity,in_order_type,in_skin_id,NOW());

			SELECT LAST_INSERT_ID() INTO xorder_id;
			SET out_return_id = xorder_id;
			IF logit Then
				INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' Order added to Orders'),CONCAT('user:',in_user_id),CONCAT('Order:',xorder_id),'',now());
			END IF;

			-- now loop through the temporary table
			OPEN orderItems;

			BEGIN
				DECLARE noMoreRows int(1) DEFAULT 0;
				DECLARE xmenu_type_start_time TIME; -- to determine if the menu type is only available certain hours
				DECLARE xmenu_type_end_time	TIME; -- to determine if the menu type is only available certain hours
				DECLARE xcalced_item_sub_total DECIMAL (10,3) DEFAULT 0.00; -- to hold the total price of this item (mods and anything else) before tax caculations
				DECLARE CONTINUE HANDLER FOR NOT FOUND
				BEGIN
					SET noMoreRows = 1;
				END;

				-- GET NUMBER OF ITEMS IN THE TABLE
				SELECT COUNT(sizeprice_id) INTO xnum_of_temp_order_items FROM TempOrderItems;

				SET xcalced_order_total = 0.0;
			
			-- now loop through each item in the temp table
			orderInsertLoop:LOOP
		
				-- FETCH orderItems INTO  xtemp_order_detail_id,xsizeprice_id,xitem_quantity, xitem_nameofuser,xitem_note;
				SET noMoreRows = 0;
				FETCH orderItems INTO  xtemp_order_detail_id,xsizeprice_id,xitem_quantity, xitem_nameofuser,xitem_note,xitem_points_used,xitem_amount_off_from_points;
				IF noMoreRows THEN
					INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' THERE are nore items in teh temp table'),'','','',now());
					leave orderInsertLoop;
				END IF;
				INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' got info from temporary table'),CONCAT('SPid:', xsizeprice_id),CONCAT('qty:', xitem_quantity),CONCAT(xitem_nameofuser,':',xitem_note),now());

				SELECT a.item_id,a.size_id,b.item_name,b.item_print_name,a.price,a.active,a.merchant_id,d.size_name,d.size_print_name,
					c.start_time,c.end_time,a.external_id,a.tax_group,c.menu_type_name,b.active,c.active,c.menu_type_id
				INTO xitem_id,xsize_id,xitem_name,xitem_print_name,xitem_price,xitem_price_active,xmenu_item_owner,xsize_name,xsize_print_name,xmenu_type_start_time,
					xmenu_type_end_time, xitem_external_id,xitem_tax_group,xmenu_type_name,xitem_active,xmenu_type_active,xmenu_type_id
				FROM Item_Size_Map a, Item b, Menu_Type c, Sizes d
				WHERE a.item_id = b.item_id AND a.item_size_id = xsizeprice_id AND b.menu_type_id = c.menu_type_id AND a.size_id = d.size_id;

				IF xitem_id IS NULL THEN
					-- ROLLBACK;
					CLOSE orderItems;
					INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' DATA INTEGRITY! ERROR, ITEM NO LONGER EXISTS FROM APP!'),'','','',now());
					SET out_return_id = 705;
					SET out_message = 'DATA_INTEGRITY_ERROR_APP_ITEM_NO_LONGER_EXISTS';
					leave foundBlock;
				END IF;

				-- first check to see if the menu type is available at the pickup time but dont check if this is the test system
-- CHANGE THIS  it blows up on inverted hours
			IF xskip_hours = 0 THEN
				IF TIME(xlocal_pickup_dt) < xmenu_type_start_time OR TIME(xlocal_pickup_dt) > xmenu_type_end_time THEN
					-- ROLLBACK;
					CLOSE orderItems;
					INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' ITEM OUT OF MENU TYPE HOURS!'),CONCAT('SPid:', xsizeprice_id),CONCAT('qty:', xitem_quantity),
												CONCAT(xitem_id,':',xsize_id,':',xitem_name,':',xitem_price,':',xitem_price_active,':', xlocal_pickup_dt),now());
					SET out_return_id = 780;
					IF TIME(xlocal_pickup_dt) < xmenu_type_start_time THEN
						SET out_message = CONCAT('SORRY :( The ',xitem_name,' isn''t available until ',TIME_FORMAT(xmenu_type_start_time,'%l:%i'),'. Please remove it from your cart before placing your order. Thanks for using splick-it!');
					ELSE
						SET out_message = CONCAT('SORRY :( The ',xitem_name,' isn''t available after ',TIME_FORMAT(xmenu_type_end_time,'%l:%i'),'. Please remove it from your cart before placing your order. Thanks for using splick-it!');
					END IF;
					leave foundBlock;
				END IF;
			END IF;

				IF xitem_price_active = 'N' OR xitem_active = 'N' OR xmenu_type_active = 'N' THEN
					-- SHOULD NEVER HAPPEN SINCE I SEND THE ACTIVE ITEMS TO THE APP WHEN THE CUSTOMER STARTS THE APP
					-- ROLLBACK;
					CLOSE orderItems;
					INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' DATA INTEGRITY! ERROR ITEM NOT ACTIVE FROM APP!'),CONCAT('SPid:', xsizeprice_id),CONCAT('qty:', xitem_quantity),
												CONCAT(xitem_id,':',xsize_id,':',xitem_name,':',xitem_price,':',xitem_price_active),now());
					SET out_return_id = 700;
					SET out_message = 'DATA_INTEGRITY_ERROR_APP_ITEM_NOT_ACTIVE';
					leave foundBlock;
				END IF;

				IF xmenu_type_active = 'L' THEN
					-- WE HAVE AN LTO SO LETS CHECK TO MAKE SURE ITS ON RIGHT NOW.
					SELECT 1 INTO lto_found FROM Menu_Change_Schedule WHERE object_type = 'menu_type' AND object_id = xmenu_type_id AND day_of_week = DAYOFWEEK(xlocal_pickup_dt) AND active = 'Y' AND TIME(xlocal_pickup_dt) > start AND TIME(xlocal_pickup_dt) < stop AND merchant_id = in_merchant_id;

					IF lto_found THEN
						INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' SUCCESSFULL LTO VERIFICATION'),CONCAT('menu_type:', xmenu_type_id),NULL,
										CONCAT(xitem_id,':',xsize_id,':',xitem_name,':',xitem_price,':',xitem_price_active),now());
					ELSE
	
						-- ROLLBACK;
						CLOSE orderItems;
						INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' MENU ERROR! LTO MENU_TYPE NOT ACTIVE FROM APP!'),CONCAT('menu_type:', xmenu_type_id),NULL,
										CONCAT(xitem_id,':',xsize_id,':',xitem_name,':',xitem_price,':',xitem_price_active),now());
						SET out_return_id = 715;
						SET out_message = 'DATA_INTEGRITY_ERROR_LTO_NOT_VALID';
						leave foundBlock;
					END IF;
				END IF;

				-- make sure the item is owned by the merchant referenced in the order
				IF in_order_type = 'D' THEN
					SELECT menu_id INTO xmenu_id FROM Merchant_Menu_Map WHERE merchant_id = in_merchant_id AND merchant_menu_type = 'delivery' AND logical_delete = 'N';
				ELSE
					SELECT menu_id INTO xmenu_id FROM Merchant_Menu_Map WHERE merchant_id = in_merchant_id AND merchant_menu_type = 'pickup' AND logical_delete = 'N';
				END IF;
				
				-- get version of menu
				SELECT version INTO xmenu_version FROM Menu WHERE menu_id = xmenu_id AND logical_delete = 'N';
				
				IF xmenu_version > 2.0 THEN
					SET xmenu_merchant_id = in_merchant_id;
				ELSE
					SET xmenu_merchant_id = 0;
				END IF;
			
				IF logit THEN
					INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' got item info from Item_Size_Map'),CONCAT('SizePriceId:', xsizeprice_id,'   qty:', xitem_quantity),CONCAT('item_id:', xitem_id,'  size_id:', xsize_id),CONCAT('name:',xitem_name,'  price:',xitem_price,'  price_active:',xitem_price_active),now());
			 	END IF;

				IF xmenu_item_owner != xmenu_merchant_id THEN
					-- should never happen
					-- ROLLBACK;
					CLOSE orderItems;
					INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' DATA INTEGRITY ERROR! ITEM NOT OWNED BY THIS MERCHANTANT!'),CONCAT('merchant_id: ', xmenu_merchant_id),CONCAT('owner id:', xmenu_item_owner),'',now());
					SET out_return_id = 710;
					SET out_message = 'DATA_INTEGRITY_ERROR_ITEM_NOT_OWNED_BY_SUBMITTED_MERCHANT';
					leave foundBlock;
				END IF;
				
				-- xitem_subtotal is a useless field.
				SET xitem_sub_total = xitem_quantity*xitem_price;

				SET xcalced_item_sub_total = xitem_price;

				INSERT INTO `Order_Detail` ( `order_id`, `item_size_id`,`external_id`,`menu_type_name`,`size_name`,`size_print_name`,`item_name`,`item_print_name`,`name`,`note`,`quantity`,`price`,`item_total`,`created`)
				VALUES (xorder_id, xsizeprice_id, xitem_external_id, xmenu_type_name, xsize_name, xsize_print_name, xitem_name, xitem_print_name, xitem_nameofuser,xitem_note,xitem_quantity,xitem_price,xitem_sub_total,now());

				-- get last inserted id so we can associate the modifications with a particular item
				SELECT LAST_INSERT_ID() INTO xorder_detail_id;

				IF logit THEN
					INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' Item Added to Order_Items'),CONCAT('user:',in_user_id),CONCAT(xitem_id,':',xsize_id,':',xitem_quantity,':',xitem_price,':',xitem_sub_total),CONCAT('Order:',xorder_id),now());                          				END IF;
			
				-- Here starts the modification code
				BEGIN

					DECLARE noMoreModRows int(1) DEFAULT 0;
					DECLARE xmod_sizeprice_id int(11);
					DECLARE xmod_price DECIMAL(10,3) DEFAULT 0.0;
					DECLARE xmod_qty int(11);
					DECLARE xmod_total_price DECIMAL(10,3);
					DECLARE xmodifier_item_name VARCHAR(50);
					DECLARE xmodifier_item_print_name VARCHAR(50);
					DECLARE xmodifier_item_id int(11);
					DECLARE xmodifier_item_priority int(11);
					DECLARE xmodifier_item_external_id VARCHAR(50) DEFAULT NULL; -- POS integration
					DECLARE xmodifier_group_external_id VARCHAR(50) DEFAULT NULL; -- POS integration
					DECLARE xmodifier_concat_external_id VARCHAR(100) DEFAULT NULL; -- POS integration
					DECLARE xmodifier_group_name VARCHAR(50);
					DECLARE xmodifier_group_id int(11);
					DECLARE xmodifier_type CHAR(2) DEFAULT 'T';
					DECLARE found_comes_with int(1);
					DECLARE xcomes_with CHAR(1) DEFAULT 'N';
					DECLARE xhold_it CHAR(1) DEFAULT 'N';
					DECLARE xhold_it_modifier_group_id int(11);
					DECLARE xhold_it_modifier_group_name VARCHAR(50);

					DECLARE order_item_mods CURSOR FOR
								SELECT a.mod_sizeprice_id,b.modifier_price,a.mod_quantity,(b.modifier_price*a.mod_quantity) as mod_total_price,c.modifier_item_name,c.modifier_item_print_name,
										c.modifier_item_id,d.modifier_group_name,d.modifier_type,d.modifier_group_id,b.external_id,c.priority,d.external_modifier_group_id
								FROM TempOrderItemMods a, Modifier_Size_Map b, Modifier_Item c, Modifier_Group d
								WHERE a.mod_sizeprice_id = b.modifier_size_id AND b.modifier_item_id = c.modifier_item_id AND c.modifier_group_id = d.modifier_group_id AND a.temp_order_detail_id = xtemp_order_detail_id;

					DECLARE CONTINUE HANDLER FOR NOT FOUND
					BEGIN
						INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' WE GOT A NOT FOUND IN TEH MODIFIER CURSOR'),'','','',now());
						SET noMoreModRows = 1;
					END;
				
					OPEN order_item_mods;
						
					modInsertLoop:LOOP
						FETCH order_item_mods INTO xmod_sizeprice_id,xmod_price,xmod_qty,xmod_total_price, xmodifier_item_name, xmodifier_item_print_name, xmodifier_item_id, xmodifier_group_name, xmodifier_type,xmodifier_group_id, xmodifier_item_external_id, xmodifier_item_priority, xmodifier_group_external_id;
						IF noMoreModRows THEN
							leave modInsertLoop;
						END IF;
						INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' got MODIFICATION price from temporary table'),CONCAT('mod_sizeprice_id:', xmod_sizeprice_id,'    mod_id:', xmodifier_item_id),CONCAT('mod_group_id: ', xmodifier_group_id),CONCAT('qty:', xmod_qty,'   total_mod_price:', xmod_total_price),now());
	/*
						IF xmodifier_group_external_id IS NULL THEN
							SET xmodifier_concat_external_id = xmodifier_item_external_id;
						ELSE
							IF xmodifier_item_external_id IS NULL THEN
								SET xmodifier_concat_external_id = xmodifier_group_external_id;
							ELSE
								SET xmodifier_concat_external_id = CONCAT(xmodifier_group_external_id,':',xmodifier_item_external_id);
							END IF;
						END IF;
*/
						INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' the external values'),CONCAT('group_external:', xmodifier_group_external_id),CONCAT('item_external:', xmodifier_item_external_id),CONCAT('concat:', xmodifier_concat_external_id),now());

						IF xmodifier_type = 'Q' AND xmod_qty > 1 THEN
							SET xitem_quantity = xmod_qty;
						END IF;

						-- now determine if this is a comes with item, if so then deduct a single price unit from the total mod price (quantity*unit price).  the message builder will detmine if its shown on the ticket.  had to still include it in the order otherwiese the message builder will think its being held, as in HOLD the mayo.
						-- somthing else:  so if the sandwich is ham/swiss and the person swaps out cheddar for swiss, they will get charged for cheddar.  we made a concious decision NOT to deal with swaps.  tough luck kind of thing.

						-- but..... if the comes with modifier doesn't exist, we could test to see if an added modifier is in the same group as the comes with modifier, if so compare the price
						--       and if they're the same then subtract.......  hmmmmm..........  maybe this is for 2.5

						-- first selection zero price override?????
							
						-- we can skip this code if the price is zero already though
						-- IF xmod_price > 0.00 THEN
							INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' CHECK IF MOD IS ON THE COMES WITH LIST'),CONCAT('mod_sizeprice_id:', xmod_sizeprice_id,'    mod_id:', xmodifier_item_id),CONCAT('mod_group_id: ', xmodifier_group_id),CONCAT('qty:', xmod_qty,'   total_mod_price:', xmod_total_price),now());
							set found_comes_with = 0;
							set xcomes_with = 'N';
							-- is this on the list?
							SELECT 'Y' INTO xcomes_with FROM Item_Modifier_Item_Map WHERE item_id = xitem_id AND modifier_item_id = xmodifier_item_id AND logical_delete = 'N';
							INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' comes with is: ', xcomes_with),'','','',now());
							IF xcomes_with = 'Y' THEN
								INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' Its on the comes with list'),NULL,NULL,NULL,now());

								-- with the addition of the price_override functionality we only need to do this calculation if price_override is 0.00.  if its more than zero, we'll let ht price get settled
								-- in the adjustment section below.
								SELECT price_override INTO xmodifier_group_price_override FROM Item_Modifier_Group_Map WHERE modifier_group_id = xmodifier_group_id AND item_id = xitem_id AND logical_delete = 'N' AND merchant_id = xmenu_merchant_id;
								
								IF xmodifier_group_price_override = 0.00 THEN
									-- no price override so let the comes with price logic work
									SET xmod_total_price = xmod_total_price - xmod_price;
								END IF;
							END IF;
								
							-- and now for the HACK.
							-- the not found handler is screwing things up here when there was no rows found for comes with
							-- so we need to set the noMoreModRows to '0' to keep the loop going
							SET noMoreModRows = 0;
						-- END IF;
							
						INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' about to do the insert for modifier'),CONCAT('name:', xmodifier_item_name),CONCAT('s_name:', xmodifier_item_print_name),'',now());

						-- now insert it into the order_detail_mod table
						INSERT INTO `Order_Detail_Modifier` (`order_detail_id`,`modifier_size_id`,`external_id`,`modifier_item_id`,`modifier_item_priority`,`modifier_group_id`,`modifier_group_name`,`mod_name`,
										`mod_print_name`,`modifier_type`,`comes_with`,`hold_it`,`mod_quantity`,`mod_price`,`mod_total_price`,`created`)
						VALUES (	xorder_detail_id,xmod_sizeprice_id, xmodifier_item_external_id,xmodifier_item_id,xmodifier_item_priority,xmodifier_group_id,
 								xmodifier_group_name ,xmodifier_item_name,xmodifier_item_print_name,xmodifier_type,xcomes_with,xhold_it,xmod_qty,xmod_price,xmod_total_price,NOW());
		
						INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' modifier INSERTED!'),'','','',now());

						SET xcalced_item_sub_total = xcalced_item_sub_total + xmod_total_price;

					END LOOP; -- mod insert loop

					CLOSE order_item_mods;

					-- NOW FIGURE OUT WHAT THE 'HOLD THE' ITEMS ARE AND ADD THEM TO THE ORDER DETAIL
					holdtheBlock:BEGIN

						DECLARE comes_with_items CURSOR FOR SELECT a.modifier_item_id, b.modifier_item_name, b.modifier_item_print_name,b.modifier_group_id,b.priority
												FROM Item_Modifier_Item_Map a, Modifier_Item b
												WHERE a.item_id = xitem_id AND a.modifier_item_id = b.modifier_item_id AND a.logical_delete = 'N';
						SET noMoreModRows = 0;
						INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' starting the hold it loop'),'','','',now());
						OPEN comes_with_items;
						holdtheInsertLoop:LOOP
							FETCH comes_with_items INTO xmodifier_item_id,xmodifier_item_name,xmodifier_item_print_name,xhold_it_modifier_group_id,xmodifier_item_priority;
							IF noMoreModRows THEN
								leave holdtheInsertLoop;
							END IF;
							SET xhold_it = 'Y';
							INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' looping'),xmodifier_item_id,xmodifier_item_name,CONCAT('order_datail: ', xorder_detail_id),now());
							SELECT 'N' INTO xhold_it FROM `Order_Detail_Modifier` WHERE order_detail_id = xorder_detail_id AND modifier_item_id = xmodifier_item_id;
							INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' after select N'),'','',CONCAT('order_datail: ', xorder_detail_id),now());
							IF xhold_it = 'Y' THEN
								INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' *****WE HAVE A HOLD IT!******'),xmodifier_item_id,xmodifier_item_name,'',now());

								-- short name sub for null short name.   legacy stuff.  shouldn't be nulls going forward.
								IF xmodifier_item_print_name IS NULL THEN
									-- SET xmodifier_item_name = xmodifier_item_print_name;
									SET xmodifier_item_print_name = xmodifier_item_name;
								END IF;
	
								-- for POS we need the maping id of the held item so....
								SET xmod_sizeprice_id = null;
								SELECT modifier_size_id,external_id INTO xmod_sizeprice_id, xmodifier_item_external_id FROM Modifier_Size_Map WHERE modifier_item_id = xmodifier_item_id AND size_id = xsize_id AND logical_delete = 'N' AND merchant_id = xmenu_merchant_id;
								IF xmod_sizeprice_id IS NULL THEN
								    SELECT modifier_size_id, external_id INTO xmod_sizeprice_id, xmodifier_item_external_id FROM Modifier_Size_Map WHERE modifier_item_id = xmodifier_item_id AND size_id = 0 AND logical_delete = 'N' AND merchant_id = xmenu_merchant_id;
								END IF;
								IF xmod_sizeprice_id IS NULL THEN
									-- ok looks like we have an orphaned Item_Modifier_Item record, so we'll just skip it.
								    INSERT INTO Errors VALUES (null,xraw_stamp,'EMAIL ERROR',
											CONCAT('NULL value for mod_size_map_id. Skipping hold it insert. modifier_item_id: ', xmodifier_item_name,'  ',xmodifier_item_id),
											CONCAT('item_id:',xitem_id,'    merchant_id:', xmenu_merchant_id),now());
								ELSE
									INSERT INTO `Order_Detail_Modifier` (`order_detail_id`,`modifier_size_id`,`external_id`,`modifier_item_id`,`modifier_item_priority`,`modifier_group_id`,`mod_name`,`mod_print_name`,`modifier_type`,
												`comes_with`,`hold_it`,`mod_quantity`,`mod_price`,`mod_total_price`,`created`)
									VALUES (	xorder_detail_id, xmod_sizeprice_id, xmodifier_item_external_id,xmodifier_item_id,xmodifier_item_priority,xhold_it_modifier_group_id,xmodifier_item_name,xmodifier_item_print_name,'H','H',xhold_it,0,0.00,0.00,now());
								END IF;
							ELSE
								INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' do not hold it!'),xmodifier_item_id,xmodifier_item_name,'',now());
							END IF;
							SET noMoreModRows = 0;
						END LOOP;

					END; -- HOLD THE BLOCK

					-- now do any price recalcs for zero price override or mod group max price.
					-- get the mod groups that have some rules
					-- determine total price on each of those groups in the order
					-- make subtotal changes if necessary
						-- add a row in the modifier details table for this price change?  maybe. yes
						-- need a dummy modifier that holds this place in the order_details table?   should NOT display on receipt to user
					
					-- maybe can do a check here for a free/promo item
	priceadjustblock:BEGIN
							
						DECLARE xmodifier_group_price_max DECIMAL (10,3);
						DECLARE xgroup_total_price_for_item DECIMAL (10,3);
						DECLARE xadjustment_amount DECIMAL (10,3);
					
						DECLARE order_item_mod_groups CURSOR FOR SELECT modifier_group_id,price_override,price_max FROM Item_Modifier_Group_Map
									WHERE item_id = xitem_id AND logical_delete = 'N' AND merchant_id = xmenu_merchant_id AND
									modifier_group_id IN (SELECT DISTINCT modifier_group_id FROM Order_Detail_Modifier WHERE order_detail_id = xorder_detail_id AND ( modifier_type LIKE 'I%' OR modifier_type = 'T' OR modifier_type = 'S' ));

						DECLARE CONTINUE HANDLER FOR NOT FOUND
							SET noMoreModRows = 1;
						
						OPEN order_item_mod_groups;
						LOOP
							SET noMoreModRows = 0;
							FETCH order_item_mod_groups INTO xmodifier_group_id, xmodifier_group_price_override, xmodifier_group_price_max;
							IF noMoreModRows THEN
								LEAVE priceadjustblock;
							END IF;
							INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' we have fetched Price Overrides'), xmodifier_group_id, xmodifier_group_price_override, xmodifier_group_price_max,now());

							-- so now see what the total amount spent on the group is.  problem is that a comes with item wont be charged so it wont be figured in.  HOW TO SOLVE FOR THIS?????
							--  maybe in price calculations above we need to see if the modifier_item is part of item_group_map that has an override, if so, we do charge for the item above and let it work
							-- out in the logic here.
							SELECT SUM(mod_total_price) INTO xgroup_total_price_for_item FROM Order_Detail_Modifier WHERE modifier_group_id = xmodifier_group_id AND order_detail_id = xorder_detail_id;

							INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' we have summed price override'), xgroup_total_price_for_item, '', '',now());
							
							IF xmodifier_group_price_override > 0.00 THEN
								SET xadjustment_amount = xmodifier_group_price_override;
								IF xgroup_total_price_for_item < xmodifier_group_price_override THEN
									SET xadjustment_amount = xgroup_total_price_for_item;
								END IF;
								
								-- add row to order_detail_modifier table
								IF xadjustment_amount > 0.00 THEN
									INSERT INTO Order_Detail_Modifier (order_detail_id,modifier_item_id,modifier_group_id,mod_name,mod_print_name,modifier_type,hold_it,mod_quantity,mod_price,mod_total_price,created)
										VAlUES (xorder_detail_id,0, xmodifier_group_id,'price adjustment','override','A','N',1,-xadjustment_amount,-xadjustment_amount,NOW());
									-- adjust xitem_sub_total
									SET xcalced_item_sub_total = xcalced_item_sub_total - xadjustment_amount;

									-- have to adjust xgroup_total_price_for_item here so it works for the next section.  very low probability of this happening though
									SET xgroup_total_price_for_item = xgroup_total_price_for_item - xadjustment_amount;
								END IF;

							END IF;
							IF xmodifier_group_price_max IS NOT NULL AND xgroup_total_price_for_item > xmodifier_group_price_max THEN
								-- add row in modifier details table for the adjustment
								SET xadjustment_amount = xgroup_total_price_for_item - xmodifier_group_price_max;
								INSERT INTO Order_Detail_Modifier (order_detail_id,modifier_item_id,modifier_group_id,mod_name,mod_print_name,modifier_type,hold_it,mod_quantity,mod_price,mod_total_price,created)
									VAlUES (xorder_detail_id,0, xmodifier_group_id,'price adjustment','group max','A','N',1,-xadjustment_amount,-xadjustment_amount,NOW());
								-- adjust xitem_sub_total
								SET xcalced_item_sub_total = xcalced_item_sub_total - xadjustment_amount;
							END IF;
						END LOOP; -- loop of mod groups in order
					END;  -- priceadjustblock

	paywithpointsblock:BEGIN
						IF xitem_points_used > 0 THEN
							INSERT INTO Order_Detail_Modifier (order_detail_id,modifier_item_id,modifier_group_id,mod_name,mod_print_name,modifier_type,hold_it,mod_quantity,mod_price,mod_total_price,created)
									VAlUES (xorder_detail_id,0, 0,'price adjustment','points','P','N',xitem_points_used,-xitem_amount_off_from_points,-xitem_amount_off_from_points,NOW());
							-- adjust xitem_sub_total
							SET xcalced_item_sub_total = xcalced_item_sub_total - xitem_amount_off_from_points;
						END IF;
					END;  -- paywithpointsblock

					-- xcalced_item_sub_total should be loaded up with the totals from the modifiers for this item by the time the code gets to here.
					
					--  here is where i do the tax for the item
					-- get tax rate for this item from the tax table

					IF xitem_tax_group = 0 THEN
 						SET xitem_tax_rate = 0.00;
					ELSE
						SELECT sum(rate) INTO xitem_tax_rate FROM `Tax` WHERE merchant_id = in_merchant_id AND tax_group = xitem_tax_group AND logical_delete = 'N';
						IF (xitem_tax_rate IS NULL AND xitem_tax_group > 1) THEN
							SELECT sum(rate) INTO xitem_tax_rate FROM `Tax` WHERE merchant_id = in_merchant_id AND tax_group = 1 AND logical_delete = 'N';
							INSERT INTO Errors VALUES (null,xraw_stamp,'EMAIL ERROR',
												CONCAT( 'NO TAX GROUP FOR THIS ITEM Defaulting to 1 group ','item_id: ', xitem_id,'  tax group: ',xitem_tax_group),
												CONCAT('size_price_id:', xsizeprice_id),now());
						END IF;
					END IF;
	
					-- determine if there is a quantity modifier of more than 1 and adjust
					IF xitem_quantity > 1 THEN
						SET xcalced_item_sub_total = xitem_quantity * xcalced_item_sub_total;
						SET xitem_sub_total = xitem_quantity*xitem_price;
					END IF;

					-- now get the tax amount for this item and add it to the running total tax amit
					SET xtax_running_total_amt = xtax_running_total_amt + ((xitem_tax_rate/100) * xcalced_item_sub_total);

					UPDATE Order_Detail SET item_total_w_mods = xcalced_item_sub_total,item_tax = ((xitem_tax_rate/100) * xcalced_item_sub_total),item_total = xitem_sub_total,quantity = xitem_quantity WHERE order_detail_id = xorder_detail_id;
	
					IF logit Then
						INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' TAX for item calculated'),CONCAT('rate:', xitem_tax_rate),CONCAT('subtotal: ', xcalced_item_sub_total),CONCAT('item tax total:', (xitem_tax_rate/100) * xcalced_item_sub_total),now());
						INSERT INTO Errors VALUES (NULL,CONCAT(xraw_stamp,' running order subtotal'),CONCAT('sub total: ', xcalced_order_sub_total),'','',now());
					END IF;

					-- now update the running total
					SET xcalced_order_sub_total = xcalced_order_sub_total + xcalced_item_sub_total;

				END; -- MODS SECTION

			END LOOP;

			CLOSE orderItems;
		END;

		-- adjust taxes if necessary because of promo
		IF xpromo_tax_amt > 0.00 THEN
			INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' ABOUT TO ADJUST TAX TOTAL FOR PROMO'),CONCAT('amt:  ', xpromo_tax_amt),CONCAT('order_id:  ', xorder_id),'',NOW());
		END IF;
		SET xtax_running_total_amt = xtax_running_total_amt - xpromo_tax_amt + xdelivery_tax_amount;

		-- write to the error log if the submitted sub total and calculated sub total do not match
		IF in_sub_total != xcalced_order_sub_total+xdelivery_cost THEN
			INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' ERROR! ORDER SUBTOTAL MIS-MATCH'),CONCAT('in:', in_sub_total), CONCAT('calced:', (xcalced_order_sub_total-xpromo_amt)),'',now());
		END IF;

		INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' ORDER TOTALS   tax_total:', xtax_running_total_amt,'  calced_sub_total', xcalced_order_sub_total),CONCAT('transfee:', xtrans_fee_amt,'  tip: ', in_tip),CONCAT('promo: ', xpromo_amt),CONCAT('delivery:', xdelivery_cost),now());
		SET xcalced_order_total = xtax_running_total_amt +xcalced_order_sub_total+xtrans_fee_amt+in_tip-xpromo_amt+xdelivery_cost;

		-- get donation information
		IF xcash != 'Y' AND xskin_donation_active = 'Y' THEN
			SELECT donation_type, donation_amt INTO xcustomer_donation_type, xcustomer_donation_amt FROM User_Skin_Donation WHERE user_id = in_user_id AND skin_id = in_skin_id AND donation_active = 'Y';
			INSERT INTO Errors values (null,CONCAT(xraw_stamp,' 1: CUSTOMER DONATION: ', xcustomer_donation_type),CONCAT('DONATION:', xcustomer_donation_amt),CONCAT(in_user_id,' ',in_skin_id),CONCAT('ORDER_ID: ', xorder_id),now());
			IF xcustomer_donation_type = 'R' THEN
				SET xcustomer_donation_amt = ((SELECT FLOOR(xcalced_order_total))+1)-xcalced_order_total;
				IF xcalced_order_total = 1.00 THEN
					SET xcustomer_donation_amt = 0.25;
				END IF;
				INSERT INTO Errors values (null,CONCAT(xraw_stamp,' 2: CUSTOMER DONATION: ', xcustomer_donation_type),CONCAT('DONATION:', xcustomer_donation_amt),'',CONCAT('ORDER_ID: ', xorder_id),now());			END IF;
		ELSE
				INSERT INTO Errors values (null,CONCAT(xraw_stamp,' SKIPPING DONATION BECAUSE OF CASH '),'','',CONCAT('ORDER_ID: ', xorder_id),now());		END IF;

		SET xcalced_order_total = xcalced_order_total + xcustomer_donation_amt;

		IF logit THEN
			INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' CALCED ORDER TOTAL'),CONCAT('total:', xcalced_order_total),'','',now());
		END IF;

		UPDATE `Orders` SET `order_amt` = xcalced_order_sub_total,`total_tax_amt`= xtax_running_total_amt,`grand_total` = xcalced_order_total, `order_qty` = xnum_of_temp_order_items, `tip_amt` = in_tip, `promo_amt` = -xpromo_amt, `customer_donation_amt` = xcustomer_donation_amt, `delivery_amt` = xdelivery_cost, `cash` = xcash, `stamp` = xraw_stamp WHERE `order_id` = xorder_id;

		-- check for duplicate order.
		IF xdbname = 'smaw_prod' AND in_user_id > 1000 AND linebuster = 'N' AND xserver != 'laptop' THEN
			IF logit THEN
				INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' checking for duplicate.  Merch_id: ', in_merchant_id,'    user_id:',in_user_id),CONCAT('grand total:', xcalced_order_total),CONCAT('qty:', xnum_of_temp_order_items),CONCAT('calced_order_total:', xcalced_order_total),now());                       			END IF;
			SET found = 0;
			SELECT COUNT(*) INTO found FROM `Orders` WHERE merchant_id = in_merchant_id AND user_id = in_user_id AND order_amt = xcalced_order_sub_total AND tip_amt = in_tip AND `order_qty` = xnum_of_temp_order_items AND status IN ('E','P','O') AND created > DATE_SUB(NOW(), INTERVAL 15 MINUTE);
			IF found > 1 AND xcalced_order_sub_total > 0.00 THEN
				-- UPDATE `Orders` SET `status` = 'D' WHERE `order_id` = xorder_id;
				SET out_return_id = 70;
				SET out_message = 'DUPLICATE_ORDER_ERROR';
				IF logit THEN
					INSERT INTO Errors values(null,CONCAT(xraw_stamp,'  ',out_message),CONCAT('user:',in_user_id),CONCAT('merch:',xmerchant_name),CONCAT('order:', xorder_id),now());
				END IF;
				LEAVE foundBlock;
			END IF;
		END IF;

		-- if we got here then we know all the inserts suceeded and we can commit all the changes
		-- COMMIT;
		
		IF logit THEN
			INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' Returned id from creating the order is'),CONCAT('user:',in_user_id),concat('Returned order id:',out_return_id),'',now());
		END IF;

		-- now need to update the users balance.  need to create the messages too which is called from the update balance SP.
		-- had to do it this way since some messages are dependent on the users balance.  probably could have figured out a bettter way.  stupid.

		-- OK FOR LENNY'S right here i would call the create messages completely skipping the update balance SP

		IF xorder_type = 'Z' THEN
			UPDATE `Orders` SET `status` = 'Z' WHERE `order_id` = out_return_id LIMIT 1;
			SET out_message = "SuccessfulGetCheckOut";
		ELSE
			IF xorder_del_type = 'K' OR xcash = 'Y' OR linebuster = 'Y' THEN
				IF xcash = 'Y' THEN
					INSERT INTO Errors values (null,CONCAT(xraw_stamp,' SKIPPING UPDATE BALANCE BECAUSE OF CASH OR JM OR LineBuster '),'','',CONCAT('ORDER_ID: ', xorder_id),now());
				END IF;
				IF linebuster = 'Y' THEN
					INSERT INTO Errors values (null,CONCAT(xraw_stamp,' SETTING DEFAULT LEAD TO -1 TO INDICATE LINE BUSTER FOR MESSAGES '),'','',CONCAT('ORDER_ID: ', xorder_id),now());
					SET 	xmerch_default_lead = -1;
				END IF;

				CALL SMAWSP_CREATE_ORDER_MESSAGES(in_merchant_id,xorder_id,xserver_pickup_dt,xmerch_default_lead,in_user_id,0.00,@return);
				-- add pickup time to merchant same.  we'll serparate out on the sms_order page
				SET out_message = CONCAT(xmerchant_name,'#',xlocal_pickup_dt);
				UPDATE `Orders` SET `status` = 'O' WHERE `order_id` = xorder_id LIMIT 1;
			ELSE
				CALL SMAWSP_UPDATE_BALANCE(in_user_id,xbalance,xorder_id,in_merchant_id,xserver_pickup_dt, xcalced_order_total,xmerch_default_lead,xtest,logit,@result,@returnCode,@errorMessage);
				IF @result = 'FAILURE' THEN
					SET out_return_id = @returnCode;
					SET out_message = @errorMessage;
					IF logit THEN
						INSERT INTO Errors values(null,CONCAT(xraw_stamp,'  ',out_message),CONCAT('user:',in_user_id),CONCAT('merch:',xmerchant_name),'',now());
					END IF;
					LEAVE foundBlock;
				END IF;
	
				-- add pickup time to merchant same.  we'll serparate out on the sms_order page
				SET out_message = CONCAT(xmerchant_name,'#',xlocal_pickup_dt);
				-- set the overlimit flag if the user is over the limit
				IF xbalance-xcalced_order_total < xlimit THEN
					SET out_message = CONCAT(out_message,'#runcard');
				ELSE
					-- ok so the order did not take the user over the limit so update the order status to Open from Pending
					UPDATE `Orders` SET `status` = 'O' WHERE `order_id` = xorder_id LIMIT 1;
				END IF;
			END IF; -- detemine if LENNY"S type

/*			IF xcash = 'Y' THEN
				INSERT INTO Errors values (null,CONCAT(xraw_stamp,' SKIPPING UPDATE BALANCE BECAUSE OF CASH '),'','',CONCAT('ORDER_ID: ', xorder_id),now());
			END IF;
			IF linebuster = 'Y' THEN
				INSERT INTO Errors values (null,CONCAT(xraw_stamp,' SETTING DEFAULT LEAD TO -1 TO INDICATE LINE BUSTER FOR MESSAGES '),'','',CONCAT('ORDER_ID: ', xorder_id),now());
				SET 	xmerch_default_lead = -1;
			END IF;

			CALL SMAWSP_CREATE_ORDER_MESSAGES(in_merchant_id,xorder_id,xserver_pickup_dt,xmerch_default_lead,in_user_id,0.00,@return);
			-- add pickup time to merchant same.  we'll serparate out on the sms_order page
			SET out_message = CONCAT(xmerchant_name,'#',xlocal_pickup_dt);
			IF xorder_del_type = 'K' OR xcash = 'Y' OR linebuster = 'Y' THEN
				UPDATE `Orders` SET `status` = 'O' WHERE `order_id` = xorder_id LIMIT 1;
			ELSE
				IF xbalance-xcalced_order_total < xlimit THEN					SET out_message = CONCAT(out_message,'#runcard');
				ELSE
					UPDATE `User` SET balance = (xbalance-xcalced_order_total) WHERE user_id = in_user_id;
					INSERT INTO `Balance_Change` (`user_id`,`balance_before`,`charge_amt`,`balance_after`,`process`,`order_id`,`notes`,`created`) VALUES (in_user_id, xbalance,-xcalced_order_total,(xbalance-xcalced_order_total),'Order', xorder_id,'SP balance change',now());
					UPDATE `Orders` SET `status` = 'O' WHERE `order_id` = xorder_id LIMIT 1;
				END IF;
			END IF;
*/
		END IF;

		IF logit THEN
			INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' completion of found block in SMAW_CREATE_ORDER'),xorder_id,out_message,'',now());
		END IF;
	END;  -- end found block
  END; -- end main block
END
 ;;
delimiter ;

-- ----------------------------
--  Procedure structure for `SMAWSP_CREATE_ORDER_MESSAGES`
-- ----------------------------
DROP PROCEDURE IF EXISTS `SMAWSP_CREATE_ORDER_MESSAGES`;
delimiter ;;
CREATE DEFINER=`itsquik`@`%` PROCEDURE `SMAWSP_CREATE_ORDER_MESSAGES`(IN in_merchant_id int(11), IN in_order_id int(11), IN in_server_pickup_dt DATETIME, IN in_merch_lead int(11), IN in_user_id int(11), IN in_new_balance DECIMAL(10,2), OUT out_return_val VARCHAR(10))
BEGIN
        	DECLARE xmap_id int(11);
        	DECLARE xmesg_format char(3);
		DECLARE xmesg_type char(2);
		DECLARE xmesg_delivery_addr varchar(50);
        	DECLARE xdelay int(11);
		DECLARE ximmediate_message_delivery CHAR(1) DEFAULT 'N';
        	DECLARE xmesg_next_dt DATETIME DEFAULT NOW();
		DECLARE xmesg_info VARCHAR(255) DEFAULT '5';
		DECLARE xmesg_text TEXT DEFAULT NULL;
		DECLARE xlocked CHAR(1) DEFAULT 'N';
        	DECLARE done int(11) DEFAULT 0;
        	DECLARE xbalance DECIMAL(10,2);
        	DECLARE xlimit DECIMAL(10,2);
		DECLARE xmerch_del_type CHAR(1);
		DECLARE xdbname varchar(20); -- used to help when applying rules
		DECLARE xuser_rewardr_participation CHAR(1);
		DECLARE xglobal_rewardr_active CHAR(1);
		DECLARE xskin_id int(11);
		DECLARE xskin_rewardr_active CHAR(1);
		DECLARE xraw_stamp VARCHAR(255);
		DECLARE xviewed VARCHAR(4) DEFAULT NULL;
		DECLARE xtemp_merchant_id int(11);

		DECLARE xtext_message_addr varchar(50) DEFAULT '88888';

		DECLARE xnum_e_level_items int(11); -- used to adjust delivery time based number of items that need to be prepared.

        DECLARE msgs CURSOR FOR SELECT b.map_id, b.message_format, b.delivery_addr, b.delay, b.message_type, b.`info`, b.message_text FROM Merchant_Message_Map b WHERE b.merchant_id=in_merchant_id AND b.logical_delete = 'N' ORDER BY b.map_id;

		DECLARE CONTINUE HANDLER FOR NOT FOUND
		BEGIN
			SET done = 1;
			INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' No messages found in SMAWSP_CREATE_ORDER_MESSAGES()'),'','','',NOW());
		END;
/*		DECLARE EXIT HANDLER FOR SQLEXCEPTION
		BEGIN
			SET out_return_val = 'FAILURE';
			INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,'there was an SQL Exception in SMAWSP_CREATE_ORDER_MESSAGES()','','','',NOW());
		END;
*/

		SELECT stamp INTO xraw_stamp FROM TempOrders;
		
		INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' Starting h SMAWSP_CREATE_ORDER_MESSAGES()'),CONCAT('user:',in_user_id),CONCAT('Order:',in_order_id),CONCAT('Merchant: ',in_merchant_id),now());
		SET out_return_val = 'NOMESSAGES';
		
		SELECT DATABASE() INTO xdbname;
		
 		OPEN msgs;
		msg_insert:LOOP

			FETCH msgs INTO xmap_id,xmesg_format, xmesg_delivery_addr, xdelay, xmesg_type,xmesg_info,xmesg_text;

			IF done THEN
				LEAVE msg_insert;
			END IF;

			IF xmesg_format = 'T' THEN
				SET xtext_message_addr = xmesg_delivery_addr;
			END IF;

			IF xmesg_format LIKE 'G%' THEN
				IF xtext_message_addr = '88888' THEN
					SELECT delivery_addr INTO xtext_message_addr FROM Merchant_Message_Map WHERE merchant_id=in_merchant_id AND message_format = 'T' AND logical_delete = 'N';
				END IF;
				SET xmesg_delivery_addr = xtext_message_addr;
			END IF;

			INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' successful fetch'),'','','',NOW());
			-- this used to be dependent on the X message getting created.  not really useing it any more
			SET out_return_val = 'SUCCESS';

			-- create the message send time based on the delay value.  funny thing with negatives here.  sub - xdelay and all.
			SELECT (  DATE_SUB(in_server_pickup_dt, INTERVAL (in_merch_lead-xdelay) MINUTE) ) INTO xmesg_next_dt;

			-- now check the special cases the need immediate send of order like lenny's
			IF xmesg_format = 'L' THEN
				INSERT INTO Errors VALUES(null,CONCAT(xraw_stamp,' SUBSTITUTING now() for next message time because its a lennys order'),CONCAT('user:',in_user_id),CONCAT('Order: ',in_order_id),'',now());
				SET xmesg_next_dt = NOW();
			END IF;

			--  could conceivably find out the number of 'E' level items and adjust the message send time accordingly.
			SELECT COUNT(a.order_detail_id) INTO xnum_e_level_items FROM Order_Detail a, Item_Size_Map b, Item c, Menu_Type d WHERE a.order_id = in_order_id AND a.item_size_id = b.item_size_id AND b.item_id = c.item_id AND c.menu_type_id = d.menu_type_id AND d.cat_id = 'E';

			IF xnum_e_level_items > 4 THEN
				INSERT INTO Errors VALUES(null,CONCAT (xraw_stamp,' HIGH NUMBER OF E LEVEL ITEMS ', xnum_e_level_items,'. MAKING CHANGE TO DELIVERY TIME BEFORE'),CONCAT('time:', xmesg_next_dt),CONCAT('Order: ',in_order_id),'',now());
				SELECT DATE_SUB(xmesg_next_dt, INTERVAL (((xnum_e_level_items-4) * 2)+3) MINUTE) INTO xmesg_next_dt;
				INSERT INTO Errors VALUES(null,CONCAT (xraw_stamp,' HIGH NUMBER OF E LEVEL ITEMS MAKING CHANGE TO DELIVERY TIME AFTER'),CONCAT('time:', xmesg_next_dt),CONCAT('Order: ',in_order_id),'',now());
				IF xmesg_next_dt < NOW() THEN
					SELECT (  DATE_ADD(NOW(), INTERVAL (xdelay) MINUTE) ) INTO xmesg_next_dt;
					INSERT INTO Errors VALUES(null,CONCAT (xraw_stamp,' REJECT DELIVERY TIME CHANGE.  ITS IN THE PAST, SO SET TO NOW'),CONCAT('time:', xmesg_next_dt),CONCAT('Order: ',in_order_id),'',now());
				END IF;
			END IF;

			INSERT INTO Errors VALUES(null,CONCAT(xraw_stamp,' Adding Message to Comm_Schedule table. id:',xmap_id,' type:',xmesg_format),CONCAT('user:',in_user_id),CONCAT('Order: ',in_order_id),CONCAT('newbalance: ',in_new_balance),now());

			/* now correct for the -1 on pings which may be in the past */

			IF xmesg_format LIKE 'P' AND xmesg_next_dt < NOW() THEN
				SET xmesg_next_dt = NOW();
			ELSE
				IF xmesg_info LIKE "%firmware%" THEN
					IF xmesg_next_dt < NOW() THEN
						SET xmesg_next_dt = NOW();
					END IF;
				ELSE
					IF (xmesg_format LIKE 'T' OR xmesg_format LIKE 'G%') AND (DATE_SUB(xmesg_next_dt, INTERVAL 1 MINUTE) < NOW() AND xdelay > -1) THEN
						SELECT DATE_ADD(NOW(), INTERVAL 1 MINUTE) INTO xmesg_next_dt;
					END IF;
				END IF;
			END IF;

			SET xlocked = 'N';
			-- determine if we need to set the locked to 'Pulled'  so it gets skipped by the auto sender.
			IF xmesg_format LIKE 'G%' OR xmesg_format LIKE 'O%' OR xmesg_format LIKE 'W%' OR xmesg_format LIKE 'S%' OR xmesg_format LIKE 'H%' THEN
			 	SET xlocked = 'P';
				IF xmesg_info LIKE '%firmware%' OR xmesg_format LIKE 'WM' THEN
					SET xviewed = 'N';
				END IF;
			END IF;

			SELECT UPPER(immediate_message_delivery) INTO ximmediate_message_delivery FROM Merchant WHERE merchant_id = in_merchant_id;

			IF in_user_id < 100 OR ximmediate_message_delivery = 'Y' THEN
				SET xmesg_next_dt = NOW();
			END IF;

-- local testing hack
/* IF xmesg_next_dt > DATE_SUB(NOW(), INTERVAL 2 SECOND) THEN
	SET xmesg_next_dt = DATE_SUB(NOW(), INTERVAL 5 SECOND);
END IF;
*/
IF (in_user_id > 2) THEN
			-- code to allow admin orders to get picked up by the admin GPRS printer
			IF (in_user_id < 100 OR xdbname = 'smaw_test') AND xmesg_format LIKE 'G%' THEN
				IF in_user_id < 100 OR xmesg_info LIKE '%firmware=7%' THEN
					SET xmesg_format = 'GZ';
					SET xtemp_merchant_id = 10;
				ELSE
					-- old firmware printer
					SET xtemp_merchant_id = 20;
				END IF;

				INSERT INTO `Merchant_Message_History` ( `merchant_id` , `order_id` , `message_format` ,`message_delivery_addr`,`next_message_dt_tm`,`locked`,
								`viewed`,`message_type`,`info`,`message_text`,`created`,`logical_delete` )
							VALUES (xtemp_merchant_id, in_order_id,xmesg_format, 'HQ' ,xmesg_next_dt,xlocked, xviewed,xmesg_type,xmesg_info, xmesg_text,NOW(),'Y');
			ELSE
				INSERT INTO `Merchant_Message_History` ( `merchant_id` , `order_id` , `message_format` ,`message_delivery_addr`,`next_message_dt_tm`,`locked`,`viewed`,`message_type`,`info`,`message_text`,`created`,`logical_delete`  )
					VALUES (in_merchant_id, in_order_id,xmesg_format, xmesg_delivery_addr ,xmesg_next_dt,xlocked,xviewed,xmesg_type,xmesg_info,xmesg_text,NOW(),'Y');
			END IF;
END IF;

-- CHANGE THIS
			-- now add duplicate GPRS message for HQ printer on all even orders. this si just during the first couple months of launch.
	/*		IF xmesg_format LIKE 'G%' AND MOD(in_order_id,2) = 0 THEN
	
					INSERT INTO `Merchant_Message_History` ( `merchant_id` , `order_id` , `message_format` ,`message_delivery_addr`,`next_message_dt_tm`,`locked`,
									`viewed`,`message_type`,`info`,`created`,`logical_delete` )
						VALUES (10, in_order_id,'GZ', 'HQ' ,NOW(),'P','N','O','firmware=7',NOW(),'N');
					INSERT INTO `Merchant_Message_History` ( `merchant_id` , `order_id` , `message_format` ,`message_delivery_addr`,`next_message_dt_tm`,`message_type`,`info`,`message_text`, `created` )
						VALUES (10, in_order_id,'T', '5086155099' ,NOW(),'A','firmware=7.0','***',NOW());
			END IF;
*/
			
/*			IF xmesg_format LIKE 'F%' THEN

				SELECT (  DATE_ADD(xmesg_next_dt, INTERVAL 4 MINUTE) ) INTO xmesg_next_dt;
				INSERT INTO `Merchant_Message_History` ( `merchant_id` , `order_id` , `message_format` ,`message_delivery_addr`,`next_message_dt_tm`,`message_type`, `created`,`logical_delete`  )
					VALUES (in_merchant_id, in_order_id,'FC', 'StatusCheck' ,xmesg_next_dt,'O',NOW(),'Y');
			END IF;
*/
		END LOOP;
         CLOSE msgs;

		SELECT `value` INTO xglobal_rewardr_active FROM `Property` WHERE name = 'global_rewardr_active';

		IF xglobal_rewardr_active = 'Y' THEN
			
					INSERT INTO `Merchant_Message_History` ( `merchant_id` , `order_id` , `message_format` ,`message_delivery_addr`,`next_message_dt_tm`,`message_type`, `created`,`logical_delete`  )
					VALUES (in_merchant_id, in_order_id,'R', 'rewarder' ,NOW(),'R',NOW(),'Y');

		END IF;

END
 ;;
delimiter ;

-- ----------------------------
--  Procedure structure for `SMAWSP_HOLIDAY_HOUR`
-- ----------------------------
DROP PROCEDURE IF EXISTS `SMAWSP_HOLIDAY_HOUR`;
delimiter ;;
CREATE DEFINER=`itsquik`@`%` PROCEDURE `SMAWSP_HOLIDAY_HOUR`()
INSERT INTO Holiday_Hour
SELECT NULL, A.merchant_id, holiday, 'Y', open, close, second_close, now(), now(), 'N'
FROM (
      SELECT merchant_id, '2012-01-01' holiday  FROM Holiday H WHERE newyearsday ='O'
UNION SELECT merchant_id, '2012-11-22' holiday  FROM Holiday H WHERE thanksgiving ='O'
UNION SELECT merchant_id, '2012-12-25' holiday  FROM Holiday H WHERE christmas ='O'
UNION SELECT merchant_id, '2012-04-08' holiday  FROM Holiday H WHERE easter ='O'
UNION SELECT merchant_id, '2012-07-04' holiday  FROM Holiday H WHERE fourthofjuly ='O'
) A
LEFT OUTER JOIN Hour H ON H.merchant_id = A.merchant_id AND DAYOFWEEK(holiday) = H.day_of_week AND hour_type = 'R'
WHERE CONCAT(A.merchant_id,holiday) NOT IN (SELECT CONCAT(H.merchant_id,the_date)  FROM Holiday_Hour H)

UNION

SELECT NULL, A.merchant_id, holiday, 'N', open, close, second_close,  now() , now() , 'N'
FROM (
      SELECT merchant_id, '2012-01-01' holiday  FROM Holiday H WHERE newyearsday ='C'
UNION SELECT merchant_id, '2012-11-22' holiday  FROM Holiday H WHERE thanksgiving ='C'
UNION SELECT merchant_id, '2012-12-25' holiday  FROM Holiday H WHERE christmas ='C'
UNION SELECT merchant_id, '2012-04-08' holiday  FROM Holiday H WHERE easter ='C'
UNION SELECT merchant_id, '2012-07-04' holiday  FROM Holiday H WHERE fourthofjuly ='C'
) A
LEFT OUTER JOIN Hour H ON H.merchant_id = A.merchant_id AND DAYOFWEEK(holiday) = H.day_of_week AND hour_type = 'R'
WHERE CONCAT(A.merchant_id,holiday) NOT IN (SELECT CONCAT(H.merchant_id,the_date)  FROM Holiday_Hour H)
 ;;
delimiter ;

-- ----------------------------
--  Procedure structure for `SMAWSP_INCREMENT_MENU_KEY`
-- ----------------------------
DROP PROCEDURE IF EXISTS `SMAWSP_INCREMENT_MENU_KEY`;
delimiter ;;
CREATE DEFINER=`itsquikadmin`@`%` PROCEDURE `SMAWSP_INCREMENT_MENU_KEY`(in_menu_id int(11))
BEGIN
	IF in_menu_id = 0 THEN
		UPDATE Menu SET last_menu_change = UNIX_TIMESTAMP();
	ELSE
		UPDATE Menu SET last_menu_change = UNIX_TIMESTAMP() WHERE menu_id = in_menu_id;
	END IF;
END
 ;;
delimiter ;

-- ----------------------------
--  Procedure structure for `SMAWSP_MENU_CHANGE_SCHEDULER`
-- ----------------------------
DROP PROCEDURE IF EXISTS `SMAWSP_MENU_CHANGE_SCHEDULER`;
delimiter ;;
CREATE DEFINER=`itsquik`@`%` PROCEDURE `SMAWSP_MENU_CHANGE_SCHEDULER`(OUT num_activated int(11), OUT num_deactivated int(11))
    DETERMINISTIC
BEGIN

	DECLARE xmenu_change_id int(11);
	DECLARE xmenu_id int(11);
	DECLARE xobject_type VARCHAR(50);
	DECLARE xobject_id int(11);
	DECLARE xreplace_id int(11);
	DECLARE xday_of_week int(11);
	DECLARE xcurrent_status VARCHAR(3);
	DECLARE xstart TIME;
	DECLARE xstop TIME;
	DECLARE xactive char(1);
	DECLARE xnum_objects_activated int(11) DEFAULT 0;
	DECLARE xnum_objects_deactivated int(11) DEFAULT 0;
	DECLARE xserver_offset INT(11);

	DECLARE xdbname varchar(20);

	DECLARE menuchange CURSOR FOR SELECT menu_change_id,menu_id,object_type,object_id,replace_id FROM Menu_Change_Schedule WHERE day_of_week=DAYOFWEEK(NOW()) AND current_status = 'off' AND NOW() > `start` AND NOW() < `stop` AND active = 'Y' AND logical_delete ='N';
	
	DECLARE menuchange2 CURSOR FOR SELECT menu_change_id,menu_id,object_type,object_id,replace_id FROM Menu_Change_Schedule WHERE day_of_week=DAYOFWEEK(NOW()) AND current_status = 'on' AND (NOW() < `start` OR NOW() > `stop`) AND active = 'Y' AND logical_delete ='N' UNION SELECT menu_change_id,menu_id,object_type,object_id,replace_id FROM Menu_Change_Schedule WHERE day_of_week != DAYOFWEEK(NOW()) AND current_status = 'on' AND active = 'Y' AND logical_delete ='N';

mainblock:BEGIN

	DECLARE noMoreRows int(1) DEFAULT 0;
	DECLARE CONTINUE HANDLER FOR NOT FOUND
	BEGIN
		SET noMoreRows = 1;
	END;

	SELECT `value` INTO xserver_offset FROM Property WHERE name = 'default_server_timezone_offset';
	SET SESSION time_zone="-7:00";

	OPEN menuchange;

	menuChangeOnLoop:LOOP
		
		FETCH menuchange INTO xmenu_change_id,xmenu_id,xobject_type,xobject_id,xreplace_id;
		IF noMoreRows THEN
			leave menuChangeOnLoop;
		END IF;

		INSERT INTO Errors VALUES (null,'We have a activate in teh menu scheduele',CONCAT('object:', xobject_type),CONCAT('id:', xobject_id),CONCAT('replace id:',xreplace_id),now());
		SET xnum_objects_activated = xnum_objects_activated + 1;

		UPDATE Menu_Change_Schedule SET current_status = 'on' WHERE menu_change_id = xmenu_change_id LIMIT 1;
		CALL SMAWSP_INCREMENT_MENU_KEY(xmenu_id);
	END LOOP;

	CLOSE menuchange;
	SET noMoreRows = 0;

	OPEN menuchange2;

	menuChangeOffLoop:LOOP
		
		FETCH menuchange2 INTO xmenu_change_id,xmenu_id,xobject_type,xobject_id,xreplace_id;
		IF noMoreRows THEN
			leave menuChangeOffLoop;
		END IF;
		INSERT INTO Errors VALUES (null,'We have a DE-activate in the menu schedule',CONCAT('object:', xobject_type),CONCAT('id:', xobject_id),CONCAT('replace id:',xreplace_id),now());
		SET xnum_objects_deactivated = xnum_objects_deactivated + 1;

		UPDATE Menu_Change_Schedule SET current_status = 'off' WHERE menu_change_id = xmenu_change_id LIMIT 1;
		CALL SMAWSP_INCREMENT_MENU_KEY(xmenu_id);
	END LOOP;

	CLOSE menuchange2;

	END;
	IF xnum_objects_activated > 0 OR xnum_objects_deactivated > 0 THEN
		INSERT INTO Errors VALUES (null,'ENDING the MENU_CHANGE_SCHEDULER',CONCAT('objects activated: ', xnum_objects_activated),CONCAT('objects DE-activated: ', xnum_objects_deactivated),NULL,now());
	END IF;

	SET num_activated = xnum_objects_activated;
	SET num_deactivated = xnum_objects_deactivated;

END
 ;;
delimiter ;

-- ----------------------------
--  Procedure structure for `SMAWSP_rpt_Weekly`
-- ----------------------------
DROP PROCEDURE IF EXISTS `SMAWSP_rpt_Weekly`;
delimiter ;;
CREATE DEFINER=`itsquik`@`%` PROCEDURE `SMAWSP_rpt_Weekly`(
     IN x_brand_id INT(11), IN x_start_date VARCHAR(10),      IN x_end_date VARCHAR(10)

   )
BEGIN

DROP TABLE rpt_Weely_exclusions;
CREATE TABLE rpt_Weely_exclusions
SELECT merchant_id FROM Merchant WHERE merchant_id iN (101065,101101,101051,101069,101037,101485,103328,101237,101729,101363,101361,103304,103318,103324,101571,101637,
101487,101489,101519,101543,103386,103390)
UNION
-- postponed
SELECT merchant_id FROM Merchant WHERE merchant_id iN (101711,101725,101727,101733,101731);

DROP TABLE rpt_Weely;

CREATE TABLE rpt_Weely
SELECT moes_fbc_id, IFNULL(type_id_name,"UNKNOWN") as fbc_name,  M.merchant_id, M.merchant_external_id, DATE_FORMAT(DATE_SUB(NOW(),INTERVAL 1 DAY),"%M %D %Y") AS date_period, M.name, M.address1, M.city,
       IFNULL(SUM(orders),0) as orders, IFNULL(SUM(order_qty),0) as order_qty, IFNULL(SUM(revenue),0) as revenue, IFNULL(SUM(taxes),0) as taxes, IFNULL(SUM(tips),0) as tips,
       IFNULL(SUM(grand_total),0) as grand_total, IFNULL(SUM(promo_amt),0) as promo_amt, IFNULL(SUM(trans_fee),0) as trans_fee, IFNULL(SUM(web),0) web, IFNULL(SUM(mobile),0) mobile
FROM Merchant M
LEFT OUTER JOIN adm_moes_fbc fbc ON fbc.merchant_external_id =M.merchant_external_id
LEFT OUTER JOIN (SELECT * FROM Lookup L WHERE type_id_field = "moes_fbc") L ON fbc.moes_fbc_id = L.type_id_value
LEFT OUTER JOIN (
          SELECT U.merchant_id, DATE_FORMAT( pickup_dt_tm, "%Y-%m-%d" ) date_period, count(*) as orders,
               SUM(order_qty) order_qty, SUM(U.order_amt ) revenue, SUM(U.total_tax_amt) as taxes, SUM(U.tip_amt) as tips, SUM(U.grand_total) as grand_total,
               SUM(U.promo_amt) as promo_amt, SUM(U.trans_fee_amt) as trans_fee, SUM(IF(device_type="web",1,0)) web, SUM(IF(device_type!="web",1,0)) mobile
          FROM Orders U
          WHERE U.logical_delete = "N" AND U.status="E"
          AND U.order_dt_tm  BETWEEN DATE(x_start_date) AND DATE(x_end_date)
          GROUP BY U.merchant_id
) O ON M.merchant_id = O.merchant_id
WHERE 1=1
AND M.brand_id = x_brand_id
AND M.merchant_id NOT IN (SELECT merchant_id FROM rpt_Weely_exclusions)
GROUP BY M.merchant_id
ORDER BY type_id_name, CAST(M.merchant_external_id AS  SIGNED )
;

END
 ;;
delimiter ;

-- ----------------------------
--  Procedure structure for `smawsp_tester`
-- ----------------------------
DROP PROCEDURE IF EXISTS `smawsp_tester`;
delimiter ;;
CREATE DEFINER=`itsquikadmin`@`%` PROCEDURE `smawsp_tester`(IN indate date, OUT xtotal_users int(11), OUT xno_orders int(11), OUT xone_order int(11), OUT xmore_than_one int(11))
BEGIN

	DECLARE xuser_id int(11);
	DECLARE xskin_id int(11);
	DECLARE xlat DECIMAL(10,6);
	DECLARE xlng DECIMAL(10,6);
	DECLARE xdistance DECIMAL(10,2);
	DECLARE xdevice_type VARCHAR(20);
	DECLARE xtime_diff decimal(10.2);
	DECLARE xcreated TIMESTAMP;
	DECLARE xmodified TIMESTAMP;
	DECLARE xfirst_order_date TIMESTAMP;

	DECLARE xorder_count int(11);

	DECLARE userscursor CURSOR FOR SELECT user_id FROM user_creation_data WHERE dist_to_closest_skin_store <= 10.00 AND created >= indate  and created < DATE_ADD(indate, INTERVAL 7 DAY);

mainblock:BEGIN

	DECLARE noMoreRows int(1) DEFAULT 0;
	DECLARE CONTINUE HANDLER FOR NOT FOUND
		BEGIN
			SET noMoreRows = 1;
		END;

	SET xtotal_users = 0;
	SET xno_orders = 0;
	SET xone_order = 0;
	SET xmore_than_one = 0;

	OPEN userscursor;
	userscursorLoop:LOOP
	
		FETCH userscursor INTO xuser_id;
		IF noMoreRows THEN
			leave userscursorLoop;
		END IF;

				SET xtotal_users = xtotal_users+1;

				SELECT count(*) INTO xorder_count FROM Orders WHERE user_id = xuser_id AND created < DATE_ADD(indate, INTERVAL 7 DAY) AND status = 'E';
				if xorder_count = 0 then
					set xno_orders = xno_orders+1;
				ELSE
					IF xorder_count = 1 THEN
						SET xone_order = xone_order+1;
					ELSE
						SET xmore_than_one = xmore_than_one+1;
					END IF;
				END IF;

	END LOOP;
	CLOSE userscursor;
	
END;

END
 ;;
delimiter ;

-- ----------------------------
--  Procedure structure for `SMAWSP_UPDATE_BALANCE`
-- ----------------------------
DROP PROCEDURE IF EXISTS `SMAWSP_UPDATE_BALANCE`;
delimiter ;;
CREATE DEFINER=`itsquikadmin`@`%` PROCEDURE `SMAWSP_UPDATE_BALANCE`(  IN in_user_id int(11),
								    IN in_balance decimal(10,2),
								    IN in_order_id int(11),
								    IN in_merchant_id int(11),
                                                                    IN server_pickup_dt datetime,
                                                                    IN in_grand_total DECIMAL (10,2),
                                                                    IN in_merch_default_lead INT(11), IN in_xtest VARCHAR(5),
                                                                    IN logit int(1),
                                                                    OUT out_success varchar(100),
                                                                    OUT out_return_id INT(11),
                                                                    OUT out_error_message varchar(255)
									)
BEGIN
	DECLARE xnew_balance DECIMAL(10,2);
	DECLARE xorder_count int(11) default 0;
	DECLARE xdbname varchar(20);
	DECLARE xraw_stamp VARCHAR(255);

	BEGIN
		SELECT DATABASE() INTO xdbname;

		SELECT stamp INTO xraw_stamp FROM TempOrders;

		IF logit Then
			INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' Starting SMAWSP_UPDATE_BALANCE()'),CONCAT('user:',in_user_id),CONCAT('Order:',in_order_id),'',now());
		END IF;
		
		IF in_balance IS NULL THEN
			SELECT balance INTO in_balance FROM `User` WHERE user_id = in_user_id;
		END IF;

		SET xnew_balance = in_balance - in_grand_total;

		CALL SMAWSP_CREATE_ORDER_MESSAGES(in_merchant_id,in_order_id,server_pickup_dt,in_merch_default_lead,in_user_id,xnew_balance,@return);
		IF @return = 'FAILURE' THEN
			SET out_error_message = 'There was an error thrown in IQSP_CREATE_ORDER_MESSAGES() the order is being cancelled';
			SET out_return_id = 900;
			INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' ERROR: messages not created for order'),CONCAT('user:',in_user_id),'',CONCAT('OrderID:',in_order_id),now());
			UPDATE `Orders` SET status = 'N' WHERE order_id = in_order_id LIMIT 1;
			SET out_success = 'FAILURE';
		ELSE
			IF @return = 'NOMESSAGES' THEN
				SET out_error_message = 'There was an error thrown in IQSP_CREATE_ORDER_MESSAGES() There is NO order message (type X) set up for this merchant';
				SET out_error_message = 'MERCHANT_DELIVERY_MESSAGE_NOT_SET_UP';
				SET out_return_id = 800;
				INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' ERROR: no order message (X) exists for this merchant'),CONCAT('user:',in_user_id),'',CONCAT('OrderID:', in_order_id),now());
				UPDATE `Orders` SET status = 'N' WHERE order_id = in_order_id LIMIT 1;
				SET out_success = 'FAILURE';
			ELSE
				IF logit Then
					INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' Messages successfully created, about to adjust users balance'),CONCAT('user:',in_user_id),CONCAT('Order:',in_order_id),'',now());                                   				END IF;

				IF in_user_id > 1000 THEN
					UPDATE `User` SET balance = xnew_balance WHERE user_id = in_user_id;
					INSERT INTO `Balance_Change` (`user_id`,`balance_before`,`charge_amt`,`balance_after`,`process`,`order_id`,`notes`,`created`) VALUES (in_user_id, in_balance,-in_grand_total,xnew_balance,'Order',in_order_id,notes,now());
				ELSE
					INSERT INTO Errors VALUES (null,CONCAT(xraw_stamp,' No record created in balance change table'),CONCAT('user:',in_user_id),CONCAT('Order:',in_order_id),'',now());
				END IF;
				SET out_success = 'success';

			END IF;
		END IF;
	END;
END
 ;;
delimiter ;

-- ----------------------------
--  Procedure structure for `SMAWSP_USER_BALANCE_CHANGE`
-- ----------------------------
DROP PROCEDURE IF EXISTS `SMAWSP_USER_BALANCE_CHANGE`;
delimiter ;;
CREATE DEFINER=`itsquikadmin`@`%` PROCEDURE `SMAWSP_USER_BALANCE_CHANGE`(IN in_user_id int(11), IN in_change decimal(10,2), IN in_process varchar(255), IN in_notes varchar(255), OUT out_return_id INT(11), OUT out_error_message varchar(255))
BEGIN
	DECLARE xstarting_balance decimal(10,2);
	DECLARE xfinishing_balance decimal(10,2);

	BEGIN

		INSERT INTO Errors VALUES (null,'Starting SMAWSP_USER_BALANCE_CHANGE()',CONCAT('user:',in_user_id),CONCAT('process:', in_process),'',now());
		
		SELECT balance INTO xstarting_balance FROM `User` WHERE user_id = in_user_id;
		SET xfinishing_balance = xstarting_balance + in_change;
		
		UPDATE `User` SET balance = balance + in_change WHERE user_id = in_user_id LIMIT 1;
		INSERT INTO Errors VALUES (null,'USERS BALANCE CHANGED!',CONCAT('user:',in_user_id),CONCAT('process:', in_process),'',now());
		INSERT INTO `Balance_Change` (`user_id`,`balance_before`,`charge_amt`,`balance_after`,`process`,`notes`,`created`)VALUES (in_user_id, xstarting_balance, in_change, xfinishing_balance,in_process,in_notes,now());
		SET out_return_id = LAST_INSERT_ID();
		INSERT INTO Errors VALUES (null,'BALANCE CHANGE ROW ADDED!',CONCAT('user:',in_user_id),CONCAT('process:', in_process),CONCAT('balId:', out_return_id),now());
	END;
END
 ;;
delimiter ;

-- ----------------------------
--  Procedure structure for `SMAWSP_USER_CREATION_DATA_CREATE`
-- ----------------------------
DROP PROCEDURE IF EXISTS `SMAWSP_USER_CREATION_DATA_CREATE`;
delimiter ;;
CREATE DEFINER=`itsquikadmin`@`%` PROCEDURE `SMAWSP_USER_CREATION_DATA_CREATE`()
BEGIN

	DECLARE xuser_id int(11);
	DECLARE xskin_id int(11);
	DECLARE xlat DECIMAL(10,6);
	DECLARE xlng DECIMAL(10,6);
	DECLARE xdistance DECIMAL(10,2);
	DECLARE xdevice_type VARCHAR(20);
	DECLARE xtime_diff decimal(10.2);
	DECLARE xcreated TIMESTAMP;
	DECLARE xmodified TIMESTAMP;
	DECLARE xfirst_order_date TIMESTAMP;

	DECLARE userscursor CURSOR FOR SELECT user_id,device_type,created FROM User WHERE logical_delete = 'N' AND user_id > 19999;

mainblock:BEGIN

	DECLARE noMoreRows int(1) DEFAULT 0;
	DECLARE CONTINUE HANDLER FOR NOT FOUND
	BEGIN
		SET noMoreRows = 1;
	END;

	OPEN userscursor;
START TRANSACTION;
	userscursorLoop:LOOP
	
		FETCH userscursor INTO xuser_id,xdevice_type,xcreated;
		IF noMoreRows THEN
			leave userscursorLoop;
		END IF;

		SELECT lat,`long`,skin_id INTO xlat,xlng,xskin_id FROM `merchant_list_request_location` WHERE user_id = xuser_id Order BY created ASC limit 1;

		IF noMoreRows THEN
			SET noMoreRows = 0;
		ELSE
			SELECT z.distance INTO xdistance FROM (SELECT ( 3959 * acos( cos( radians(xlat) ) * cos( radians( a.lat ) ) * cos( radians( a.lng ) - radians(xlng) ) + sin( radians(xlat) ) * sin( radians( a.lat ) ) ) ) AS distance
				FROM Merchant a, Skin_Merchant_Map b WHERE b.skin_id = xskin_id AND a.merchant_id = b.merchant_id ORDER BY distance ASC LIMIT 1) z WHERE 1=1;

			IF noMoreRows THEN
				SET noMoreRows = 0;
			ELSE
				SELECT created INTO xfirst_order_date FROM Orders WHERE user_id = xuser_id ORDER BY created ASC LIMIT 1;
				IF noMoreRows THEN
					SET xtime_diff = NULL;
					SET noMoreRows = 0;
				ELSE
					SET xtime_diff = TIMESTAMPDIFF(DAY, xcreated, xfirst_order_date);
					
				END IF;
				INSERT INTO user_creation_data VALUES (xuser_id,xskin_id,xlat,xlng,xdistance,xdevice_type, xtime_diff,xcreated,NOW());
			END IF;
		END IF;

	END LOOP;
COMMIT;
	CLOSE userscursor;
END;

END
 ;;
delimiter ;

-- NEW TABLES ----

-- ----------------------------
--  Table structure for `Menu_Upsell_Item_Maps`
-- ----------------------------
DROP TABLE IF EXISTS `Menu_Upsell_Item_Maps`;
CREATE TABLE `Menu_Upsell_Item_Maps` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `menu_id` int(11) NOT NULL,
  `item_id` int(11) NOT NULL,
  `active` char(1) NOT NULL DEFAULT 'Y',
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`id`),
  UNIQUE KEY `item_id` (`item_id`),
  KEY `menu_id` (`menu_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1000;

ALTER TABLE `Promo_Type1_Amt_Map` ADD UNIQUE (`promo_id`);
ALTER TABLE `Promo_Message_Map` ADD UNIQUE (`promo_id`);
ALTER TABLE `Item_Size_Map` CHANGE `tax_group` `tax_group` INT( 11 ) NOT NULL DEFAULT '1';
ALTER TABLE `User_Groups` ADD `convenience_fee_override` DECIMAL( 4, 2 ) NULL DEFAULT NULL AFTER `promo_id` , ADD `minimum_lead_time_override` INT( 3 ) NULL DEFAULT NULL AFTER `convenience_fee_override` ;
ALTER TABLE `Merchant_Delivery_Price_Distance` ADD `polygon_coordinates` MEDIUMTEXT NULL DEFAULT NULL AFTER `zip_codes` ;
ALTER TABLE `Merchant_Delivery_Info` ADD `delivery_price_type` ENUM( 'driving', 'zip', 'polygon' ) NOT NULL DEFAULT 'driving' AFTER `zip_codes`;
UPDATE `Merchant_Delivery_Info` set delivery_price_type = 'zip' WHERE zip_codes = 'true';
ALTER TABLE `Merchant_Delivery_Price_Distance` ADD `minimum_order_amount` DECIMAL(10,2) NULL DEFAULT NULL AFTER `price`, ADD `name` VARCHAR(255) NULL DEFAULT NULL AFTER `merchant_id`;

CREATE TABLE `User_Delivery_Location_Merchant_Price_Maps` (
	`id` int(11) NOT NULL AUTO_INCREMENT,
	`user_delivery_location_id` int(11) NOT NULL,
	`merchant_id` int(11) NOT NULL,
	`merchant_delivery_price_distance_map_id` int(11) NOT NULL,
	`created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
	`logical_delete` enum('N','Y') NOT NULL DEFAULT 'N',
	PRIMARY KEY (`id`),
	UNIQUE KEY `user_location_merchant` (`user_delivery_location_id`,`merchant_id`),
	KEY `user_location_ids` (`user_delivery_location_id`),
	KEY `merchant_ids` (`merchant_id`)
					) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
ALTER TABLE `User_Delivery_Location_Merchant_Price_Maps`
	ADD CONSTRAINT `user_delivery_location_ids` FOREIGN KEY (`user_delivery_location_id`) REFERENCES `User_Delivery_Location` (`user_addr_id`) ON DELETE CASCADE ON UPDATE NO ACTION,
	ADD CONSTRAINT `merchant_ids` FOREIGN KEY (`merchant_id`) REFERENCES `Merchant` (`merchant_id`) ON DELETE CASCADE ON UPDATE NO ACTION;
INSERT INTO `Lookup_Master` (`id`, `type_id_field`, `description`, `created`, `modified`, `logical_delete`) VALUES (NULL, 'state_delivery_is_taxed', 'does the state tax delivery', '0000-00-00 00:00:00.000000', '0000-00-00 00:00:00.000000', 'N');
INSERT INTO Lookup(`type_id_field`, `type_id_value`, `type_id_name`, `active`, `created`, `modified`, `logical_delete`) VALUES ('message_template', 'A', '/order_templates/task_retail/execute_order_task_retail.xml', 'Y', CURRENT_TIMESTAMP(), CURRENT_TIMESTAMP(), 'N');

CREATE TABLE IF NOT EXISTS `User_Brand_Loyalty_History` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `brand_id` int(11) NOT NULL,
  `order_id` int(11) DEFAULT NULL,
  `process` varchar(255) DEFAULT NULL,
  `points_added` int(11) NOT NULL DEFAULT '0',
  `points_redeemed` int(11) NOT NULL DEFAULT '0',
  `action_date` DATETIME NOT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`id`),
  KEY `user_id` (`user_id`,`brand_id`,`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1001;
		
CREATE TABLE IF NOT EXISTS `Promo_Brand_External_Id_Map` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `promo_id` int(11) NOT NULL,
  `brand_id` int(11) NOT NULL,
  `external_id` VARCHAR(100) DEFAULT NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `logical_delete` CHAR NOT NULL DEFAULT 'N',
  PRIMARY KEY (`id`),
  KEY `user_id` (`promo_id`,`brand_id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1001;
		
CREATE TABLE IF NOT EXISTS `User_Extra_Data` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `birthdate` varchar(10) NULL,
  `zip` varchar(5) NULL,
  `process` varchar(255) NULL,
  `results` varchar(255) NULL,
  `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `modified` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 AUTO_INCREMENT=1000 ;

CREATE TABLE IF NOT EXISTS `Fixed_Tax` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `merchant_id` int(11) NOT NULL DEFAULT '0',
  `name` varchar(40) NOT NULL,
  `description` varchar(100) NOT NULL DEFAULT ' ',
  `amount` decimal(6,2) NOT NULL DEFAULT '0.0',
  `created` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00',
  `modified` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `logical_delete` char(1) NOT NULL DEFAULT 'N',
  PRIMARY KEY (`id`),
  KEY `fk_FT_merchant_id` (`merchant_id`),
  CONSTRAINT `fk_FT_merchant_id` FOREIGN KEY (`merchant_id`) REFERENCES `Merchant` (`merchant_id`) ON DELETE CASCADE ON UPDATE NO ACTION
) ENGINE=InnoDB AUTO_INCREMENT=1000 DEFAULT CHARSET=utf8 COMMENT='To list any fixed taxes such as bag tax';

INSERT INTO Property (`name`,`value`, `created`, `modified`) VALUES ('vault_alert_list_sms','1234567890', NOW(), NOW());
