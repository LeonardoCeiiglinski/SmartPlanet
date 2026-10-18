-- @@statement
CREATE SCHEMA IF NOT EXISTS app_private;

-- @@statement
REVOKE ALL ON SCHEMA app_private FROM PUBLIC;

-- @@statement
DO $guard$
BEGIN
    IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'anon') THEN
        EXECUTE 'REVOKE ALL ON SCHEMA app_private FROM anon';
    END IF;
    IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'authenticated') THEN
        EXECUTE 'REVOKE ALL ON SCHEMA app_private FROM authenticated';
    END IF;
END
$guard$;

-- @@statement
CREATE OR REPLACE FUNCTION app_private.password_is_strong(candidate text)
RETURNS boolean
LANGUAGE sql
IMMUTABLE
AS $fn$
    SELECT candidate IS NOT NULL
        AND char_length(candidate) BETWEEN 10 AND 200
        AND candidate ~ '[A-Za-z]'
        AND candidate ~ '[0-9]';
$fn$;

-- @@statement
REVOKE ALL ON FUNCTION app_private.password_is_strong(text) FROM PUBLIC;

-- @@statement
DO $guard$
BEGIN
    IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'anon') THEN
        EXECUTE 'REVOKE ALL ON FUNCTION app_private.password_is_strong(text) FROM anon';
    END IF;
    IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'authenticated') THEN
        EXECUTE 'REVOKE ALL ON FUNCTION app_private.password_is_strong(text) FROM authenticated';
    END IF;
END
$guard$;

-- @@statement
CREATE OR REPLACE FUNCTION app_private.lock_api_access()
RETURNS void
LANGUAGE plpgsql
SECURITY DEFINER
SET search_path = public
AS $fn$
DECLARE
    rel record;
    api_roles boolean;
BEGIN
    SELECT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'anon')
       AND EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'authenticated')
    INTO api_roles;

    FOR rel IN
        SELECT c.relname
        FROM pg_class c
        JOIN pg_namespace n ON n.oid = c.relnamespace
        WHERE n.nspname = 'public' AND c.relkind IN ('r', 'p')
    LOOP
        EXECUTE format('ALTER TABLE public.%I ENABLE ROW LEVEL SECURITY', rel.relname);
        IF api_roles THEN
            EXECUTE format('DROP POLICY IF EXISTS erp_deny_api ON public.%I', rel.relname);
            EXECUTE format(
                'CREATE POLICY erp_deny_api ON public.%I AS RESTRICTIVE FOR ALL TO anon, authenticated USING (false) WITH CHECK (false)',
                rel.relname
            );
            EXECUTE format('REVOKE ALL ON TABLE public.%I FROM PUBLIC, anon, authenticated', rel.relname);
        ELSE
            EXECUTE format('REVOKE ALL ON TABLE public.%I FROM PUBLIC', rel.relname);
        END IF;
    END LOOP;

    FOR rel IN
        SELECT c.relname
        FROM pg_class c
        JOIN pg_namespace n ON n.oid = c.relnamespace
        WHERE n.nspname = 'public' AND c.relkind = 'S'
    LOOP
        IF api_roles THEN
            EXECUTE format('REVOKE ALL ON SEQUENCE public.%I FROM PUBLIC, anon, authenticated', rel.relname);
        ELSE
            EXECUTE format('REVOKE ALL ON SEQUENCE public.%I FROM PUBLIC', rel.relname);
        END IF;
    END LOOP;

    FOR rel IN
        SELECT p.proname, pg_get_function_identity_arguments(p.oid) AS args
        FROM pg_proc p
        JOIN pg_namespace n ON n.oid = p.pronamespace
        WHERE n.nspname = 'public'
    LOOP
        IF api_roles THEN
            EXECUTE format(
                'REVOKE ALL ON FUNCTION public.%I(%s) FROM PUBLIC, anon, authenticated',
                rel.proname,
                rel.args
            );
        ELSE
            EXECUTE format('REVOKE ALL ON FUNCTION public.%I(%s) FROM PUBLIC', rel.proname, rel.args);
        END IF;
    END LOOP;
END;
$fn$;

-- @@statement
REVOKE ALL ON FUNCTION app_private.lock_api_access() FROM PUBLIC;

-- @@statement
DO $guard$
BEGIN
    IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'anon') THEN
        EXECUTE 'REVOKE ALL ON FUNCTION app_private.lock_api_access() FROM anon';
    END IF;
    IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'authenticated') THEN
        EXECUTE 'REVOKE ALL ON FUNCTION app_private.lock_api_access() FROM authenticated';
    END IF;
END
$guard$;

-- @@statement
SELECT app_private.lock_api_access();

-- @@statement
DO $defaults$
BEGIN
    IF EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'anon')
       AND EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'authenticated') THEN
        EXECUTE 'ALTER DEFAULT PRIVILEGES IN SCHEMA public REVOKE ALL ON TABLES FROM PUBLIC, anon, authenticated';
        EXECUTE 'ALTER DEFAULT PRIVILEGES IN SCHEMA public REVOKE ALL ON SEQUENCES FROM PUBLIC, anon, authenticated';
        EXECUTE 'ALTER DEFAULT PRIVILEGES IN SCHEMA public REVOKE ALL ON FUNCTIONS FROM PUBLIC, anon, authenticated';
    END IF;
END
$defaults$;
