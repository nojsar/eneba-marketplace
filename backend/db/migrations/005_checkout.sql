-- Fulfilment and release look up an order's keys through order_item_id.
-- Partial index: available keys (most rows) have no order item and are left out.
CREATE INDEX product_keys_order_item_id_idx ON product_keys (order_item_id) WHERE order_item_id IS NOT NULL;
