/* Here, no database upgrade is porvided, we assume 1.0.0.0 version was never installed in a production environment */
/* REM: In geonames location names are VARCHAR(200) */
CREATE TABLE IF NOT EXISTS metadata (
    keyword VARCHAR(64) NOT NULL,
    value VARCHAR(64),
    UNIQUE(keyword)
);

/* This table must be kept locale independant */
CREATE TABLE geocoder_locations (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    geocoding_datetime_utc DATETIME NOT NULL,
    geocoding_provider VARCHAR(64) NOT NULL,
    geocoder_object_id VARCHAR(64) NOT NULL,
    geocoded_latitude FLOAT NOT NULL,
    geocoded_longitude FLOAT NOT NULL,
    geocoded_country_code FLOAT NOT NULL,
    geocoded_timezone VARCHAR(32) NULL,
    place_id BIGINT UNSIGNED NULL,
    UNIQUE(geocoding_provider, geocoder_object_id)
) ENGINE=INNODB CHARACTER SET=utf8;

/* Here places name and postal_code are "normalized" thanks to the geocoder (or any geocoding provider) */
CREATE TABLE places (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    postal_code VARCHAR(16) NULL,
    country_code VARCHAR(2) NOT NULL,
    best_geocoder_location_id BIGINT UNSIGNED NOT NULL,
    UNIQUE(name, postal_code, country_code),
    FOREIGN KEY (best_geocoder_location_id) REFERENCES geocoder_locations(id)
) ENGINE=INNODB CHARACTER SET=utf8;

ALTER TABLE geocoder_locations ADD CONSTRAINT place_id FOREIGN KEY (place_id) REFERENCES places(id);

CREATE TABLE place_names (
    place_id BIGINT UNSIGNED NOT NULL,
    locale VARCHAR(5) NOT NULL,
    name VARCHAR(200) NOT NULL,
    UNIQUE(place_id, locale),
    FOREIGN KEY (place_id) REFERENCES places(id)
) ENGINE=INNODB CHARACTER SET=utf8;

CREATE TABLE place_postal_codes (
    place_id BIGINT UNSIGNED NOT NULL,
    postal_code VARCHAR(16) NOT NULL,
    UNIQUE(place_id, postal_code),
    FOREIGN KEY (place_id) REFERENCES places(id)
) ENGINE=INNODB CHARACTER SET=utf8;

INSERT INTO metadata (keyword, value) VALUES ('locations_version', '1.0.1.0');
