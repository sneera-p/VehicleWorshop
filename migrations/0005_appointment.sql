-- Appointment
CREATE TYPE appointment_status AS ENUM ('Pending', 'Confirmed', 'Cancelled', 'Completed');

CREATE TABLE appointments (
   appointment_no SERIAL             PRIMARY KEY,
   owner_id       INT                NOT NULL REFERENCES vehicle_owners,
   vehicle_id     INT                NOT NULL REFERENCES vehicles,
   scheduled_at   TIMESTAMPTZ        NOT NULL,
   status         appointment_status NOT NULL DEFAULT 'Pending',
   description    TEXT,
   confirmed_by   INT                REFERENCES staff,  -- NULL until confirmed
   created_at     TIMESTAMPTZ        NOT NULL DEFAULT now()
);
CREATE INDEX ON appointments (owner_id);
CREATE INDEX ON appointments (scheduled_at);
