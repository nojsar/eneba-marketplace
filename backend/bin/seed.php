<?php

declare(strict_types=1);

// Fills the database with demo data: platforms, regions, sellers, products,
// offers, keys and two months of past orders (so "best-selling" means something).
//
//   php bin/seed.php             seed an empty database (refuses if products exist)
//   php bin/seed.php --if-empty  same, but succeed quietly if already seeded (container start)
//   php bin/seed.php --fresh     wipe all data and seed again (development only)

use App\Database;
use App\Env;

require __DIR__ . '/../vendor/autoload.php';

Env::load(__DIR__ . '/../.env');
$pdo = (new Database(Env::get('DATABASE_URL')))->pdo();
$data = require __DIR__ . '/../db/seed_data.php';

$fresh = in_array('--fresh', $argv, true);
$ifEmpty = in_array('--if-empty', $argv, true);

if (!$fresh && (int) $pdo->query('SELECT count(*) FROM products')->fetchColumn() > 0) {
    echo $ifEmpty ? "Demo data already present\n" : "Products already exist; use --fresh to wipe and reseed\n";
    exit($ifEmpty ? 0 : 1);
}

// Fixed seed: every run produces the same sellers, prices and stock.
mt_srand(2027);

// One transaction: either all demo data is inserted, or none of it.
$pdo->beginTransaction();

