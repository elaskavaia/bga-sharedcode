
-- ------
-- BGA framework: © Gregory Isabelli <gisabelli@boardgamearena.com> & Emmanuel Colin <ecolin@boardgamearena.com>
-- SharedCode implementation : © Alena Laskavaia <laskava@gmail.com>
-- 
-- This code has been produced on the BGA studio platform for use on http://boardgamearena.com.
-- See http://en.boardgamearena.com/#!doc/Studio for more information.
-- -----

-- dbmodel.sql

-- This is the file where you are describing the database schema of your game
-- Basically, you just have to export from PhpMyAdmin your table structure and copy/paste
-- this export here.
-- Note that the database itself and the standard tables ("global", "stats", "gamelog" and "player") are
-- already created and must not be created here

-- Note: The database schema is created from this file when the game starts. If you modify this file,
--       you have to restart a game to see your changes in database.





-- Example 1: create a standard "card" table to be used with the "Deck" tools (see example game "hearts"):

CREATE TABLE IF NOT EXISTS `card` (
  `card_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `card_type` varchar(16) NOT NULL,
  `card_type_arg` int(11) NOT NULL,
  `card_location` varchar(16) NOT NULL,
  `card_location_arg` int(11) NOT NULL,
  PRIMARY KEY (`card_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;

-- Example 2: create a resource table, resource count for each player, 
-- we could have altered player table but it probably better to keep it separate

CREATE TABLE IF NOT EXISTS `resource` (
  `player_id` int(10) unsigned NOT NULL,
  `resource_key` varchar(32) NOT NULL,
  `resource_count` int(10) signed NOT NULL,
  PRIMARY KEY (`player_id`,`resource_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE resource ADD CONSTRAINT fk_player_id FOREIGN KEY (player_id) REFERENCES player(player_id);


-- Example 3: create a token table, simple table contain token_id as string, location as string and state as integer
-- this is sufficient for most euro-games

CREATE TABLE IF NOT EXISTS `token` (
  `token_key` varchar(32) NOT NULL,
  `token_location` varchar(32) NOT NULL,
  `token_state` int(10),
  PRIMARY KEY (`token_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;



-- Example 5: add a custom field to the standard "player" table
-- ALTER TABLE `player` ADD `player_my_custom_field` INT UNSIGNED NOT NULL DEFAULT '0';
