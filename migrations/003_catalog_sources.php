<?php
return ['up'=>[
    'CREATE TABLE catalog_sources (seed_key TEXT PRIMARY KEY, catalog_id INTEGER NOT NULL REFERENCES catalog(id), source TEXT NOT NULL, note TEXT NOT NULL DEFAULT \'\', created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)',
    'CREATE INDEX idx_catalog_sources_item ON catalog_sources(catalog_id)',
    'CREATE TABLE service_rate_sources (seed_key TEXT PRIMARY KEY, rate_id INTEGER NOT NULL REFERENCES service_rates(id), source TEXT NOT NULL)',
], 'down'=>[]];
