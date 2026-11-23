<?php
return ['up'=>['CREATE TABLE allocation_requests (request_key TEXT PRIMARY KEY, payment_id INTEGER NOT NULL REFERENCES payments(id), document_id INTEGER NOT NULL REFERENCES documents(id), amount INTEGER NOT NULL CHECK(amount>0))'], 'down'=>[]];
