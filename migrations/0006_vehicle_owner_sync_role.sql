CREATE FUNCTION sync_customer_role()
RETURNS trigger AS $$
BEGIN
   IF TG_OP = 'INSERT' THEN
      UPDATE users
      SET roles = roles | 16 -- 0b10000 is enum value of CustomerRole
      WHERE user_id = NEW.user_id;

   ELSE -- DELETE
      UPDATE users
      SET roles = roles & ~16 -- 0b10000 is enum value of CustomerRole
      WHERE user_id = OLD.user_id;
   END IF;

   RETURN NULL; -- AFTER trigger: return value is ignored
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER vehicle_owners_sync_role
AFTER INSERT OR DELETE ON vehicle_owners
FOR EACH ROW
EXECUTE FUNCTION sync_customer_role();
