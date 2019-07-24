CREATE TABLE `active_jobs` (
  `job_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`job_id`)
);

CREATE TABLE `canceled_jobs` (
  `job_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`job_id`)
);

CREATE TABLE `deleted_jobs` (
  `job_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`job_id`)
);

CREATE TABLE `jobs` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `request_ip` varchar(255) DEFAULT NULL,
  `wiki_user` varchar(255) DEFAULT NULL,
  `wiki_user_ip` varchar(255) DEFAULT NULL,
  `wiki_page_id` varchar(255) DEFAULT NULL,
  `code_name` varchar(255) DEFAULT NULL,
  `status` enum('pending','running','canceled','completed','timeout','deleted') DEFAULT 'pending',
  `token` varchar(255) DEFAULT NULL,
  `pid` int(10) unsigned DEFAULT NULL,
  `queued_at` datetime DEFAULT NULL,
  `ran_at` datetime DEFAULT NULL,
  `end_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL,
  `store` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `token` (`token`),
  UNIQUE KEY `token_2` (`token`),
  KEY `user_id` (`user_id`)
);

CREATE TABLE `users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `username` varchar(255) DEFAULT NULL,
  `password` varchar(255) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `args` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
);