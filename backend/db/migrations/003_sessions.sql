-- Login sessions. The browser holds a random token in an httpOnly cookie; the
-- database only stores its SHA-256 hash, so a leaked copy of this table cannot
-- be used to log in as anyone. Logging out deletes the row.
CREATE TABLE sessions (
    token_hash text PRIMARY KEY,                                    -- sha256 of the cookie value, as hex
    user_id    bigint NOT NULL REFERENCES users (id) ON DELETE CASCADE,
    created_at timestamptz NOT NULL DEFAULT now(),
    expires_at timestamptz NOT NULL
);

CREATE INDEX sessions_user_id_idx ON sessions (user_id);
