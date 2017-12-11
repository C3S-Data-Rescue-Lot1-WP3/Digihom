-- ============================================================
-- digihom
-- ============================================================

CREATE SEQUENCE seq START 1000;

CREATE TABLE key1(
	id bigint PRIMARY KEY DEFAULT nextval('seq'),
	keyword varchar(128) NOT NULL UNIQUE
);

CREATE TABLE key2(
	id bigint PRIMARY KEY DEFAULT nextval('seq'),
	keyword varchar(128) NOT NULL UNIQUE
);

CREATE TABLE key3(
	id bigint PRIMARY KEY DEFAULT nextval('seq'),
	keyword varchar(128) NOT NULL UNIQUE
);

CREATE TABLE jobs(
	id bigint PRIMARY KEY DEFAULT nextval('seq'),
	dir varchar(1024),
	key1 bigint NOT NULL REFERENCES key1,
	key2 bigint REFERENCES key2,
	remark varchar(1024),

	dig_user varchar(1024),
	dig_dateout date,
	dig_file varchar(128),
	dig_datein date,
	dig_time real,
	dig_remark varchar(1024),

	chk_user varchar(1024),
	chk_dateout date,
	chk_file varchar(128),
	chk_datein date,
	chk_time real,
	chk_remark varchar(1024)
);

CREATE TABLE files(
	id bigint PRIMARY KEY DEFAULT nextval('seq'),
	filename varchar(1024),
	job bigint NOT NULL REFERENCES jobs,
	key3 bigint NOT NULL REFERENCES key3
);

CREATE VIEW job_view AS
       SELECT jobs.*, key1.keyword as key1wrd, key2.keyword as key2wrd,
       CASE WHEN dig_dateout IS NULL THEN 0
       	    WHEN dig_datein  IS NULL THEN 1
	    WHEN chk_dateout IS NULL THEN 2
       	    WHEN chk_datein  IS NULL THEN 3
	    ELSE 4 END AS status
       FROM (jobs LEFT JOIN key1 ON jobs.key1=key1.id) LEFT JOIN key2 ON jobs.key2=key2.id
       ORDER BY key1.keyword, key2.keyword;


