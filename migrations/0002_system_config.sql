-- SystemConfig
CREATE TYPE config_value_type AS ENUM ('int', 'decimal', 'bool', 'string', 'json');

CREATE TABLE system_config (
   config_id         TEXT              PRIMARY KEY,   -- e.g. 'tax_rate'
   value             TEXT              NOT NULL,
   value_type        config_value_type NOT NULL,
   description       TEXT,
   last_updated_at   TIMESTAMPTZ       NOT NULL DEFAULT now(),
   updated_by        INT               REFERENCES users ON DELETE SET NULL  -- NULL for seeded rows
);
