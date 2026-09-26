-- CustomerVehicle
CREATE TABLE vehicle_owners (
   user_id  INT PRIMARY KEY REFERENCES users ON DELETE CASCADE
);

CREATE TABLE vehicle_makes (
   wmi      CHAR(3) PRIMARY KEY,
   brand    TEXT    NOT NULL,
   country  TEXT
);

CREATE TABLE vehicle_cache (
   wmi         CHAR(3) NOT NULL REFERENCES vehicle_makes,
   vds         CHAR(6) NOT NULL,
   model       TEXT,
   body        TEXT,
   trim        TEXT,
   engine_type TEXT,
   PRIMARY KEY (wmi, vds)
);

CREATE TABLE engine_cache (
   engine_no      TEXT     PRIMARY KEY,
   type           TEXT,
   capacity       INT      CHECK (capacity > 0),        -- cc
   cylinder_count SMALLINT CHECK (cylinder_count > 0)
);

CREATE TABLE vehicles (
   vehicle_id  SERIAL   PRIMARY KEY,
   license_no  TEXT     NOT NULL UNIQUE,
   vin         CHAR(17) NOT NULL UNIQUE,
   year        SMALLINT,
   picture     TEXT,
   owner_id    INT      NOT NULL REFERENCES vehicle_owners,
   wmi         CHAR(3),
   vds         CHAR(6),
   engine_no   TEXT     UNIQUE REFERENCES engine_cache,  -- 1:1 per engine serial
   FOREIGN KEY (wmi, vds) REFERENCES vehicle_cache
);
CREATE INDEX ON vehicles (owner_id);
