-- TAJI fresh install (MySQL 8 / MariaDB 10.3+). Import once into an empty database.
SET NAMES utf8mb4;

CREATE TABLE categories (
  id TINYINT UNSIGNED PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  slug VARCHAR(80) NOT NULL UNIQUE,
  description VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE artists (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  slug VARCHAR(90) NOT NULL UNIQUE,
  stage_name VARCHAR(80) NOT NULL,
  category_id TINYINT UNSIGNED NOT NULL,
  bio TEXT,
  curator_note VARCHAR(200),
  photo_url VARCHAR(255),
  yt_url VARCHAR(255),
  ig_url VARCHAR(255),
  tiktok_url VARCHAR(255),
  x_url VARCHAR(255),
  fb_url VARCHAR(255),
  status ENUM('draft','published') NOT NULL DEFAULT 'draft',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_status_cat (status, category_id),
  FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL UNIQUE,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE otp_codes (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(190) NOT NULL,
  code_hash VARCHAR(255) NOT NULL,
  attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  used TINYINT(1) NOT NULL DEFAULT 0,
  expires_at DATETIME NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_email (email, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One vote per user per artist, enforced by the database.
CREATE TABLE ratings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NOT NULL,
  artist_id INT UNSIGNED NOT NULL,
  score TINYINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY one_vote (user_id, artist_id),
  KEY idx_artist (artist_id),
  CONSTRAINT chk_score CHECK (score BETWEEN 1 AND 10),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (artist_id) REFERENCES artists(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE vote_log (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  ip VARCHAR(45) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_ip (ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE schema_version (
  version INT PRIMARY KEY,
  applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
INSERT INTO schema_version (version) VALUES (2);

INSERT INTO categories (id, name, slug, description) VALUES
(1,'Matatu Culture (Nganya Art)','matatu-culture','Customizing public transit vehicles with airbrushed graffiti, bold typography, LED lighting and sound systems.'),
(2,'Music (Gengetone, Benga, Ohangla & Arbantone)','music','Homegrown genres from traditional Luo Ohangla and Kikuyu Benga to Nairobi street anthems.'),
(3,'Sculpture & Wood Carving','sculpture-wood-carving','Hand-carved wildlife, human figures and abstract pieces from Kisii soapstone and Akamba hardwood.'),
(4,'Beadwork & Jewelry Making','beadwork-jewelry','Colorful, symbolic collars, bracelets and belts in Maasai, Samburu and Turkana traditions.'),
(5,'Drawing & Painting','drawing-painting','Fine art, wildlife portraits and contemporary abstract works on canvas.'),
(6,'Street Art & Murals','street-art-murals','Walls across Mukuru, Kibera and downtown Nairobi with social commentary and community portraits.'),
(7,'Photography','photography','Kenya''s landscapes, wildlife safaris and vibrant street fashion.'),
(8,'Basketry & Weaving (Kiondo)','basketry-kiondo','Hand-woven bags and baskets of sisal and leather in Kikuyu and Kamba heritage.'),
(9,'Spoken Word & Poetry','spoken-word-poetry','Rhythmic, socially conscious poetry and storytelling at open-mics and festivals.'),
(10,'Traditional & Contemporary Dance','dance','Maasai Adumu, coastal Chakacha and modern Afro-fusion choreography.'),
(11,'Fashion & Textile Design','fashion-textile','Kikoy, Kitenge and Maasai Shuka blended into modern streetwear and runway pieces.'),
(12,'Theater & Stage Acting','theater-stage','Comedic and dramatic plays in Swahili, English and local languages.'),
(13,'Pottery & Ceramics','pottery-ceramics','Clay shaped and fired into water pots, vases and modern ceramic art.'),
(14,'Film & Scripted Drama (Riverwood)','film-riverwood','Local cinema, TV drama and scripted storytelling of everyday Kenyan life.'),
(15,'Acrobatics & Circus Arts','acrobatics-circus','Tumbling, balancing and human pyramid routines.'),
(16,'Social Media Content Creation','content-creation','Viral creators, skit makers and entertainers across TikTok, Instagram and YouTube.');
