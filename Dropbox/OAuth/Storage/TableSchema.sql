CREATE TABLE `%s`
(
	`uid` int(10) unsigned NOT NULL AUTO_INCREMENT,
	`userID` int(10) unsigned NOT NULL,
	`token` text CHARACTER SET utf8 COLLATE utf8_unicode_ci NOT NULL,
	PRIMARY KEY (`uid`),
	UNIQUE KEY `userID` (`userID`)
)
ENGINE=InnoDB DEFAULT CHARSET=latin1 AUTO_INCREMENT=1 ;