if ($fresh) {
    $pdo->exec('TRUNCATE platforms, regions, products, sellers, offers, product_keys, users, orders, order_items
                RESTART IDENTITY CASCADE');
}

$insertPlatform = $pdo->prepare('INSERT INTO platforms (slug, name) VALUES (?, ?)');
foreach ($data['platforms'] as $slug => $name) {
    $insertPlatform->execute([$slug, $name]);
}

$insertRegion = $pdo->prepare('INSERT INTO regions (slug, name) VALUES (?, ?)');
foreach ($data['regions'] as $slug => $name) {
    $insertRegion->execute([$slug, $name]);
}

$insertSeller = $pdo->prepare('INSERT INTO sellers (name, rating_percent, rating_count) VALUES (?, ?, ?) RETURNING id');
$sellerIds = [];
foreach ($data['sellers'] as [$name, $rating, $count]) {
    $insertSeller->execute([$name, $rating, $count]);
    $sellerIds[] = (int) $insertSeller->fetchColumn();
}

$insertProduct = $pdo->prepare(
    'INSERT INTO products (slug, name, description, image_url, platform, region, release_date, list_price_cents)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?) RETURNING id'
);
$insertOffer = $pdo->prepare('INSERT INTO offers (product_id, seller_id, price_cents) VALUES (?, ?, ?) RETURNING id');
$popularity = []; // product id => weight, used for the past orders below

foreach ($data['games'] as $game) {
    foreach ($game['listings'] as [$platform, $region, $weight]) {
        $insertProduct->execute([
            slugify("{$game['name']} $platform $region"),
            $game['name'],
            $game['description'],
            "https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/{$game['steamAppId']}/library_600x900.jpg",
            $platform,
            $region,
            $game['released'],
            $game['priceCents'],
        ]);
        $productId = (int) $insertProduct->fetchColumn();
        $popularity[$productId] = $weight;

        // 2–4 different sellers, each 15–60 % below the publisher's price, with 3–12 keys.
        $sellers = $sellerIds;
        shuffle($sellers);
        foreach (array_slice($sellers, 0, mt_rand(2, 4)) as $sellerId) {
            $price = (int) round($game['priceCents'] * (100 - mt_rand(15, 60)) / 100);
            $insertOffer->execute([$productId, $sellerId, $price]);
            insertKeys($pdo, (int) $insertOffer->fetchColumn(), mt_rand(3, 12));
        }
    }
}

// Three demo buyers. "!" is not a valid password hash, so password_verify()
// always fails for them: nobody can log in as a demo buyer.
$insertUser = $pdo->prepare("INSERT INTO users (email, password_hash) VALUES (?, '!') RETURNING id");
$buyerIds = [];
foreach (['ana', 'jonas', 'marta'] as $name) {
    $insertUser->execute(["$name@demo-buyers.invalid"]);
    $buyerIds[] = (int) $insertUser->fetchColumn();
}

// 80 paid one-item orders over the last 60 days, bought from the cheapest seller with stock.
$cheapestOffer = $pdo->prepare(
    "SELECT o.id, o.price_cents
       FROM offers o
      WHERE o.product_id = ?
        AND EXISTS (SELECT 1 FROM product_keys k WHERE k.offer_id = o.id AND k.status = 'available')
      ORDER BY o.price_cents
      LIMIT 1"
);
$insertOrder = $pdo->prepare(
    "INSERT INTO orders (user_id, status, total_cents, created_at, paid_at) VALUES (?, 'paid', ?, ?, ?) RETURNING id"
);
$insertItem = $pdo->prepare(
    'INSERT INTO order_items (order_id, offer_id, quantity, unit_price_cents) VALUES (?, ?, 1, ?) RETURNING id'
);
$sellKey = $pdo->prepare(
    "UPDATE product_keys SET status = 'sold', order_item_id = ?
      WHERE id = (SELECT id FROM product_keys WHERE offer_id = ? AND status = 'available' LIMIT 1)"
);

$orders = 0;
for ($i = 0; $i < 80; $i++) {
    $cheapestOffer->execute([weightedPick($popularity)]);
    $offer = $cheapestOffer->fetch();
    if ($offer === false) {
        continue; // that product sold out
    }

    $paidAt = date('c', time() - mt_rand(60, 60 * 86400));
    $insertOrder->execute([$buyerIds[array_rand($buyerIds)], $offer['price_cents'], $paidAt, $paidAt]);
    $insertItem->execute([(int) $insertOrder->fetchColumn(), $offer['id'], $offer['price_cents']]);
    $sellKey->execute([(int) $insertItem->fetchColumn(), $offer['id']]);
    $orders++;
}

$pdo->commit();

$keys = (int) $pdo->query('SELECT count(*) FROM product_keys')->fetchColumn();
printf("Seeded %d products, %d sellers, %d keys, %d past orders\n", count($popularity), count($sellerIds), $keys, $orders);

/** Inserts $count keys for one offer with a single multi-row INSERT (one round trip). */
function insertKeys(PDO $pdo, int $offerId, int $count): void
{
    $rows = implode(', ', array_fill(0, $count, '(?, ?)'));
    $values = [];
    for ($i = 0; $i < $count; $i++) {
        array_push($values, $offerId, fakeKeyCode());
    }
    $pdo->prepare("INSERT INTO product_keys (offer_id, code) VALUES $rows")->execute($values);
}

/** A fake key like "7KQ2M-PX9DA-3HNWT". No 0/O or 1/I, which are easy to misread. */
function fakeKeyCode(): string
{
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $groups = [];
    for ($g = 0; $g < 3; $g++) {
        $group = '';
        for ($c = 0; $c < 5; $c++) {
            $group .= $alphabet[mt_rand(0, strlen($alphabet) - 1)];
        }
        $groups[] = $group;
    }

    return implode('-', $groups);
}

/** Picks a key of $weights at random, in proportion to its weight. */
function weightedPick(array $weights): int
{
    $roll = mt_rand(1, array_sum($weights));
    foreach ($weights as $key => $weight) {
        $roll -= $weight;
        if ($roll <= 0) {
            return $key;
        }
    }

    throw new LogicException('Weights must be positive');
}

/** "Baldur's Gate 3 steam global" → "baldurs-gate-3-steam-global" */
function slugify(string $text): string
{
    $text = strtolower(str_replace("'", '', $text));

    return trim(preg_replace('/[^a-z0-9]+/', '-', $text), '-');
}
