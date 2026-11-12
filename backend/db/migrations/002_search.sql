-- Typo-tolerant search.
--
-- pg_trgm splits text into trigrams, overlapping 3-letter pieces:
-- "witcher" → "  w", " wi", "wit", "itc", "tch", "che", "her", "er ".
-- Two strings that share most of their trigrams are similar even if a letter
-- is missing or wrong, so "witchr" still finds "The Witcher 3".
CREATE EXTENSION IF NOT EXISTS pg_trgm;

-- A GIN index over the trigrams of every product name. Postgres can use it for
-- both ways the store searches: name ILIKE '%elden%' and 'eldn' <% name.
CREATE INDEX products_name_trgm_idx ON products USING gin (name gin_trgm_ops);
