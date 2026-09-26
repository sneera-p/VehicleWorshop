-- Identity: users + refresh tokens.
CREATE TABLE users (
   user_id        SERIAL      PRIMARY KEY,
   name           TEXT        NOT NULL,
   email          TEXT        NOT NULL UNIQUE,
   password_hash  TEXT        NOT NULL,
   contact_no     TEXT,
   picture        TEXT,                              -- file path
   roles          SMALLINT    NOT NULL DEFAULT 0     -- bitmask; 0 = no staff role
                  CHECK (roles & ~31 = 0),           -- only bits 0..4 valid
   created_at     TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE refresh_tokens (
   token_hash  CHAR(64)    PRIMARY KEY,              -- SHA-256 hex of the token
   user_id     INT         NOT NULL REFERENCES users ON DELETE CASCADE,
   user_agent  TEXT,
   ip_address  INET,
   issued_at   TIMESTAMPTZ NOT NULL DEFAULT now(),
   expires_at  TIMESTAMPTZ NOT NULL,
   CHECK (expires_at > issued_at)
);
CREATE INDEX ON refresh_tokens (user_id);
