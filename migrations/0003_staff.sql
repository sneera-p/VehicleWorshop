-- Staff
CREATE TABLE staff (
   user_id  INT   PRIMARY KEY REFERENCES users ON DELETE CASCADE,
   nic      TEXT  NOT NULL UNIQUE,
   address  TEXT
);
