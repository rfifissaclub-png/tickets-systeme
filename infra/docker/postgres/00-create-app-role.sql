-- The official postgres image's bootstrap superuser cannot strip its own
-- SUPERUSER attribute ("the bootstrap user must have the SUPERUSER
-- attribute") — so POSTGRES_USER is deliberately *not* set to "tms" in
-- docker-compose.yml; Postgres bootstraps as its own default "postgres"
-- superuser instead, and this script (running as that superuser, since
-- every docker-entrypoint-initdb.d script connects with it) creates the
-- actual application role separately, as a plain non-superuser login.
--
-- This matters beyond hygiene: a superuser always bypasses Row-Level
-- Security regardless of FORCE ROW LEVEL SECURITY, which would silently
-- defeat this app's entire tenant-isolation model if the app connected as
-- a superuser. CREATEDB is granted explicitly — it's a distinct, narrower
-- privilege still needed to create the tms_testing database right after
-- this script runs.
CREATE ROLE tms WITH LOGIN PASSWORD 'secret' CREATEDB;
GRANT ALL PRIVILEGES ON DATABASE tms TO tms;
ALTER DATABASE tms OWNER TO tms;
