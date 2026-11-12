-- Catalogue (platforms, regions, products, sellers, offers, keys) and the order
-- tables, which "best-selling" needs from day one. Sessions and homepage tables
-- come in later migrations. All money is integer euro cents.

-- Lookup tables: the store's filter options and their display names live in the
-- database, so the frontend never hardcodes them.
CREATE TABLE platforms (
    slug text PRIMARY KEY,              -- used in URLs: ?platform=steam
    name text NOT NULL                  -- shown to people: "Steam"
);

CREATE TABLE regions (
    slug text PRIMARY KEY,
    name text NOT NULL
);

-- One product = one game on one platform in one region, like Eneba's
-- "Elden Ring (PC) Steam Key GLOBAL" and "Elden Ring (PS5) PSN Key EUROPE".
CREATE TABLE products (
    id               bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    slug             text NOT NULL UNIQUE,
    name             text NOT NULL,
    description      text NOT NULL,
    image_url        text NOT NULL,
    platform         text NOT NULL REFERENCES platforms (slug),
    region           text NOT NULL REFERENCES regions (slug),
    release_date     date NOT NULL,
    list_price_cents integer NOT NULL CHECK (list_price_cents > 0), -- publisher's price, for "-45%" badges
    created_at       timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE sellers (
    id             bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    name           text NOT NULL UNIQUE,
    rating_percent numeric(4, 1) NOT NULL CHECK (rating_percent BETWEEN 0 AND 100), -- share of positive ratings
    rating_count   integer NOT NULL CHECK (rating_count >= 0)
);

-- A seller's price for a product. Several sellers compete on the same product.
CREATE TABLE offers (
    id          bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    product_id  bigint NOT NULL REFERENCES products (id),
    seller_id   bigint NOT NULL REFERENCES sellers (id),
    price_cents integer NOT NULL CHECK (price_cents > 0),
    UNIQUE (product_id, seller_id)      -- one offer per seller per product
);

CREATE TABLE users (
    id            bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    email         text NOT NULL UNIQUE, -- stored lower-case by the app
    password_hash text NOT NULL,        -- from PHP's password_hash(), never the password itself
    created_at    timestamptz NOT NULL DEFAULT now()
);

CREATE TABLE orders (
    id                bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    user_id           bigint NOT NULL REFERENCES users (id),
    status            text NOT NULL CHECK (status IN ('pending', 'paid', 'cancelled')),
    total_cents       integer NOT NULL CHECK (total_cents > 0),
    stripe_session_id text UNIQUE,      -- set when the Stripe Checkout session is created (Day 5)
    created_at        timestamptz NOT NULL DEFAULT now(),
    paid_at           timestamptz
);

-- One line of an order. The price is copied in, so later price changes do not
-- rewrite what the buyer actually paid.
CREATE TABLE order_items (
    id               bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    order_id         bigint NOT NULL REFERENCES orders (id),
    offer_id         bigint NOT NULL REFERENCES offers (id),
    quantity         integer NOT NULL CHECK (quantity > 0),
    unit_price_cents integer NOT NULL CHECK (unit_price_cents > 0)
);

-- Stock is real rows: one row per key a seller has uploaded.
-- available → reserved (checkout started) → sold (payment confirmed).
CREATE TABLE product_keys (
    id            bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
    offer_id      bigint NOT NULL REFERENCES offers (id),
    code          text NOT NULL UNIQUE,
    status        text NOT NULL DEFAULT 'available' CHECK (status IN ('available', 'reserved', 'sold')),
    order_item_id bigint REFERENCES order_items (id),
    -- A key belongs to an order exactly when it is not available.
    CHECK ((status = 'available') = (order_item_id IS NULL))
);

-- Indexes on the foreign keys the store queries join on.
CREATE INDEX offers_product_id_idx ON offers (product_id);
CREATE INDEX order_items_offer_id_idx ON order_items (offer_id);
CREATE INDEX order_items_order_id_idx ON order_items (order_id);
CREATE INDEX orders_user_id_idx ON orders (user_id);
-- Partial index: only available keys, which is all the stock checks look at.
CREATE INDEX product_keys_available_idx ON product_keys (offer_id) WHERE status = 'available';

-- The catalogue as the store sees it: each product with its display names,
-- cheapest in-stock price, number of sellers with stock, and units sold.
-- The store, search and homepage blocks all read from this one view.
CREATE VIEW product_listing AS
SELECT
    p.id,
    p.slug,
    p.name,
    p.image_url,
    p.release_date,
    p.list_price_cents,
    p.platform,
    pl.name AS platform_name,
    p.region,
    r.name AS region_name,
    (SELECT min(o.price_cents)
       FROM offers o
      WHERE o.product_id = p.id
        AND EXISTS (SELECT 1 FROM product_keys k WHERE k.offer_id = o.id AND k.status = 'available')
    ) AS lowest_price_cents,
    (SELECT count(*)
       FROM offers o
      WHERE o.product_id = p.id
        AND EXISTS (SELECT 1 FROM product_keys k WHERE k.offer_id = o.id AND k.status = 'available')
    ) AS offer_count,
    (SELECT coalesce(sum(oi.quantity), 0)
       FROM order_items oi
       JOIN orders ord ON ord.id = oi.order_id
       JOIN offers o ON o.id = oi.offer_id
      WHERE o.product_id = p.id
        AND ord.status = 'paid'
    ) AS units_sold
FROM products p
JOIN platforms pl ON pl.slug = p.platform
JOIN regions r ON r.slug = p.region;
