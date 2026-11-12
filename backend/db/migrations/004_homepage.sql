-- The homepage is built from blocks the backend lists, in order. The frontend
-- only draws what it receives, so reordering, retitling or switching off a block
-- is a database change, not a frontend deploy.

-- Hero banners: their own small table, edited like content.
CREATE TABLE banners (
    id        bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    title     text NOT NULL,
    subtitle  text NOT NULL,
    image_url text NOT NULL,
    cta_label text NOT NULL,             -- button text, e.g. "Shop now"
    cta_url   text NOT NULL,             -- a path inside the app, e.g. /product/...
    position  integer NOT NULL,          -- 1 is the big banner, the rest are the side tiles
    is_active boolean NOT NULL DEFAULT true
);

-- The homepage layout: which blocks appear, in which order, with which heading.
-- `provider` names the backend class that supplies the block's content (see src/Home).
CREATE TABLE home_blocks (
    id        bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    provider  text NOT NULL,
    title     text,                      -- heading above the block; the hero has none
    position  integer NOT NULL UNIQUE,
    is_active boolean NOT NULL DEFAULT true
);

-- The starting homepage ships with the migration, like platforms ship with the seed:
-- every environment must have it, and it is configuration, not demo data.
INSERT INTO banners (title, subtitle, image_url, cta_label, cta_url, position) VALUES
    ('Clair Obscur: Expedition 33', 'The RPG everyone is talking about, from trusted sellers',
     'https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/1903340/library_hero.jpg',
     'Shop now', '/product/clair-obscur-expedition-33-steam-global', 1),
    ('Hollow Knight: Silksong', 'Hornet''s long-awaited adventure',
     'https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/1030300/library_hero.jpg',
     'View offers', '/product/hollow-knight-silksong-steam-global', 2),
    ('Split Fiction', 'A co-op adventure for two',
     'https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/2001120/library_hero.jpg',
     'View offers', '/product/split-fiction-ea-app-global', 3),
    ('PlayStation keys', 'God of War Ragnarök and more',
     'https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/2322010/library_hero.jpg',
     'Browse', '/store?platform=playstation', 4);

INSERT INTO home_blocks (provider, title, position) VALUES
    ('hero_banners', NULL, 1),
    ('best_sellers', 'Best sellers', 2),
    ('deals', 'Biggest discounts', 3);
