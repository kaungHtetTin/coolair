<?php
return ['up'=>[
    "ALTER TABLE audit ADD COLUMN actor_username TEXT NOT NULL DEFAULT ''",
    "ALTER TABLE audit ADD COLUMN actor_name TEXT NOT NULL DEFAULT ''",
    "UPDATE audit SET actor_username=COALESCE((SELECT username FROM users WHERE id=audit.user_id),'System'),actor_name=COALESCE((SELECT name FROM users WHERE id=audit.user_id),'System')",
    "ALTER TABLE documents ADD COLUMN creator_username TEXT NOT NULL DEFAULT ''",
    "ALTER TABLE documents ADD COLUMN creator_name TEXT NOT NULL DEFAULT ''",
    "ALTER TABLE jobs ADD COLUMN creator_username TEXT NOT NULL DEFAULT ''",
    "ALTER TABLE jobs ADD COLUMN creator_name TEXT NOT NULL DEFAULT ''",
    "ALTER TABLE customers ADD COLUMN creator_username TEXT NOT NULL DEFAULT ''",
    "ALTER TABLE customers ADD COLUMN creator_name TEXT NOT NULL DEFAULT ''",
    "UPDATE documents SET creator_username=COALESCE((SELECT username FROM users WHERE id=documents.created_by),''),creator_name=COALESCE((SELECT name FROM users WHERE id=documents.created_by),'')",
    "UPDATE jobs SET creator_username=COALESCE((SELECT username FROM users WHERE id=jobs.created_by),''),creator_name=COALESCE((SELECT name FROM users WHERE id=jobs.created_by),'')",
    "UPDATE customers SET creator_username=COALESCE((SELECT username FROM users WHERE id=customers.created_by),''),creator_name=COALESCE((SELECT name FROM users WHERE id=customers.created_by),'')",
    'CREATE INDEX idx_audit_user_history ON audit(user_id,id)',
], 'down'=>[]];
