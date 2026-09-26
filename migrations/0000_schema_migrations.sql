-- Tracks which migration files have been applied.
CREATE TABLE schema_migrations (
   version     TEXT        PRIMARY KEY, -- file name, e.g. '0001_identity'
   applied_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);
