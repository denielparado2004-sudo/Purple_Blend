-- Add grouping and size labels so Milk Tea flavors can have independent variants.
-- Run once on existing Purple Blend databases before using Milk Tea size options.
ALTER TABLE menu_items
    ADD COLUMN variant_group CHAR(32) NULL AFTER category,
    ADD COLUMN size_label VARCHAR(20) NULL AFTER variant_group,
    ADD INDEX idx_menu_items_variant_group (variant_group);
