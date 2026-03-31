-- Migration: add persisted custom surcharge for cake orders (MySQL)

ALTER TABLE cake_orders
    ADD COLUMN additional_cost DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER notes;
