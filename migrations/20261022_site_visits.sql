CREATE TABLE IF NOT EXISTS public.site_visits (
    visit_id BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id VARCHAR NOT NULL REFERENCES public.users(user_id) ON DELETE CASCADE,
    user_role VARCHAR(32) NOT NULL,
    ip_address INET,
    entered_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    exited_at TIMESTAMPTZ
);

CREATE INDEX IF NOT EXISTS idx_site_visits_entered_at
    ON public.site_visits (entered_at);

CREATE INDEX IF NOT EXISTS idx_site_visits_user_entered_at
    ON public.site_visits (user_id, entered_at);
